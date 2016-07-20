<?php

namespace CirrusSearch\Job;

use CirrusSearch\OtherIndexes;
use JobQueueGroup;
use Title;

/**
 * Job wrapper around OtherIndexes. Used during page updates.
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
class OtherIndex extends Job {
	/**
	 * Check if we need to make a job and inject one if so.
	 *
	 * @param Title[] $titles The title we might update
	 * @param string|null $cluster The name of the cluster to write
	 *  to, or null for all clusters.
	 */
	public static function queueIfRequired( array $titles, $cluster ) {
		$titlesToUpdate = array();
		foreach( $titles as $title ) {
			if ( OtherIndexes::getExternalIndexes( $title ) ) {
				$titlesToUpdate[] = array( $title->getNamespace(), $title->getText() );
			}
		}
		if ( $titlesToUpdate ) {
			// Note that we're updating a bunch of titles but we have to pick one to
			// attach to the job so we pick the first one.
			JobQueueGroup::singleton()->push(
				new self( $titles[ 0 ], array(
					'titles' => $titlesToUpdate,
					'cluster' => $cluster,
				) )
			);
		}
	}

	protected function doJob() {
		$titles = array();
		foreach ( $this->params[ 'titles' ] as $titleArr ) {
			list( $namespace, $title ) = $titleArr;
			$titles[] = Title::makeTitle( $namespace, $title );
		}
		$flags = array();
		if ( $this->params['cluster'] ) {
			$flags[] = 'same-cluster';
		}
		$otherIdx = new OtherIndexes( $this->connection, $this->searchConfig, $flags, wfWikiID() );
		$otherIdx->updateOtherIndex( $titles );
	}
}
