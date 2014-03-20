<?php
/**
 * @ingroup Extensions
 * @{
 * MediaWiki extension providing numeric URLs of various radixes.
 *  Requires:
 *   - MediaWiki version: >= 1.19
 *
 * @file
 * @{
 * @copyright © 2014 Daniel Norton d/b/a WeirdoSoft - www.weirdosoft.com
 *
 * @section License
 * **GPL v3**\n
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * \n\n
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * \n\n
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * @}
 *
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "This file is an extension to MediaWiki software and is not designed for standalone use.\n";
	die( 1 );
}

if ( defined( 'MW_EXT_NUMERICURL_NAME' ) ) {
	echo "Extension module already loaded: " . MW_EXT_NUMERICURL_NAME . "\n";
	die ( 1 );
}

define( 'MW_EXT_NUMERICURL_NAME',            'NumericUrl' );
define( 'MW_EXT_NUMERICURL_VERSION',         '1.0.0' );
define( 'MW_EXT_NUMERICURL_AUTHOR',          'Daniel Norton' );

define( 'MW_EXT_NUMERICURL_API_PARAM_NAME',  'numericurl' );
define( 'MW_EXT_NUMERICURL_API_MID',         'nu' );

define( 'MW_EXT_NUMERICURL_API_CLASS',       'ApiNumericUrl' );
define( 'MW_EXT_NUMERICURL_API_QUERY_CLASS', 'ApiQueryNumericUrl' );

global $wgNumericUrl;
if ( !isset( $wgNumericUrl ) ) {
  $wgNumericUrl = array();
}

if ( !isset( $wgNumericUrl['template'] ) ) {
	$wgNumericUrl['template'] = null;
}

if ( !isset( $wgNumericUrl['api'] ) ) {
  $wgNumericUrl['api'] = array();
}

if ( !isset( $wgNumericUrl['api']['paramName'] ) ) {
  $wgNumericUrl['api']['paramName'] = MW_EXT_NUMERICURL_API_PARAM_NAME;
}

if ( !isset( $wgNumericUrl['api']['mid'] ) ) {
  $wgNumericUrl['api']['mid'] = MW_EXT_NUMERICURL_API_MID;
}

global
	$wgExtensionCredits, $wgExtensionMessagesFiles,
  $wgHooks, $wgAPIPropModules, $wgAutoloadClasses, $wgAPIModules
;

$wgExtensionCredits['api'][] = array(
	'path' => __DIR__ . '/' . MW_EXT_NUMERICURL_NAME,
	'name'         => MW_EXT_NUMERICURL_NAME,
	'description'  => 'Provide numeric URLs of various radixes.',
	'version'      => MW_EXT_NUMERICURL_VERSION,
	'author'       => MW_EXT_NUMERICURL_AUTHOR,
	'license-name' => '[http://www.gnu.org/licenses/gpl-3.0.txt GPL v3]',
	'url'          => 'http://www.wikimedia.org/wiki/Extension:NumericUrl',
);

// i18n
$wgExtensionMessagesFiles[MW_EXT_NUMERICURL_NAME] = __DIR__ . '/NumericUrl.i18n.php';

// Auto load our "common" class
$wgAutoloadClasses['NumericUrlCommon'] = __DIR__ . '/NumericUrlCommon.php';

// Hook special redirection paths
$wgHooks['WebRequestPathInfoRouter'][] = 'NumericUrlCommon::onWebRequestPathInfoRouter';

// Hook toolbox link
$wgHooks['SkinTemplateToolboxEnd'][] = 'NumericUrlCommon::onSkinTemplateToolboxEnd';

// Special page handling
$wgAutoloadClasses['NumericUrlSpecialPage'] = __DIR__ . '/NumericUrlSpecialPage.php';
$wgSpecialPages[MW_EXT_NUMERICURL_NAME] = 'NumericUrlSpecialPage';
$wgSpecialPageGroups[MW_EXT_NUMERICURL_NAME] = 'pagetools';

// API declarations

// action=query&prop=shorturl
$wgAPIPropModules[$wgNumericUrl['api']['paramName']] = MW_EXT_NUMERICURL_API_QUERY_CLASS;
$wgAutoloadClasses[MW_EXT_NUMERICURL_API_QUERY_CLASS] =
	 __DIR__ . '/' . MW_EXT_NUMERICURL_API_QUERY_CLASS . '.php';

// action=shorturl
$wgAPIModules[$wgNumericUrl['api']['paramName']] = MW_EXT_NUMERICURL_API_CLASS;
$wgAutoloadClasses[MW_EXT_NUMERICURL_API_CLASS] =
	 __DIR__ . '/' . MW_EXT_NUMERICURL_API_CLASS . '.php';

// resources
$wgResourceModules['ext.numericUrl.toolpage'] = array(
	'styles' => MW_EXT_NUMERICURL_NAME . '.css',
	'localBasePath' => __DIR__,
);
/** @}*/
