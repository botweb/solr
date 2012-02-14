<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Ingo Renner <ingo@typo3.org>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 * Index Queue initializer for pages which also covers resolution of mount
 * pages.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_indexqueue_initializer_Page extends tx_solr_indexqueue_initializer_Abstract {

	/**
	 * Initializes Index Queue page items for a site. Includes regular pages
	 * and mounted pages - no nested mount page structures though.
	 *
	 * @return boolean TRUE if initialization was successful, FALSE on error.
	 * @see tx_solr_indexqueue_initializer_Abstract::initialize()
	 * @see tx_solr_IndexQueueInitializer::initialize()
	 */
	public function initialize() {
		$pagesInitialized      = parent::initialize();
		$mountPagesInitialized = $this->initializeMountPages();

		return ($pagesInitialized && $mountPagesInitialized);
	}

	/**
	 * Initializes Mount Pages to be indexed through the Index Queue. The Mount
	 * Pages are searched and their mounted virtual sub-trees are then resolved
	 * and added to the Index Queue as if they were actually present below the
	 * Mount Page.
	 *
	 * @return boolean TRUE if initialization of the Mount Pages was successful, FALSE otherwise
	 */
	protected function initializeMountPages() {
		$mountPagesInitialized = FALSE;
		$mountPages = $this->findMountPages();

		if (empty($mountPages)) {
			$mountPagesInitialized = TRUE;
			return $mountPagesInitialized;
		}

		foreach ($mountPages as $mountPage) {
			$mountedPages = $this->resolveMountPageTree($mountPage['mountPageSource']);

				// handling mount_pid_ol behavior
			if ($mountPage['mountPageOverlayed']) {
					// the page shows the mounted page's content
				$mountedPages[] = $mountPage['mountPageSource'];
			} else {
					// Add page like a regular page, as only the sub tree is
					// mounted. The page itself has its own content.
				t3lib_div::makeInstance('tx_solr_indexqueue_Queue')->updateItem(
					$this->type,
					$mountPage['uid'],
					$this->indexingConfigurationName
				);
			}

			$this->databaseTransactionStart();
			try {
				$this->addMountedPagesToIndexQueue($mountedPages);
				$this->addIndexQueueItemIndexingProperties($mountPage, $mountedPages);

				$this->databaseTransactionCommit();
			} catch (Exception $e) {
				$this->databaseTransactionRollback();

				t3lib_div::devLog(
					'Index Queue initialization failed for mount pages',
					'solr',
					3,
					array($e->__toString())
				);
				break;
			}
		}
		$mountPagesInitialized = TRUE;

		return $mountPagesInitialized;
	}

	/**
	 * Adds the virtual / mounted pages to the Index Queue as if they would
	 * belong to the same site where they are mounted.
	 *
	 * @param array $mountedPages An array of mounted page IDs
	 */
	protected function addMountedPagesToIndexQueue(array $mountedPages) {
		$initializationQuery = 'INSERT INTO tx_solr_indexqueue_item (root, item_type, item_uid, indexing_configuration, changed) '
			. $this->buildSelectStatement() . ' '
			. 'FROM pages '
			. 'WHERE '
				. 'uid IN(' . implode(',', $mountedPages) . ') '
				. $this->buildTcaWhereClause();

		$GLOBALS['TYPO3_DB']->sql_query($initializationQuery);
	}

	/**
	 * Adds Index Queue item indexing properties for mounted pages. The page
	 * indexer later needs to know that he's dealing with a mounted page, the
	 * indexing properties will let make it possible for the indexer to
	 * distinguish the mounted pages.
	 *
	 * @param array $mountPage An array with information about the root/destination Mount Page
	 * @param array $mountedPages An array of mounted page IDs
	 */
	protected function addIndexQueueItemIndexingProperties(array $mountPage, array $mountedPages) {
		$mountPageItems = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'tx_solr_indexqueue_item',
			'root = ' . intval($this->site->getRootPageId())
				. ' AND item_type = \'pages\' '
				. ' AND item_uid IN(' . implode(',', $mountedPages) . ')'
		);

		foreach ($mountPageItems as $mountPageItemRecord) {
			$mountPageItem = t3lib_div::makeInstance('tx_solr_indexqueue_Item', $mountPageItemRecord);

			$mountPageItem->setIndexingProperty('mountPageSource',      $mountPage['mountPageSource']);
			$mountPageItem->setIndexingProperty('mountPageDestination', $mountPage['mountPageDestination']);
			$mountPageItem->setIndexingProperty('isMountedPage',        '1');

			$mountPageItem->storeIndexingProperties();
		}
	}


		// Mount Page resolution


	/**
	 * Finds the mount pages in the current site.
	 *
	 * @return array An array of mount pages
	 */
	protected function findMountPages() {
		return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid,
				\'1\'        as isMountPage,
				mount_pid    as mountPageSource,
				uid          as mountPageDestination,
				mount_pid_ol as mountPageOverlayed',
			'pages',
			$this->buildPagesClause()
				. $this->buildTcaWhereClause()
				. ' AND doktype = 7'
		);
	}

	/**
	 * Gets all the pages from a mounted page tree.
	 *
	 * @param integer $mountPageSourceId
	 * @return array An array of page IDs in the mounted page tree
	 */
	protected function resolveMountPageTree($mountPageSourceId) {
		$mountedSite = tx_solr_Site::getSiteByPageId($mountPageSourceId);

		return $mountedSite->getPages($mountPageSourceId);
	}


		// Database helpers (as long as not supported by t3lib_DB)


	protected function databaseTransactionStart() {
		$GLOBALS['TYPO3_DB']->sql_query('START TRANSACTION');
	}

	protected function databaseTransactionCommit() {
		$GLOBALS['TYPO3_DB']->sql_query('COMMIT');
	}

	protected function databaseTransactionRollback() {
		$GLOBALS['TYPO3_DB']->sql_query('ROLLBACK');
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/initializer/class.tx_solr_indexqueue_initializer_page.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/initializer/class.tx_solr_indexqueue_initializer_page.php']);
}

?>