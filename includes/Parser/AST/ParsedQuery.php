<?php

namespace CirrusSearch\Parser\AST;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Parser\AST\Visitor\KeywordNodeVisitor;
use CirrusSearch\Parser\ParsedQueryClassifiersRepository;
use Wikimedia\Assert\Assert;

/**
 * Parsed query
 */
class ParsedQuery {

	/**
	 * markup to indicate that the query was cleaned up
	 * detecting a double quote used as a gershayim
	 * see T66350
	 */
	const CLEANUP_GERSHAYIM_QUIRKS = 'gershayim_quirks';

	/**
	 * markup to indicate that the had some question marks
	 * stripped
	 * @see \CirrusSearch\Util::stripQuestionMarks
	 */
	const CLEANUP_QMARK_STRIPPING = 'stripped_qmark';

	/**
	 * @var ParsedNode
	 */
	private $root;

	/**
	 * @var string
	 */
	private $query;

	/**
	 * @var string
	 */
	private $rawQuery;

	/**
	 * @var bool[] indexed by cleanup type
	 */
	private $queryCleanups;

	/**
	 * @var ParseWarning[]
	 */
	private $parseWarnings;

	/**
	 * @var array|string
	 */
	private $requiredNamespaces;

	/**
	 * @var CrossSearchStrategy|null (lazy loaded)
	 */
	private $crossSearchStrategy;

	/**
	 * @var ParsedQueryClassifiersRepository
	 */
	private $classifierRepository;

	/**
	 * @var bool[] indexed by query class name
	 */
	private $queryClassCache = [];

	/**
	 * @var string[] list of used features in the query
	 * @see \CirrusSearch\Query\KeywordFeature::getFeatureName()
	 */
	private $featuresUsed;

	/**
	 * ParsedQuery constructor.
	 * @param ParsedNode $root
	 * @param string $query cleaned up query string
	 * @param string $rawQuery original query as received by the search engine
	 * @param bool[] $queryCleanups indexed by cleanup type (non-empty when $query !== $rawQuery)
	 * @param array|string $requiredNamespaces
	 * @param ParseWarning[] $parseWarnings list of warnings detected during parsing
	 * @param ParsedQueryClassifiersRepository $repository
	 */
	public function __construct(
		ParsedNode $root,
		$query,
		$rawQuery,
		$queryCleanups,
		$requiredNamespaces,
		array $parseWarnings,
		ParsedQueryClassifiersRepository $repository
	) {
		$this->root = $root;
		$this->query = $query;
		$this->rawQuery = $rawQuery;
		$this->queryCleanups = $queryCleanups;
		$this->parseWarnings = $parseWarnings;
		Assert::parameter( is_array( $requiredNamespaces ) || $requiredNamespaces === 'all',
			'$requiredNamespaces', 'must be an array or "all"' );
		$this->requiredNamespaces = $requiredNamespaces;
		$this->classifierRepository = $repository;
	}

	/**
	 * @return ParsedNode
	 */
	public function getRoot() {
		return $this->root;
	}

	/**
	 * The query being parsed
	 * Some cleanups may have been made to the raw query
	 * @return string
	 */
	public function getQuery() {
		return $this->query;
	}

	/**
	 * The raw query as received by the search engine
	 * @return string
	 */
	public function getRawQuery() {
		return $this->rawQuery;
	}

	/**
	 * Check if the query was cleanup with this type
	 * @see ParsedQuery::CLEANUP_QMARK_STRIPPING
	 * @see ParsedQuery::CLEANUP_GERSHAYIM_QUIRKS
	 * @param string $cleanup
	 * @return bool
	 */
	public function hasCleanup( $cleanup ) {
		return isset( $this->queryCleanups[$cleanup] );
	}

	/**
	 * List of warnings detected at parse time
	 * @return ParseWarning[]
	 */
	public function getParseWarnings() {
		return $this->parseWarnings;
	}

	/**
	 * @return array|string array of additional namespaces or 'all' if all namespaces required
	 */
	public function getRequiredNamespaces() {
		return $this->requiredNamespaces;
	}

	/**
	 * Get the cross search strategy supported by this query.
	 *
	 * @return CrossSearchStrategy
	 */
	public function getCrossSearchStrategy() {
		if ( $this->crossSearchStrategy === null ) {
			$visitor = new class() extends KeywordNodeVisitor {
				public $strategy;

				public function __construct( array $excludeOccurs = [], array $keywordClasses = [] ) {
					parent::__construct( $excludeOccurs, $keywordClasses );
					$this->strategy = CrossSearchStrategy::allWikisStrategy();
				}

				/**
				 * @param KeywordFeatureNode $node
				 */
				function doVisitKeyword( KeywordFeatureNode $node ) {
					$this->strategy = $this->strategy
						->intersect( $node->getKeyword()->getCrossSearchStrategy( $node ) );
				}
			};
			$this->root->accept( $visitor );
			$this->crossSearchStrategy = $visitor->strategy;
		}
		return $this->crossSearchStrategy;
	}

	/**
	 * @param string $class
	 * @return bool
	 * @throws \CirrusSearch\Parser\ParsedQueryClassifierException if the class is unknown
	 */
	public function isQueryOfClass( $class ) {
		return $this->queryClassCache[$class] ?? $this->loadQueryClass( $class );
	}

	/**
	 * @param string $class
	 * @return bool
	 * @throws \CirrusSearch\Parser\ParsedQueryClassifierException
	 */
	private function loadQueryClass( $class ) {
		$classifier = $this->classifierRepository->getClassifier( $class );
		$newClasses = $classifier->classify( $this );
		foreach ( $classifier->classes() as $k ) {
			$this->queryClassCache[$k] = in_array( $k, $newClasses, true );
		}
		return $this->queryClassCache[$class];
	}

	/**
	 * Preload all known query classes and classify this
	 * query.
	 * @throws \CirrusSearch\Parser\ParsedQueryClassifierException
	 */
	public function preloadQueryClasses() {
		foreach ( $this->classifierRepository->getKnownClassifiers() as $class ) {
			$this->isQueryOfClass( $class );
		}
	}

	/**
	 * Get the list of keyword features used by this query.
	 * @see \CirrusSearch\Query\KeywordFeature::getFeatureName()
	 * @return string[]
	 */
	public function getFeaturesUsed() {
		if ( $this->featuresUsed === null ) {
			$visitor = new class() extends KeywordNodeVisitor {
				public $features = [];

				/**
				 * @param KeywordFeatureNode $node
				 */
				function doVisitKeyword( KeywordFeatureNode $node ) {
					$name = $node->getKeyword()
						->getFeatureName( $node->getKey(), $node->getDelimiter() );
					$this->features[$name] = true;
				}
			};
			$this->root->accept( $visitor );
			$this->featuresUsed = array_keys( $visitor->features );
		}
		return $this->featuresUsed;
	}

	/**
	 * @return array
	 */
	public function toArray() {
		$ar = [
			'query' => $this->query,
			'rawQuery' => $this->rawQuery
		];
		if ( !empty( $this->requiredNamespaces ) ) {
			$ar['requiredNamespaces'] = $this->requiredNamespaces;
		}
		if ( !empty( $this->queryCleanups ) ) {
			$ar['queryCleanups'] = $this->queryCleanups;
		}
		$this->preloadQueryClasses();
		$classes = array_keys( array_filter( $this->queryClassCache ) );
		if ( !empty( $classes ) ) {
			$ar['queryClassCache'] = $classes;
		}
		if ( !empty( $this->parseWarnings ) ) {
			$ar['warnings'] = array_map( function ( ParseWarning $w ) {
				return $w->toArray();
			}, $this->parseWarnings );
		}
		if ( !empty( $this->getFeaturesUsed() ) ) {
			$ar['featuresUsed'] = $this->getFeaturesUsed();
		}
		$ar['root'] = $this->getRoot()->toArray();
		return $ar;
	}
}
