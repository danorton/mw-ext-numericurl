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

	/** Singleton object to this (otherwise static) class */
	public static $self;

	/** */
	public static $baseUrl;

	/** */
	public static $defaultSchemePort = array(
		'http'  => 80,
		'https' => 443,
		'ftp'   => 21,
	);

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
	public static $userRightsNames;

	/** */
	public static function isUrlValid( $url ) {
	}

	/** */
	public static function isUrlLocal( $url ) {
	}

	/** */
	public static function isUrlBasic( $url ) {
	}

	/** */
	public static function isUrlInGroup( $url ) {
	}

	/**
	 * Assemble URL parts into an authority component.
	 *
	 * This puts together what parseUrl() took apart. cf. RFC 3986.
	 */
	public static function authorityFromUrlParts( $urlParts, $defaultPort = null ) {
		$authority = null;

		// We can't have a "relative" authority, so we must have a host, at least.
		if ( isset( $urlParts['host'] ) ) {

			if ( isset( $urlParts['user'] ) ) {
				// user
				$authority .= $urlParts['user'];

				// password
				if ( isset( $urlParts['pass'] ) ) {
					// output the password separator/prefix and the password
					$authority .= ":{$urlParts['pass']}";
				}
				// user:password separator/suffix
				$authority .= '@';
			}

			// host
			$authority .= strtolower( $urlParts['host'] );

			// port
			if ( isset( $urlParts['port'] ) && ( ( (int)$urlParts['port'] ) !== $defaultPort ) ) {
				// output the port separator/prefix and the port
				$authority .= ':' . (int)$urlParts['port'];
			}

		}

		return $authority;
	}

	/**
	 * Assemble URL parts into a string.
	 *
	 * This is like wfAssembleUrl() without the need for 'delimiter'. This puts together
	 * what parseUrl() took apart. cf. RFC 3986.
	 */
	public static function urlFromParts( $urlParts ) {
		$url = '';
		$scheme = '';
		$defaultPort = null;

		// scheme (may be empty)
		if ( isset( $urlParts['scheme'] ) ) {
			// we'll need $scheme later, when assembling the default port
			$scheme = strtolower( $urlParts['scheme'] );
			// output the scheme and the scheme separator/suffix
			$url .= "$scheme:";

			// note the default port for this scheme
			if ( isset( self::$defaultSchemePort[$scheme] ) ) {
				$defaultPort = self::$defaultSchemePort[$scheme];
			}
		}

		// authority
		$authority = self::authorityFromUrlParts( $urlParts, $defaultPort );
		if ( $authority !== null ) {
			// output the authority separator/prefix and the authority
			$url .= "//$authority";
		}

		// path
		if ( isset( $urlParts['path'] ) ) {
			$url .= $urlParts['path'];
		}

		// query
		if ( isset( $urlParts['query'] ) ) {
			// output the query separator/prefix and the query
			$url .= "?{$urlParts['query']}";
		}

		// fragment
		if ( isset( $urlParts['fragment'] ) ) {
			// output the fragment separator/prefix and the fragment
			$url .= "#{$urlParts['fragment']}";
		}

		return $url;
	}

	/**
	 * Disable the class constructor.
	 *
	 * This constructor will throw an error on any attempt to instantiate this class.
	 */
	public function __construct() {
		self::_debugLog( 20, __METHOD__ );
		if ( is_subclass_of( self::$self, __CLASS__ ) ) {
			throw error( new MWException(
				sprintf( 'Error detected by class %s: attempt to instantiate a static-only class', __CLASS__ )
			) );
		}
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
	private static function _isAllowedAll( $userRightsNames, $user ) {
		foreach( $userRightsNames as $userRightsName ) {
			if ( !self::isAllowed( $userRightsName, $user ) ) {
				return false;
			}
		}
		return true;
	}

	/** */
	public static function getFullUserRightsName( $userRightsName ) {
		static $fullUserRightsNames = array();
		if ( !isset( $fullUserRightsNames[$userRightsName] ) ) {
			if ( !isset( self::$userRightsNames[$userRightsName] ) ) {
				trigger_error(
					sprintf( '%s: unrecognized user rights name: "%s"', __METHOD__, $userRightsName ),
					E_USER_WARNING );
				return null;
			}
			$fullUserRightsNames[$userRightsName] = self::EXTENSION_NAME_LC . "-$userRightsName";
		}
		return $fullUserRightsNames[$userRightsName];
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

	/**
	 * Parse a URL per RFC 3986.
	 *
	 * Unlike parse_url(), we don't require a path. Unlike wfParseUrl(), which parses
	 * email URLs as mailto:<user>@<host>, we parse as mailto:<path>.
	 */
	public static function parseUrl( $url ) {
		if ( $url === '' ) {
			$url = './';
		}
		// start with the normal PHP parse_url
		wfSuppressWarnings();
		$urlParts = parse_url( $url );
		wfRestoreWarnings();
		// bail if "seriously malformed"
		if ( !$urlParts ) {
			return false;
		}

		// Like wfParseUrl(), we don't require a scheme
		if ( !isset( $urlParts['scheme'] ) ) {
			// unlike wfParseUrl, we don't require an authority
			if ( substr( $url, 0, 2 ) === '//' ) {
				// If there's an authority without a scheme, add a scheme so that parse_url()
				// will correctly recognize the authority.
				wfSuppressWarnings();
				$urlParts = parse_url( "http:$url" );
				wfRestoreWarnings();
			  // bail if PHP parse_url still can't parse it
			  if ( !$urlParts ) {
					return false;
				}
			}
			$urlParts['scheme'] = null;
		} else {
			// we must always return lower case of scheme
			// cf. RFC 3986, section 6.2.2.1., "Case Normalization"
			$urlParts['scheme'] = strtolower( $urlParts['scheme'] );
		}

		if ( isset( $urlParts['host'] ) ) {
			// we must always return lower case of host
			// cf. RFC 3986, section 6.2.2.1., "Case Normalization"
			$urlParts['host'] = strtolower( $urlParts['host'] );
		} elseif ( $urlParts['scheme']
							&& isset( $urlParts['path'] )
							&& ( substr( $urlParts['path'], 0, 1 ) !== '/' ) ) {
			// Restore the leading '/' from the path if parse_url removed it,
			// believing that it should start with a Microsoft drive letter
			$urlParts['path'] = "/{$urlParts['path']}";
		}

		// remove dot segments from path
		if ( isset( $urlParts['path'] ) ) {
			$urlParts['path'] = self::removeDotSegments( $urlParts['path'] );
		}

		// remove our scheme placeholder if there is no scheme in the URL
		if ( $urlParts['scheme'] === null ) {
			unset( $urlParts['scheme'] );
		}

		return $urlParts;
	}

	/**
	 * Remove dot segments from url path.
	 *
	 * If $eatRelativeDoubleDots is false, preserve leading '..' segments in relative paths.
	 * allowing merges with absolute paths in the way that browsers merge relative href
	 * attributes with a base URL.
	 *
	 * If $eatRelativeDoubleDots is true, this function behaves like the algorithm described
	 * in RFC 3986, which discards leading '..' segments.
	 *
	 */
	public static function removeDotSegments( $urlPath, $eatRelativeDoubleDots = false ) {
		if ( !strlen( $urlPath ) ) {
			return $urlPath;  // degenerate case
		}
		$input = array_reverse( explode( '/', $urlPath ) );
		if ( $urlPath[0] === '/' ) {
			$absPrefix = '/';
			$eatDoubleDots = true;
			array_pop( $input );
		} else {
			$eatDoubleDots = $eatRelativeDoubleDots;
			$absPrefix = '';
		}
		$output = array();
		while( count( $input ) ) {
			$segment = array_pop( $input );
			if ( ( $segment === '..' ) && ( $eatDoubleDots || ( count( $output ) && ( $output[count( $output) - 1] !== '..' ) ) ) ) {
				array_pop( $output );
			} elseif ( $segment !== '.' ) {
				$output[] = $segment;
			}
		}
		return $absPrefix . implode( '/', $output );
	}

	/**
	 * Get fully qualified URL parts from the given (possibly relative) URL.
	 *
	 * TODO: reduce dots in paths
	 */
	public static function fullUrlFromUrl( $url, $urlParts = null ) {
		if ( $urlParts === null ) {
			$urlParts = self::parseUrl( $url );
		}
		if ( $urlParts === false ) {
			return false;
		}

		if ( !isset( $urlParts['scheme'] ) ) {
		}

	}

	/**
	 * Is the target URL, at the very least, valid?
	 */
	public static function isValidTargetUrl( $url, $urlParts = null ) {
		if ( $urlParts === null ) {
			$urlParts = self::parseUrl( $url );
		}
		if ( $urlParts === false ) {
			return false;
		}
	}

	public static function scopeFromUrl( $url, $urlParts = null ) {
		if ( $urlParts === null ) {
			$urlParts = self::parseUrl( $url );
		}
		if ( $urlParts === false ) {
			return false;
		}
		// first, check the authority part to see if it's
		if ( isset( $urlParts['scheme'] ) ) {
		}
		// this is a local URL; see if it has basic URL query parameters, only
		if ( isset( $urlParts['fragment'] ) ) {
			return self::URL_SCOPE_LOCAL;
		}
		return self::URL_SCOPE_BASIC;
	}

	/**
	 * Display a link to our basic creation tool in the toolbox for certain pages.
	 */
	public static function onSkinTemplateToolboxEnd( $tpl ) {
		self::_debugLog( 20, __METHOD__ );

		if ( !self::isAllowed( 'follow' ) ) {
			return;
		}

		$context = $tpl->getSkin()->getContext();

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
		$subPath = array();

		$urlTitle = $title->getPrefixedUrl();
		if ( $urlTitle ) {
			// see if the configuration rules out this title
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
			$subPath[] = "title=$urlTitle";
			self::_debugLog( 30,
				sprintf( '%s():%u: query=%s', __METHOD__, __LINE__, implode( '&', $subPath ) )
			);
		}

		// pass the specific page ID and/or revision, if specified
		$articleId = $title->getArticleID();
		if ( $articleId ) {
			$subPath[] = "pageid=$articleId";
			self::_debugLog( 30,
				sprintf( '%s():%u: query=%s', __METHOD__, __LINE__, implode( '&', $subPath ) )
			);
			// oldid is for persistent URLs to specific revisions of pages
			$oldid = Article::newFromID( $articleId )->getOldID();
			if ( $oldid ) {
				// bail if we don't display the toolbox link for revision pages
				if ( !self::$config->revisionToolLink ) {
					self::_debugLog( 20, __METHOD__ . ': not configured for display on specific revisions' );
					return;
				}
				$subPath[] = "oldid=$oldid";
				self::_debugLog( 30,
					sprintf( '%s():%u: query=%s', __METHOD__, __LINE__, implode( '&', $subPath ) )
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
					$subPath[] = "curid=$curid";
					self::_debugLog( 30,
						sprintf( '%s():%u: query=%s', __METHOD__, __LINE__, implode( '&', $subPath ) )
					);
				}
			}
		}

		// pass the action if it's not "view"
		if ( $action !== 'view' ) {
			$subPath[] = "action=$action";
			self::_debugLog( 30,
				sprintf( '%s():%u: query=%s', __METHOD__, __LINE__, implode( '&', $subPath ) )
			);
		}

		// output the menu item link
		if ( count( $subPath ) ) {
			echo Html::rawElement( 'li', array( 'id' => 't-numericurl' ),
				Html::Element( 'a',
					array(
						'href' => self::$_path . '?'
							. self::$config->toolPageQueryParam . '=' . rawurlencode( implode( '&', $subPath )),
						'title' => wfMessage( 'numericurl-toolbox-title' )->text(),
						),
					wfMessage( 'numericurl-toolbox-text' )->text()
				)
			);
		}
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

	/** Recursively convert an array to an object */
	public static function objectFromArray( $array ) {
		$o = (object) null;
		foreach ( $array as $k => $v ) {
			// replace null array key with a valid object field name
			if ( $k === '' ) {
				$k = '_null_' . __FUNCTION__;
			}
			if ( is_array( $v ) ) {
				$o->{$k} = self::objectFromArray( $v ); // recurse on arrays
			} else {
				$o->{$k} = $v;
			}
		}
		return $o;
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
		if ( !self::$self ) {
			self::$self = new self;

			global $wgNumericUrl;
			self::$config = self::objectFromArray( $wgNumericUrl );

		  self::$_specialPageTitle = SpecialPage::getTitleFor( self::SPECIAL_PAGE_TITLE );

			$titleText = "{$wgCanonicalNamespaceNames[NS_SPECIAL]}:" .
				self::$_specialPageTitle->mUrlform;

			self::$_path = str_replace(
				'$1',
				$wgCanonicalNamespaceNames[NS_SPECIAL] . ':' .  self::$_specialPageTitle->mUrlform,
				$wgArticlePath
			);
			self::$_debugLogGroup = 'extension_' . self::EXTENSION_NAME;

			global $wgServer;
			self::$baseUrl = $wgServer;
			self::$baseScheme =  WebRequest::detectProtocol();
			// specify the protocol that loaded us if our server is not hard-configured
			if ( substr( self::$baseUrl, 0, 2 ) === '//' ) {
				self::$baseUrl = WebRequest::detectProtocol() . ':' . self::$baseUrl;
				self::$baseScheme = self::URL_SCHEME_FOLLOW;
			}

			// flip the user rights array for faster key access
			self::$userRightsNames = array_flip( self::$_userRightsNamesFlipped );

		}
		else {
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
	private static $_userRightsNamesFlipped = array(
		'follow',
		'create-basic',
		'create-group',
		'create-global',
		'create-notrack',
		'create-password',
		'create-noexpire',
	);

	/** */
	private static $_defaultUser;

	/** */
	//const _URL_SCHEME_REGEX = '"^(([a-z][a-z.+-]{1,32}:)?//)?(.*)$"';

}
// Once-only static initialization
NumericUrlCommon::_initStatic();

// Uncomment the next line to enable debug logging
NumericUrlCommon::$_debugLogLevel = 99;

/** @}*/
