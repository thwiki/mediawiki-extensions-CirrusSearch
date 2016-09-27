<?php

namespace CirrusSearch;

use CirrusSearch\Search\InterwikiResultsType;
use CirrusSearch\Search\ResultSet;
use ObjectCache;
use User;

/**
 * Performs searches using Elasticsearch -- on interwikis!
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */
class InterwikiSearcher extends Searcher {
	/**
	 * @var int Max number of results to fetch from other wiki
	 */
	const MAX_RESULTS = 5;

	/**
	 * @var string interwiki prefix
	 */
	private $interwiki;

	/**
	 * Constructor
	 * @param Connection $connection
	 * @param int[] $namespaces Namespace numbers to search
	 * @param User|null $user
	 * @param string $index Base name for index to search from, defaults to $wgCirrusSearchIndexBaseName
	 * @param string $interwiki Interwiki prefix we're searching
	 */
	public function __construct( Connection $connection, array $namespaces, User $user = null, $index, $interwiki ) {
		// Only allow core namespaces. We can't be sure any others exist
		if ( $namespaces !== null ) {
			$namespaces = array_filter( $namespaces, function( $namespace ) {
				return $namespace <= 15;
			} );
		}
		parent::__construct( $connection, 0, self::MAX_RESULTS, null, $namespaces, $user, $index );
		$this->interwiki = $interwiki;
	}

	/**
	 * Fetch search results, from caches, if there's any
	 * @param string $term Search term to look for
	 * @return ResultSet|null|false
	 */
	public function getInterwikiResults( $term ) {
		// Return early if we can
		if ( !$term ) {
			return null;
		}

		$this->searchContext->setCacheTtl(
			$this->config->get( 'CirrusSearchInterwikiCacheTime' )
		);

		$this->setResultsType( new InterwikiResultsType( $this->interwiki ) );
		$results = $this->searchText( $term, false );
		if ( $results->isOK() ) {
			return $results->getValue();
		} else {
			return false;
		}
	}

	/**
	 * Get the index basename for a given interwiki prefix, if one is defined.
	 * @param string $interwiki
	 * @return string|null
	 */
	public static function getIndexForInterwiki( $interwiki ) {
		// These settings should be common for all wikis, so globals
		// are _probably_ OK here.
		global $wgCirrusSearchInterwikiSources, $wgCirrusSearchWikiToNameMap;

		if ( isset( $wgCirrusSearchInterwikiSources[$interwiki] ) ) {
			return $wgCirrusSearchInterwikiSources[$interwiki];
		}

		if ( isset( $wgCirrusSearchWikiToNameMap[$interwiki] ) ) {
			return $wgCirrusSearchWikiToNameMap[$interwiki];
		}

		return null;
	}

	/**
	 * We don't support extra indices when we're doing interwiki searches
	 *
	 * @see Searcher::getAndFilterExtraIndexes()
	 * @return array
	 */
	protected function getAndFilterExtraIndexes() {
		return [];
	}

	/**
	 * @return string The stats key used for reporting hit/miss rates of the
	 *  application side query cache.
	 */
	protected function getQueryCacheStatsKey() {
		return 'CirrusSearch.query_cache.interwiki';
	}
}
