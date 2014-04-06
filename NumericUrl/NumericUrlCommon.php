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
	const URL_CLASS_XXXXX   = 0x0004;

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

	/**
   * These are the fixed permission names.
   *
   * This list does not include region permission names.
   */
	public static $userRightsNames = array(
		'view-basic' => true,
		'view-local' => true,
		'view-global' => true,
    'view-any' => true,

		'create-basic' => true,
		'create-local' => true,
		'create-group' => true,
		'create-global' => true,

		'create-notrack' => true,
		'create-password' => true,
		'create-noexpire' => true,
	);

  /** */
  public static function getCurrentUser() {
    return self::$_currentUser;
  }
  
  /** */
  public static function getCurrentUserId() {
    return self::$_currentUserId;
  }
  
	/** */
	public static function isAllowed( $userRightsName, $user = null ) {
		if ( $user === null ) {
			$user = self::$_currentUser;
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
		NUDBG && self::_debugLog( 20, __METHOD__ );
		if ( self::$config->template ) {
			$pathRouter->add( self::$config->template,
				array( 'title' => self::$_specialPageTitle->getPrefixedText() )
			);
		}
	}

	/** */
  //*///
	public static function onNumericUrlRegionCheck(
		&$regions,
		$urlText,
		$urlParts,
		$urlAuthority,
		$urlMapInstance
  ) {

		// don't do anything if iw_local has been removed from the list of regions
		if ( !isset( self::$config->regions->iw_local ) ) {
			return true;
		}

		NUDBG && self::_debugLog( 20,
			sprintf( '%s: url=\"%s\"; authority=\"%s\"', __METHOD__ , $urlText, $urlAuthority ),
			E_USER_WARNING );

		if ( self::iwLocalInfoFromUrl( $urlText ) ) {
			$regions['iw_local'] = self::$config->regions->iw_local;
		}
		return true;
	}
  //*///

	/**
   * Get the parameters that created the specified URL from the specified template.
   */
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
	 * Given a fully qualified URL, determine its scope.
	 */
  /*///
	public static function scopeFromUrl( $urlOrParts ) {
		NUDBG && self::_debugLog( 20, __METHOD__ );
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
  //*///

	/**
	 * Determine whether our tool link belongs on the current page and, if so, output the link.
	 */
	public static function onSkinTemplateToolboxEnd( $tpl ) {
		$qp = self::$config->queryPrefix;
		NUDBG&&self::_debugLog( 20, __METHOD__ );

		$context = $tpl->getSkin()->getContext();
		$title = $context->getTitle();

    // rule out pages that might be valid mappings, but we don't display the tool link for them
		// skip this on error pages (which might not have always produced an error)
		$outStatus = $context->getOutput()->mStatusCode;
		if ( ( $outStatus !== '' ) && ( ( $outStatus / 100 ) != 2 ) ) {
			self::_debugLog( 20, __METHOD__ . ': not displayed for error pages' );
			return;
		}
 
    // skip if the mainpage (this is a redirector configuration parameter)
    if ( $title->isMainPage() ) {
			self::_debugLog( 20, __METHOD__ . ': not displayed for mainpage' );
      return;
    }

    // Skip for redirect pages (might not always have been a redirect)
		if ( $title->isRedirect() ) {
			self::_debugLog( 20, __METHOD__ . ': not displayed for redirect pages' );
			return;
		}

		$urlMapInstance = newFromTitle( $title );
 
    // skip if the current user isn't allowed to view a map to this page
    if ( !$urlMapInstance->isAllowed( 'view', $user ) ) {
      return;
    }

		// check if any hooks want to suppress the toolbox link here
    $disable = false;
    wfRunHooks(
      'NumericUrlToolboxCheck',
      array(
        &$disable,
        $title,
        $urlMapInstance,
      )
    );
    if ( $disable ) {
      return;
    }

		// output the menu item link
    echo Html::rawElement( 'li', array( 'id' => 't-numericurl' ),
      Html::Element( 'a',
        array(
          'href' => wfAppendQuery( self::$_path, "{$qp}url=" . wfUrlencode( $urlMapInstance ) ),
          'title' => wfMessage( 'numericurl-toolbox-title' )->text(),
          ),
        wfMessage( 'numericurl-toolbox-text' )->text()
      )
    );
	}

  /**
   * Create/update our DB schema.
   *
   * This is invoked by maintenance/update.php
   */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		self::_debugLog( 10, __METHOD__ );
		$updater->addExtensionTable( 'numericurlmap', __DIR__ . '/numericurlmap.sql' );
		$updater->addExtensionTable( 'numericurlregions', __DIR__ . '/numericurlregions.sql' );
		return true;
	}

  /**
   * @todo - move this to a debug class
   */
  public static function isDebugLogLevel( $messageLevel ) {
    return $messageLevel <= self::$_debugLogLevel;
  }
  
  /** */
  public static function setDebugLogLevel( $newLevel ) {
    if ( $newLevel === self::$_debugLogLevel ) {
      // no-op
      return;
    }
    $oldLevel = self::$_debugLogLevel;
    if ( $newLevel !== null ) {
      self::_debugLog( 1,
        sprintf('%s: Logging level changing from %u to %u', __METHOD__, $oldLevel, $newLevel )
      );
      self::$_debugLogLevel = $newLevel;
    }
    return $oldLevel;
  }
 
	/** */
	public static function _debugLog( $messageLevel, $msg ) {
    if ( defined( 'NUDBG' ) && !NUDBG ) return;
		// Keep quiet if this message's log level is above the current log level
		if ( !self::isDebugLogLevel( $messageLevel ) ) {
			return;
		}
    
		global $wgDebugLogGroups;
		// set and create our log file
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

	/**
   * 
   */
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
  /*///
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
  //*///

	/**
   * Indicate if the user is allowed *all* of the specified rights.
   */
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
		NUDBG&&self::_debugLog( 20, __METHOD__ );
		if ( !self::$config ) {
    
      // Get our configuration object
			global $wgNumericUrl;
			self::$config = Weirdo::objectFromArray( $wgNumericUrl );
      
      self::$_currentUser = RequestContext::getMain()->getUser();
      self::$_currentUserId = self::$_currentUser->getId();
      
      // Enumerate all permissions that we support
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
				self::$userRightsNames["view-region-$k"] = true;
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

	/** Singleton object to this (otherwise static) class */
	private static $_self;

	/** Flag that indicates singleton has been instantiated */
	private static $_singleton;

	/** */
	private static $_articlePath;
  
  /**
   * Subset of $wgActions that we support.
   *
   * Combine this with a region (below) to get a permission tag.
   */
  private static $_actions = array(
    'create' => 1, // create a mapping
    'delete' => 1, // n.b. an extremely rare action! (even then, it only sets a flag)
    'edit'   => 1, // can't change long URL, just various attributes
    'view'   => 1, // view and/or follow the redirect
  );
  
  /**
   * URL regions
   *
   * These map to permissions, e.g. "numericurl-create-$region"
   */
  private static $_regions = array(
    'basic'  => 1,        // a "basic" page on this wiki (configurable)
    // 'local-*'  <-- hooked local regions go here
    'local'  => 1,        // other URLs on this site not in a hooked region
    // 'global-*' <-- hooked global regions go here
    'global' => 1, // other URLs anywhere else not in a hooked region
    'any'    => 1,          // grants permission to any URL
  );
  
  /** */
  private static $_currentUser;

}
// Once-only static initialization
NumericUrlCommon::_initStatic();

// Uncomment the next line to enable debug logging
NumericUrlCommon::setDebugLogLevel( 99 );

// Uncomment the next line if using NUDBG
define('NUDBG', NumericUrlCommon::$_debugLogLevel );
/** @}*/
