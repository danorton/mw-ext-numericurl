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
define( 'MW_EXT_NUMERICURL_NAME_LC',         strtolower( MW_EXT_NUMERICURL_NAME ) );
define( 'MW_EXT_NUMERICURL_VERSION',         '1.0.0' );
define( 'MW_EXT_NUMERICURL_AUTHOR',          'Daniel Norton' );
define( 'MW_EXT_NUMERICURL_QUERY_PREFIX',    'nu' );

define( 'MW_EXT_NUMERICURL_API_PARAM_NAME',   MW_EXT_NUMERICURL_NAME_LC );
define( 'MW_EXT_NUMERICURL_API_MID',         'nu' );

define( 'MW_EXT_NUMERICURL_API_CLASS',       'Api' . MW_EXT_NUMERICURL_NAME );
define( 'MW_EXT_NUMERICURL_API_QUERY_CLASS', 'ApiQuery' . MW_EXT_NUMERICURL_NAME );

global $wgNumericUrl;
if ( !isset( $wgNumericUrl ) ) {
	$wgNumericUrl = array();
}

// Path template of a short URL, e.g. '/s/$1'
if ( !isset( $wgNumericUrl['template'] ) ) {
	$wgNumericUrl['template'] = null;
}

if ( !isset( $wgNumericUrl['longUrl'] ) ) {
	$wgNumericUrl['longUrl'] = array();
}

if ( !isset( $wgNumericUrl['mediumUrl'] ) ) {
	$wgNumericUrl['mediumUrl'] = array();
}

if ( !isset( $wgNumericUrl['shortestUrl'] ) ) {
	$wgNumericUrl['shortestUrl'] = array();
}

// Minimum length of a long URL
if ( !isset( $wgNumericUrl['longUrl']['length'] ) ) {
	$wgNumericUrl['longUrl']['length'] = 7;
}

// Maximum lifespan of a long URL (minutes)
if ( !isset( $wgNumericUrl['longUrl']['lifespan'] ) ) {
	$wgNumericUrl['longUrl']['lifespan'] = 60*24*366;     // at least one leap year
}

// Minimum length of a medium-length URL
if ( !isset( $wgNumericUrl['mediumUrl']['length'] ) ) {
	$wgNumericUrl['mediumUrl']['length'] = 5;
}

// Maximum lifespan of a medium-length URL (minutes)
if ( !isset( $wgNumericUrl['mediumUrl']['lifespan'] ) ) {
	$wgNumericUrl['mediumUrl']['lifespan'] = 60*24*93;    // at least three months
}

// Minimum length of a short URL
if ( !isset( $wgNumericUrl['shortestUrl']['length'] ) ) {
	$wgNumericUrl['shortestUrl']['length'] = 3;
}

// Maximum lifespan of a short URL (minutes)
if ( !isset( $wgNumericUrl['longUrl']['lifespan'] ) ) {
	$wgNumericUrl['longUrl']['lifespan'] = 60*24*7;       // one week
}

// true if follow expired URLs not yet purged
if ( !isset( $wgNumericUrl['followExpired'] ) ) {
	$wgNumericUrl['followExpired'] = false;
}

// regex of titles to allow for basic URLs
if ( !isset( $wgNumericUrl['reTitlesWithToolLink'] ) ) {
	$wgNumericUrl['reTitlesWithToolLink'] = null;   // e.g. In the default namespace, only: '{^[^:]*(/|$)}i'
}

// regex of titles to prohibit for basic URLs
if ( !isset( $wgNumericUrl['reTitlesWithoutToolLink'] ) ) {
	$wgNumericUrl['reTitlesWithoutToolLink'] = null; // e.g. to disable on Special subpages '{^Special:.*/}i';
}

// true if basic URLs may link to revisions
if ( !isset( $wgNumericUrl['revisionToolLink'] ) ) {
	$wgNumericUrl['revisionToolLink'] = false;
	$wgNumericUrl['revisionToolLink'] = true;
}

// true if Basic URLs may link to page IDs
if ( !isset( $wgNumericUrl['pageIdToolLink'] ) ) {
	$wgNumericUrl['pageIdToolLink'] = false;
	$wgNumericUrl['pageIdToolLink'] = true;
}

// Long URL permission regions
// The names in these keys must be granted permissions in $wgGroupPermissions.
if ( !isset( $wgNumericUrl['regions'] ) ) {
	$wgNumericUrl['regions'] = array(
		'iw_local' => array(    // Local Interwikis
			'description-message' => 'numericurl-region-iw_local',
		),
	);
}


if ( !isset( $wgNumericUrl['queryParams'] ) ) {
	$wgNumericUrl['queryParams'] = array();
}

// query parameter name prefix
if ( !isset( $wgNumericUrl['queryPrefix'] ) ) {
	$wgNumericUrl['queryPrefix'] = MW_EXT_NUMERICURL_QUERY_PREFIX;
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
	$wgAPIPropModules, $wgAutoloadClasses, $wgAPIModules
;

$wgExtensionCredits['api'][] = array(
	'path'         => __DIR__ . '/' . MW_EXT_NUMERICURL_NAME,
	'name'         => MW_EXT_NUMERICURL_NAME,
	'description'  => 'Provide numeric URLs to represent long URLs.',
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
Hooks::register( 'WebRequestPathInfoRouter', 'NumericUrlCommon::onWebRequestPathInfoRouter' );

// Hook our own event, to report built-in URL regions
Hooks::register( 'NumericUrlGlobalRegionCheck', 'NumericUrlCommon::onNumericUrlGlobalRegionCheck' );

// Hook toolbox link
Hooks::register( 'SkinTemplateToolboxEnd', 'NumericUrlCommon::onSkinTemplateToolboxEnd' );

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

$wgHooks['LoadExtensionSchemaUpdates'][] = 'NumericUrlCommon::onLoadExtensionSchemaUpdates';

// default permissions are last

// 'follow-shared' might as well be true if anonymous redirectors are employed
$wgGroupPermissions['*'][MW_EXT_NUMERICURL_NAME_LC . '-follow-shared'] = true;
$wgGroupPermissions['*'][MW_EXT_NUMERICURL_NAME_LC . '-create-basic'] = false;
$wgGroupPermissions['*'][MW_EXT_NUMERICURL_NAME_LC . '-create-local'] = false;
$wgGroupPermissions['*'][MW_EXT_NUMERICURL_NAME_LC . '-create-global'] = false;
$wgGroupPermissions['*'][MW_EXT_NUMERICURL_NAME_LC . '-short'] = false;
$wgGroupPermissions['*'][MW_EXT_NUMERICURL_NAME_LC . '-medium'] = false;
$wgGroupPermissions['*'][MW_EXT_NUMERICURL_NAME_LC . '-slashes'] = false;
$wgGroupPermissions['*'][MW_EXT_NUMERICURL_NAME_LC . '-private'] = false;
$wgGroupPermissions['*'][MW_EXT_NUMERICURL_NAME_LC . '-relay-query'] = false;
$wgGroupPermissions['*'][MW_EXT_NUMERICURL_NAME_LC . '-expire-never'] = false;

$wgGroupPermissions['user'][MW_EXT_NUMERICURL_NAME_LC . '-follow-shared'] = true;

$wgGroupPermissions['autoconfirmed'][MW_EXT_NUMERICURL_NAME_LC . '-follow-shared'] = true;
$wgGroupPermissions['autoconfirmed'][MW_EXT_NUMERICURL_NAME_LC . '-create-basic'] = true;

$wgGroupPermissions['sysop'][MW_EXT_NUMERICURL_NAME_LC . '-follow-shared'] = true;
$wgGroupPermissions['sysop'][MW_EXT_NUMERICURL_NAME_LC . '-create-basic'] = true;
$wgGroupPermissions['sysop'][MW_EXT_NUMERICURL_NAME_LC . '-create-local'] = true;
$wgGroupPermissions['sysop'][MW_EXT_NUMERICURL_NAME_LC . '-create-global'] = true;
$wgGroupPermissions['sysop'][MW_EXT_NUMERICURL_NAME_LC . '-short'] = true;
$wgGroupPermissions['sysop'][MW_EXT_NUMERICURL_NAME_LC . '-medium'] = true;
$wgGroupPermissions['sysop'][MW_EXT_NUMERICURL_NAME_LC . '-slashes'] = true;
$wgGroupPermissions['sysop'][MW_EXT_NUMERICURL_NAME_LC . '-private'] = true;
$wgGroupPermissions['sysop'][MW_EXT_NUMERICURL_NAME_LC . '-relay-query'] = true;
$wgGroupPermissions['sysop'][MW_EXT_NUMERICURL_NAME_LC . '-expire-never'] = true;

// set default region permissions: deny default, allow sysop
$wgNumericUrl['fn'] = function( &$wgNumericUrl, &$wgGroupPermissions ) {
	foreach ( $wgNumericUrl['regions'] as $region ) {
		foreach ( array( 'follow', 'create' ) as $perm ) {
			$wgGroupPermissions['*'][MW_EXT_NUMERICURL_NAME_LC . "-$perm-region-$region"] = false;
			$wgGroupPermissions['sysop'][MW_EXT_NUMERICURL_NAME_LC . "-$perm-region-$region"] = true;
		}
	}
};
$wgNumericUrl['fn']($wgNumericUrl, $wgGroupPermissions);
unset($wgNumericUrl['fn']);

$wgGroupPermissions['autoconfirmed'][MW_EXT_NUMERICURL_NAME_LC . "-follow-region-iw_local"] = true;

/** @}*/
