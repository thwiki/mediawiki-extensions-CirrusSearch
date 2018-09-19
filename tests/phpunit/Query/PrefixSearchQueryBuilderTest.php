<?php

namespace CirrusSearch\Query;

use CirrusSearch\HashSearchConfig;
use CirrusSearch\Search\SearchContext;

/**
 * @covers CirrusSearch\Query\PrefixSearchQueryBuilder
 */
class PrefixSearchQueryBuilderTest extends \PHPUnit\Framework\TestCase {
	static private $WEIGHTS = [
		'title' => 2,
		'redirect' => 0.2,
		'title_asciifolding' => 1,
		'redirect_asciifolding' => 0.1
	];

	/**
	 * @expectedException ApiUsageException
	 */
	public function testRejectsOversizeQueries() {
		$qb = new PrefixSearchQueryBuilder();
		$config = new HashSearchConfig( [
			'CirrusSearchPrefixSearchStartsWithAnyWord' => false,
			'CirrusSearchPrefixWeights' => self::$WEIGHTS,
		] );
		$context = new SearchContext( $config );
		$qb->build( $context, str_repeat( 'a', 4096 ) );
	}

	public function testBuildsQuery() {
		$qb = new PrefixSearchQueryBuilder();
		$config = new HashSearchConfig( [
			'CirrusSearchPrefixSearchStartsWithAnyWord' => false,
			'CirrusSearchPrefixWeights' => self::$WEIGHTS,
		] );
		$context = new SearchContext( $config );
		// Not sure what we could reliably assert here. The code ran at least?
		$this->assertFalse( $context->isDirty() );
		$qb->build( $context, 'full keyword prefix' );
		$this->assertTrue( $context->isDirty() );
	}

	public function buildsPerWordQuery() {
		$qb = new PrefixSearchQueryBuilder();
		$config = new HashSearchConfig( [
			'CirrusSearchPrefixSearchStartsWithAnyWord' => true,
			'CirrusSearchPrefixWeights' => self::$WEIGHTS,
		] );
		$context = new SearchContext( $config );
		$this->assertEmpty( $context->getFilters() );
		$qb->build( $context, 'per word prefix' );
		$context->assertCount( 1, $context->getFilters() );
		$filter = $context->getFilters()[0];
		// ???
	}
}
