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
	const EXTENSION_NAME = MW_EXT_NUMERICURL_NAME;

	/** */
	const SPECIAL_PAGE_TITLE = MW_EXT_NUMERICURL_NAME;
  
  /** */
  const URL_SCHEME_HTTP = 'http';

  /** */
  const URL_SCHEME_HTTPS = 'https';

  /** */
  const URL_SCHEME_FOLLOW = '//';
  
  /** */
  public static $mToolQueryParam = 'nuquery';

	/** Extension configuration; from global $wgNumericUrl */
	public static $mConfig;

	/** Singleton object to this (otherwise static) class */
	public static $self;

  /** */
  public static $mBaseUrl;

  /** */
  public static $mBaseScheme;
  
  /** Subset of page actions for which we show the toolbox link. */
  public static $mToolboxActions = array( 'view',
    // 'cached', 'credits', 'edit', 'history',
    // 'info', 'purge', 'watch',
    );

	/** If to display the toolbox link on specific revision pages. */
	public static $mToolboxOnRevisions = true; //false;

	/** If to display the toolbox link on specific page ID pages. */
	public static $mToolboxOnPageId = true; // false;

	/** */
	public static $_debugLogLevel = 0;

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
	public static function onWebRequestPathInfoRouter( $pathRouter ) {
		self::_debugLog( 20, __METHOD__ );
		if ( self::$mConfig->template ) {
			$pathRouter->add( self::$mConfig->template,
				array( 'title' => self::$_specialPageTitle->getPrefixedText() )
			);
		}
	}
	
  /**
   * Build a full URL from a relative URL
   *
   * See RFC 3986, section 5.2, "Relative Resolution"
   *
   */
  /* ///
	public static function fullUrlFromRelativeUrl( $relativeUrl, $baseUrl = null ) {
    // Use our own server's base URL as the default template
    if ( $baseUrl === null ) {
      global $wgServer;
      $baseUrl = $wgServer ;
    }
    // if the base scheme is missing or is '//', make it "https" for parse_url processing
    $baseScheme = preg_replace( self::_URL_SCHEME_REGEX, '$1', $baseUrl );
    if ( ( $baseScheme === '' ) || ( $baseScheme === '//' ) {
      $baseUrl = 'https://' . preg_replace( self::_URL_SCHEME_REGEX, '$1', $baseUrl );
    }
    $baseScheme = preg_replace( self::_URL_SCHEME_REGEX, '$1', $baseUrl );
    if ( $baseScheme === '' ) {
      $baseScheme = '//';
    }
    if ( $baseScheme === '//' ) {
      $baseParts = parse_url( 'http://' . preg_replace( self::_URL_SCHEME_REGEX, '$3', $baseUrl ) );
      unset ( $baseParts['scheme'] );
    } else {
      $baseParts = parse_url( $baseScheme . preg_replace( self::_URL_SCHEME_REGEX, '$3', $baseUrl ) );
    }

    $relativeScheme = preg_replace( self::_URL_SCHEME_REGEX, '$1', $relativeUrl );
    if ( $relativeScheme === '' ) {
      // see if the relative URL starts with a host 
      if ( isset( $relativeUrl[1] ) && ( $relativeUrl[1] === '/') && ( $relativeUrl[0] === '/' )
      $relativeScheme = '//';
 
      $relativeUrl = $relativeScheme . preg_replace( self::_URL_SCHEME_REGEX, '$3', $relativeUrl ) ;
    }

		$fullUrl = 
				preg_replace( self::_URL_SCHEME_REGEX, '$1', $url )
			. preg_replace( self::_URL_SCHEME_REGEX, '$3', $url ) ;
	}
  //*///

	/**
   * Display a link in the toolbox for certain pages
   */
	public static function onSkinTemplateToolboxEnd( $tpl ) {
		self::_debugLog( 20, __METHOD__ );
    
		$context = $tpl->getSkin()->getContext();

		$action = Action::getActionName( $context );
		self::_debugLog( 30,
			sprintf( '%s(): tpl=%s; skin=%s; context=%s; action=%s', __METHOD__,
				get_class( $tpl ),
				get_class( $tpl->getSkin() ),
				get_class( $context ),
				$action
			)
		);
 
		// skip this unless specifically configured for the current action
		if ( !in_array( $action, self::$mToolboxActions ) ) {
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
 
		// pass the title
    $urlTitle = $title->getPrefixedUrl();
    if ( $urlTitle ) {
      $subPath[] = 'title=' . $title->getPrefixedUrl();
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
      $oldid = Article::newFromID( $articleId)->getOldID();
      if ( $oldid ) {
        // bail if we don't display the toolbox link for revision pages
        if ( !self::$mToolboxOnRevisions ) {
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
          if ( !self::$mToolboxOnPageId ) {
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
              . self::$mToolQueryParam . '=' . rawurlencode(implode( '&', $subPath )),
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
			self::$mConfig = self::objectFromArray( $wgNumericUrl );

		  self::$_specialPageTitle = SpecialPage::getTitleFor( MW_EXT_NUMERICURL_NAME );

			$titleText = $wgCanonicalNamespaceNames[NS_SPECIAL] . ':' .
				self::$_specialPageTitle->mUrlform;

			self::$_path = preg_replace( '/^(.*)$/', $wgArticlePath,
				$wgCanonicalNamespaceNames[NS_SPECIAL] . ':' .  self::$_specialPageTitle->mUrlform

			);
			self::$_debugLogGroup = 'extension_' . self::EXTENSION_NAME;
      
      global $wgServer;
      self::$mBaseUrl = $wgServer;
      self::$mBaseScheme =  WebRequest::detectProtocol();
      // specify the protocol that loaded us if our server is not hard-configured
      if ( substr( self::$mBaseUrl, 0, 2 ) === '//' ) {
        self::$mBaseUrl = WebRequest::detectProtocol() . ':' . self::$mBaseUrl;
        self::$mBaseScheme = self::URL_SCHEME_FOLLOW;
      }
 
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
	//const _URL_SCHEME_REGEX = '"^(([a-z][a-z.+-]{1,32}:)?//)?(.*)$"';

}
// Once-only static initialization
NumericUrlCommon::_initStatic();

// Uncomment the next line to enable debug logging
NumericUrlCommon::$_debugLogLevel = 99;

/** @}*/
