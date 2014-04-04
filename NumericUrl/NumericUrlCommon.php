<?php
/**
 * @ingroup Extensions
 * @{
 * NumericUrlCommon class
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

if ( !defined( 'MW_EXT_NUMERICURL_NAME' ) ) {
	echo "This file is an extension to MediaWiki software and is not designed for standalone use.\n";
	die ( 1 );
}

global $IP;
require_once "$IP/extensions/Weirdo/Weirdo.php";

require_once __DIR__ . '/NumericUrlMapInstance.php';

class NumericUrlCommon {

	/** */
	const URL_CLASS_BASIC   = 0x0001;

	/** */
	const URL_CLASS_LOCAL   = 0x0002;

	/** */
	const URL_CLASS_GROUP   = 0x0004;

	/** */
	const URL_CLASS_GLOBAL  = 0x0008;

	/** */
	const EXTENSION_NAME = MW_EXT_NUMERICURL_NAME;

	/** */
	const EXTENSION_NAME_LC = MW_EXT_NUMERICURL_NAME_LC;

	/** */
	const SPECIAL_PAGE_TITLE = MW_EXT_NUMERICURL_NAME;

	/** */
	const URL_SCHEME_HTTP = 'http';

	/** */
	const URL_SCHEME_HTTPS = 'https';

	/** */
	const URL_SCHEME_FOLLOW = '//';

	/** Extension configuration; from global $wgNumericUrl */
	public static $config;

	/** */
	public static $baseUrl;

	/** */
	public static $baseUrlParts;

	/** */
	public static $baseScheme;

	/** Subset of page actions for which we show the toolbox link. */
	public static $toolboxActions = array( 'view',
		// 'cached', 'credits', 'edit', 'history',
		// 'info', 'purge', 'watch',
		);

	/** If to display the toolbox link on specific page ID pages. */
	public static $toolboxOnPageId = true; // false;

	/** */
	public static $_debugLogLevel = 0;

	/** */
	public static $userRightsNames = array(
		'follow-shared' => true,
		'follow-global' => true,
		'create-basic' => true,
		'create-local' => true,
		'create-group' => true,
		'create-global' => true,
		'create-notrack' => true,
		'create-password' => true,
		'create-noexpire' => true,
	);



	/** Constructor for class's singleton object.
	 *
	 * Throws ErrorException if singleton already created.
	 */
	public function __construct() {
		if ( self::$_self || !self::$_singleton ) {
			throw new ErrorException( 'Invalid attempt to instantiate static/singleton ' . __CLASS__ . ' class' );
		}
	}

	/**
	 * Get class's singleton object.
	 *
	 * @returns     NumericUrlCommon instance.
	 */
	public static function singleton() {
		if ( !self::$_self ) {
			self::$_singleton = true;
			self::$_self = new self();
		}
		return self::$_self;
	}

	/** */
	public static function isUrlBasic( $url ) {
	}

	/** */
	public static function isUrlInGroup( $url ) {
	}

	/** */
	public static function isAllowed( $userRightsName, $user = null ) {
		if ( $user === null ) {
			if ( self::$_defaultUser === null ) {
				self::$_defaultUser = RequestContext::getMain()->getUser();
			}
			$user = self::$_defaultUser;
		}
		if ( is_array( $userRightsName ) ) {
			return self::_isAllowedAll( $userRightsName, $user );
		}
		if ( !isset( self::$userRightsNames[$userRightsName] ) ) {
			trigger_error(
				sprintf( '%s: unrecognized user right queried: "%s"', __METHOD__, $userRightsName ),
				E_USER_WARNING );
			return false;
		}
		return $user->isAllowed( self::getFullUserRightsName( $userRightsName ) );
	}

	/** */
	public static function getFullUserRightsName( $userRightsName ) {
		static $fullUserRightsNamesCache = array();
		if ( !isset( $fullUserRightsNamesCache[$userRightsName] ) ) {
			if ( !isset( self::$userRightsNames[$userRightsName] ) ) {
				trigger_error(
					sprintf( '%s: unrecognized user rights name: "%s"', __METHOD__, $userRightsName ),
					E_USER_WARNING );
				return null;
			}
			$fullUserRightsNamesCache[$userRightsName] = self::EXTENSION_NAME_LC . "-$userRightsName";
		}
		return $fullUserRightsNamesCache[$userRightsName];
	}

	/** */
	public static function onWebRequestPathInfoRouter( $pathRouter ) {
		self::_debugLog( 20, __METHOD__ );
		if ( self::$config->template ) {
			$pathRouter->add( self::$config->template,
				array( 'title' => self::$_specialPageTitle->getPrefixedText() )
			);
		}
	}

	/** */
	public static function onNumericUrlRegionCheck(
		&$regions,
		$urlText,
		$urlParts,
		$urlAuthority,
		$urlMapInstance ) {

		// don't do anything if iw_local has been removed from the list of regions
		if ( !isset( self::$config->regions->iw_local ) ) {
			return true;
		}

		self::_debugLog( 20,
			sprintf( '%s: url=\"%s\"; authority=\"%s\"', __METHOD__ , $urlText, $urlAuthority ),
			E_USER_WARNING );

		if ( self::iwLocalInfoFromUrl( $urlText ) ) {
			$regions['iw_local'] = self::$config->regions->iw_local;
		}
		return true;
	}

	/** */
	public static function reverseUrlTemplate( $urlTemplate, $urlResult ) {
		static $paramToRegex = array(
			'%241' => '(?<_1>.*)',
			'%242' => '(?<_2>.*)',
		);
		$regex = '{^' . strtr( rawurlencode( $urlTemplate ), $paramToRegex ) . '$}';
		if ( !preg_match( $regex, rawurlencode( $urlResult ), $matches ) ) {
			return false;
		}
		$result = array();
		foreach ( $matches as $k => $v ) {
			if ( is_string( $k ) ) {
				$result[(int)$k[1]] = rawurldecode( $v );
			}
		}
		ksort( $result );
		return $result;
	}

	/**
	 *
	 */
	public static function parseUrlForTitle() {
	}

	/**
	 * Given a fully qualified URL, determine its scope.
	 */
	public static function scopeFromUrl( $urlOrParts ) {
		self::_debugLog( 20, __METHOD__ );
		$urlParts = is_string( $urlOrParts ) ? NumericUrlMapInstance::parse( $url ) : $urlOrParts;
		if ( $urlParts === false ) {
			// not a valid url
			return false;
		}
		if ( NumericUrlMapInstance::isValid( $urlParts ) !== NumericUrlMapInstance::VALID_ABSOLUTE ) {
			trigger_error(
				sprintf( '%s: invalid URL%s', __METHOD__ , is_string( $urlOrParts ) ? ": \"$urlOrParts\"" : '' ),
				E_USER_WARNING );
			return false;
		}
		if ( isset( $urlParts['scheme'] ) ) {
		}
		// this is a local URL; see if it has basic URL query parameters, only
		if ( isset( $urlParts['fragment'] ) ) {
			return self::URL_SCOPE_LOCAL;
		}
		return self::URL_SCOPE_BASIC;
	}

	/**
	 * Determine whether our tool link belongs on the current page and, if so, construct it.
	 */
	public static function onSkinTemplateToolboxEnd( $tpl ) {
		$qp = self::$config->queryPrefix;
		self::_debugLog( 20, __METHOD__ );

		$context = $tpl->getSkin()->getContext();

		if ( !self::isAllowed( 'follow-shared', $context->getUser() ) ) {
			return;
		}

		$action = Action::getActionName( $context );

		// skip this unless specifically configured for the current action
		if ( !in_array( $action, self::$toolboxActions ) ) {
			self::_debugLog( 20, __METHOD__ . ': not configured for specified action' );
			return;
		}

		// skip this on error pages
		$out = $context->getOutput();
		$okStatus = ( ( $out->mStatusCode == '' ) || ( ( $out->mStatusCode / 100 ) == 2 ) );
		if ( !$okStatus ) {
			self::_debugLog( 20, __METHOD__ . ': not displayed for error pages' );
			return;
		}

		$title = $context->getTitle();

		// bugbug TODO - don't allow on main page
		// bugbug TODO - check $title->isDeleted[Quick]() ??
		// bugbug TODO - check $title->isRedirect()
		// bugbug TODO - if isSpecialPage(), invoke fixSpecialName()

		// skip if this is our own special page
		if ( $title->isSpecial( self::SPECIAL_PAGE_TITLE ) ) {
			self::_debugLog( 20, __METHOD__ . ': not displayed for our own pages' );
			return;
		}

		// skip if the page isn't known (e.g. redlink)
		if ( !$title->isKnown() ) {
			self::_debugLog( 20, __METHOD__ . ': not displayed for unknown pages' );
			return;
		}

		// skip if a redirect page
		if ( $title->isRedirect() ) {
			self::_debugLog( 20, __METHOD__ . ': not displayed for redirect pages' );
			return;
		}

		// pass the current query parameters to the tool, if invoked
		$query = array();

		// see if the configuration regexes rule out this title
		$urlTitle = (string)$title->getPrefixedUrl();
		if ( self::$config->reTitlesWithToolLink ) {
			if ( !preg_match( self::$config->reTitlesWithToolLink, $urlTitle ) ) {
				self::_debugLog( 20, __METHOD__ . ': did not match reTitlesWithToolLink' );
				return;
			}
		}
		if ( self::$config->reTitlesWithoutToolLink ) {
			if ( preg_match( self::$config->reTitlesWithoutToolLink, $urlTitle ) ) {
				self::_debugLog( 20, __METHOD__ . ': matched reTitlesWithoutToolLink' );
				return;
			}
		}

		$context->getRequest()->unsetVal( 'title' ); // remove redundant query parameter
		$urlMapInstance = new NumericUrlMapInstance(
			$title->getFullUrl(
				wfArrayToCgi( $context->getRequest()->getValues() ),
				false,
				PROTO_RELATIVE
				)
		);
		// see if any hooks want to suppress the toolbox link
		if ( Hooks::isRegistered( 'NumericUrlToolboxCheck' ) ) {
			$disable = false;
			wfRunHooks(
				'NumericUrlToolboxCheck',
				array(
					&$disable,
					$title,
					$urlMapInstance,
				)
			);

			self::_debugLog( 30,
				sprintf( '%s():%u: url=%s; regions=(%s)', __METHOD__, __LINE__,
					wfUrlencode( $urlMapInstance ),
					implode( ',', array_keys( $urlMapInstance->getRegions() ) )
					)
			);
			if ( $disable ) {
				return;
			}
		}

		$query[] = "{$qp}url=" . wfUrlencode( $urlMapInstance );

		// pass the specific page ID and/or revision, if specified
		$articleId = $title->getArticleID();
		if ( $articleId ) {
			$query[] = "{$qp}pageid=$articleId";
			self::_debugLog( 30,
				sprintf( '%s():%u: query=%s', __METHOD__, __LINE__, implode( '&', $query ) )
			);
			// oldid is for persistent URLs to specific revisions of pages
			$oldid = Article::newFromID( $articleId )->getOldID();
			if ( $oldid ) {
				// bail if we don't display the toolbox link for revision pages
				if ( !self::$config->revisionToolLink ) {
					self::_debugLog( 20, __METHOD__ . ': not configured for display on specific revisions' );
					return;
				}
				$query[] = "{$qp}oldid=$oldid";
				self::_debugLog( 30,
					sprintf( '%s():%u: query=%s', __METHOD__, __LINE__, implode( '&', $query ) )
				);
			} else {
				// curid is for persistent URLs to latest revisions of pages before
				// they were moved (renamed)
				$curid = $context->getRequest()->getInt( 'curid' );
				if ( $curid ) {
					// bail if we don't display the toolbox link for current-revision page-ID pages
					if ( !self::$config->pageIdToolLink ) {
						self::_debugLog( 20, __METHOD__ . ': not configured for display on page-ID pages' );
						return;
					}
					$query[] = "${qp}curid=$curid";
					$sendTitle = true;
					self::_debugLog( 30,
						sprintf( '%s():%u: query=%s', __METHOD__, __LINE__, implode( '&', $query ) )
					);
				}
			}
			if ( $urlTitle ) {
				$query[] = "{$qp}title=" . wfUrlencode( $urlTitle );
				self::_debugLog( 30,
					sprintf( '%s():%u: query=%s', __METHOD__, __LINE__, implode( '&', $query ) )
				);
			}
		}

		// pass the action if it's not "view"
		if ( $action !== 'view' ) {
			$query[] = "{$qp}action=$action";
			self::_debugLog( 30,
				sprintf( '%s():%u: query=%s', __METHOD__, __LINE__, implode( '&', $query ) )
			);
		}

		// output the menu item link
		if ( count( $query ) ) {
			echo Html::rawElement( 'li', array( 'id' => 't-numericurl' ),
				Html::Element( 'a',
					array(
						'href' => self::$_path . '?' . implode( '&', $query ),
						'title' => wfMessage( 'numericurl-toolbox-title' )->text(),
						),
					wfMessage( 'numericurl-toolbox-text' )->text()
				)
			);
		}
	}

	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		self::_debugLog( 10, __METHOD__ );
		$updater->addExtensionTable( 'numericurlmap', __DIR__ . '/numericurlmap.sql' );
		return true;
	}

	/** */
	public static function _debugLog( $debugLevel, $msg ) {
		global $wgDebugLogGroups;

		// Keep quiet if this message log level is above the current log level
		if ( $debugLevel > self::$_debugLogLevel ) {
			return;
		}

		// set our log file if it was not configured
		if ( !array_key_exists( self::$_debugLogGroup, $wgDebugLogGroups ) ) {
			$logDir = __DIR__ . "/log";
			if ( !is_writable( $logDir ) ) {
				mkdir( $logDir, 0770 );
				chmod( $logDir, 0770 );
			}
			$wgDebugLogGroups[self::$_debugLogGroup] = "$logDir/debug.log";
		}

		list( $msecs, $secs ) = explode( ' ', microtime( false ) );
		$msecs = explode( '.', $msecs );
		$msecs = $msecs[1];

		wfDebugLog( self::$_debugLogGroup, $msg );

	}

	/** */
	public static function iwLocalInfoFromUrl( $fullUrl ) {
		static $iwLocalUrlsCache = array();
		static $iwLocalPrefixesCache = null;
		if ( isset( $iwLocalUrlsCache[$fullUrl] ) ) {
			return $iwLocalUrlsCache[$fullUrl];
		}
		if ( $iwLocalPrefixesCache === null ) {
			$iwLocalPrefixesCache = Interwiki::getAllPrefixes( true );
		}
		$iwLocalUrlsCache[$fullUrl] = false;
		foreach ( $iwLocalPrefixesCache as $k => $prefix ) {
			$tplParams = self::reverseUrlTemplate( $prefix['iw_url'], $fullUrl );
			if ( $tplParams ) {
				if ( $k !== 0 ) {
					// keep $iwLocalPrefixesCache in MRU order
					unset( $iwLocalPrefixesCache[$k] );
					array_unshift( $iwLocalPrefixesCache, $prefix );
				}
				$iwLocalUrlsCache[$fullUrl] = array(
					'interwiki' => $prefix,
					'title' => $tplParams[1],
				);
				break;
			}
		}
		return $iwLocalUrlsCache[$fullUrl];
	}

	/** */
	public static function titleFromLocalUrl( $localUrl ) {
		$title = null;
		if ( $localUrl->isLocal() ) {
			$id = $localUrl->getQueryValue('oldid');
			if ( !$id ) {
				$id = $localUrl->getQueryValue('curid');
				if ( !$id ) {
					$id = $localUrl->getQueryValue('pageid');
				}
			}
			if ( $id ) {
				$title = Title::newFromID( $id );
			}
			if ( !$title ) {
				$titleText = $localUrl->getQueryValue('title');
				if ( !$titleText ) {
					$parsed = $localUrl->getParsed();
					global $wgArticlePath;
					$urlParams = NumericUrlCommon::reverseUrlTemplate(
						$wgArticlePath,
						$parsed['path']
					);
					if ( isset( $urlParams[1] ) ) {
						$titleText = $urlParams[1];
					}
				}
				// if there's a title in the URL, confirm that it exists on this wiki
				if ( $titleText ) {
					$title = Title::newFromText( $titleText );
					if ( $title && !$title->isKnown() ) {
						$title = null;
					}
				}
			}
		}
		return $title;
	}

	/** */
	private static function _isAllowedAll( $userRightsNames, $user ) {
		foreach( $userRightsNames as $userRightsName ) {
			if ( !self::isAllowed( $userRightsName, $user ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Initialize this class's static properties.
	 * @private
	 *
	 * PHP only allows variable declarations with simple constants, so we have this
	 * function for more complex initialization of statics. Although "public" in
	 * construction, it is usable in this source file, only, immediately after this
	 * class is declared. Any attempt to invoke this method a second time will throw
	 * a WMException.
	 */
	public static function _initStatic() {
		global $wgCanonicalNamespaceNames, $wgArticlePath;
		self::_debugLog( 20, __METHOD__ );
		if ( !self::$config ) {
			global $wgNumericUrl;
			self::$config = Weirdo::objectFromArray( $wgNumericUrl );

		  self::$_specialPageTitle = SpecialPage::getTitleFor( self::SPECIAL_PAGE_TITLE );

			$titleText = "{$wgCanonicalNamespaceNames[NS_SPECIAL]}:" .
				self::$_specialPageTitle->mUrlform;

			self::$_path = str_replace(
				'$1',
				$wgCanonicalNamespaceNames[NS_SPECIAL] . ':' .  self::$_specialPageTitle->mUrlform,
				$wgArticlePath
			);
			self::$_debugLogGroup = 'extension_' . self::EXTENSION_NAME;

			self::$baseUrl = WebRequest::detectServer();
			self::$baseUrlParts = NumericUrlBasicUrl::parse( self::$baseUrl );

			foreach ( self::$config->regions as $k => $v ) {
				if ( !preg_match( '/^[a-z][a-z0-9_]*$/', $k ) ) {
					trigger_error(
						sprintf( '%s: discarding invalid region ID: "%s"', __METHOD__ , $k ),
						E_USER_WARNING );
					continue;
				}
				self::$userRightsNames["follow-region-$k"] = true;
				self::$userRightsNames["create-region-$k"] = true;
			}

		} else {
			throw new WMException( 'Error: Attempt to invoke private method ' . __METHOD__ . '().' );
		}
	}

	/** */
	private static $_specialPageTitle;

	/** */
	private static $_path;

	/** */
	private static $_debugLogGroup;

	/** */
	private static $_defaultUser;

	/** Singleton object to this (otherwise static) class */
	private static $_self;

	/** Flag that indicates singleton has been instantiated */
	private static $_singleton;

	/** */
	private static $_articlePath;

}
// Once-only static initialization
NumericUrlCommon::_initStatic();

// Uncomment the next line to enable debug logging
NumericUrlCommon::$_debugLogLevel = 99;

/** @}*/
