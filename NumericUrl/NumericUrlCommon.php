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

	/** Extension configuration; from global $wgNumericUrl */
	public static $mConfig;

	/** Singleton object to this (otherwise static) class */
	public static $self;

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

	/** */
	public static function onSkinTemplateToolboxEnd( $tpl ) {
		self::_debugLog( 20, __METHOD__ );

		$context = $tpl->getSkin()->getContext();

		// don't display the tool unless the action is known to be 'safe'
		$action = Action::getActionName( $context );
		self::_debugLog( 30,
			sprintf( '%s(): tpl=%s; skin=%s; context=%s; action=%s', __METHOD__,
				get_class( $tpl ),
				get_class( $tpl->getSkin() ),
				get_class( $context ),
				$action
			)
		);
		if ( !in_array( $action, array(
				'cached', 'credits', 'edit', 'history',
				'info', 'purge', 'view', 'watch',
				) ) ) {
			return;
		}

		// don't show the tool on error pages
		$out = $context->getOutput();
		$okStatus = ( ( $out->mStatusCode == '' ) || ( ( $out->mStatusCode / 100 ) == 2 ) );
		if ( !$okStatus ) {
			return;
		}

		// pass the current query parameters to the tool, if invoked
		// pass the title
		$title = $context->getTitle();
		$subPath = array();
		$subPath[] = 'title=' . $title->getPrefixedUrl();

		// pass the revision, if we're looking at a specific revision
		$curid = $title->getArticleID();
		if ( $curid ) {
			$subPath[] = "curid=$curid";
			$oldid = Article::newFromID( $curid )->getOldID();
			if ( $oldid ) {
				$subPath[] = "oldid=$oldid";
			}
		}

		// pass the action if it's not "view"
		if ( $action != 'view' ) {
			$subPath[] = "action=$action";
		}

		// output the menu item link
		echo Html::rawElement( 'li', array( 'id' => 't-numericurl' ),
			Html::Element( 'a',
				array(
					'href' => self::$_path . '?nuquery=' . rawurlencode(implode( '&', $subPath )),
					'title' => wfMessage( 'numericurl-toolbox-title' )->text(),
					),
				wfMessage( 'numericurl-toolbox-text' )->text()
			)
		);
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
	const _URL_SCHEME_REGEX = '"^(([a-z][a-z.+-]{1,32}:)?//)?(.*)$"';

}
// Once-only static initialization
NumericUrlCommon::_initStatic();

// Uncomment the next line to enable debug logging
NumericUrlCommon::$_debugLogLevel = 99;

/** @}*/
