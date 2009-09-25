<?php
/*                                                                        *
 * This script belongs to the FLOW3 package "Fluid".                      *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License as published by the *
 * Free Software Foundation, either version 3 of the License, or (at your *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * View helper for rendering gravatar images.
 * See http://www.gravatar.com
 *
 * = Examples =
 *
 * <code>
 * <blog:gravatar emailAddress="foo@bar.com" size="40" defaultImageUri="someDefaultImage" />
 * </code>
 *
 * <output>
 * <img src="http://www.gravatar.com/avatar/4a28b782cade3dbcd6e306fa4757849d?d=someDefaultImage&s=40" />
 * </output>
 *
 * @version $Id: GravatarViewHelper.php 1356 2009-09-23 21:22:38Z bwaidelich $
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class Tx_Solr_ViewHelpers_PageBrowserViewHelper extends Tx_Fluid_Core_ViewHelper_AbstractViewHelper {

	/**
	 * Render the gravatar image
	 *
	 * @param integer $numberOfResults Number of total results
	 * @param integer $resultsPerPage Results per page
	 * @param array $pageBrowserConfiguration Page browser config
	 * @return string The rendered image tag
	 */
	public function render($numberOfResults, $resultsPerPage, $pageBrowserConfiguration) {
		$numberOfPages = intval($numberOfResults / $resultsPerPage)
			+ (($numberOfResults % $resultsPerPage) == 0 ? 0 : 1);

		$pageBrowserConfiguration = array_merge(
			$pageBrowserConfiguration,
			array(
				'pageParameterName' => 'tx_solr_results|page',
				'numberOfPages'     => $numberOfPages,
				//'extraQueryString'  => '&tx_solr[q]=' . $this->search->getQuery()->getKeywords(), // TODO
				'disableCacheHash'  => true,
			)
		);

		//$pageBrowserConfiguration = Tx_Extbase_Utility_TypoScript::convertPlainArrayToTypoScriptArray($pageBrowserConfiguration);
			// Get page browser
		$cObj = t3lib_div::makeInstance('tslib_cObj');
		$cObj->start(array(), '');

		$pageBrowser = $cObj->cObjGetSingle('USER_INT', $pageBrowserConfiguration);

		return $pageBrowser;
	}
}


?>