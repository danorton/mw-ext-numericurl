<?php

if ( !defined( 'MW_EXT_NUMERICURL_NAME' ) ) {
	echo "This file is an extension to MediaWiki software and is not designed for standalone use.\n";
	die ( 1 );
}

require_once __DIR__ . '/NumericUrlBasicUrl.php';

class NumericUrlMapInstance extends NumericUrlBasicUrl {

	/** */
	public $source;

	/** */
	public static $localServer;

	/**
	 * This is possibly blank, for servers that allow connection via both http and https.
	 */
	public static $localServerScheme;

	/** */
	public function __construct( $urlOrParts = null ) {
		parent::__construct( $urlOrParts );

		if ( self::$localServer === null ) {
			global $wgServer;
			self::$localServer = new NumericUrlBasicUrl( $wgServer ) ;
			$serverParsed = self::$localServer->getParsed();
			self::$localServerScheme = isset ( $serverParsed['scheme'] ) ? $serverParsed['scheme'] : null;
		}

	}

	/** */
	public function __clone() {
		// placeholder (so far, clone works fine without doing anything here)
	}

	/** */
	public function isValid() {
		if ( $this->_isValid !== null ) {
			return $this->_isValid;
		}
		$this->_isValid = false;
		if ( !$this->getValidity() ) {
			return false;
		}
		// $this->_parsed is defined at this point

		// we don't allow URLs with passwords
		if ( isset( $this->_parsed['pass'] ) ) {
			return false;
		}

		// The path must exist and be absolute
		if ( ( !isset( $this->_parsed['path'][0] ) ) || ( $this->_parsed['path'][0] !== '/' ) ) {
			return false;
		}

		$isValid = true;
		// See if any hooks consider it invalid
		if ( Hooks::isRegistered( 'NumericUrlValidityCheck' ) ) {
			wfRunHooks(
				'NumericUrlValidityCheck',
				array(
					&$isValid,
					$this->getText(),
					$this->_parsed,
					$this->getAuthority(),
					$this,
				)
			);
		}
		$this->_isValid = (bool)$isValid;
		return $this->_isValid;
	}

	/**
	 * Make the URL absolute (if it isn't already)
	 *
	 * @returns  bool @a true if the URL is now an absolute URL.
	 */
	public function makeAbsolute() {
		if ( $this->_absoluteUrl !== null ) {
			return $this->_absoluteUrl;
		}
		$this->_absoluteUrl = false;
		$validity = $this->getValidity();
		if ( !$validity ) {
			return false;
		}
		if ( $this->hasAuthority() ) {
			$this->_absoluteUrl = true;
			if ( !isset( $this->_parsed['path'] ) ) {
				$this->_parsed['path'] = '/';
				$this->_text = null;
			}
			return true;
		}
		$absoluteUrl = $this->createMerged( $this->localServer );
		if ( !$absoluteUrl ) {
			return false;
		}
		$validity = $this->getValidity();
		$this->_parsed = $absoluteUrl->getParsed();
		$this->_text = null;
		$this->_validity = $validity;
		$this->_authority = $absoluteUrl->getAuthority();
		$this->_hasAuthority = ( $this->_authority !== null );
		$this->_absoluteUrl = true;
		return true;
	}

	/**
	 * Indicate if the user is allowed the specified action on this URL.
	 *
	 * Actions here are limited to 'follow' and 'create'.
	 */
	public function isAllowed( $urlAction, $user = null ) {
		if ( $user === null ) {
			$user = RequestContext::getMain()->getUser();
		}
		$userId = $user->getId();
		if ( isset( $this->_isAllowedCache[$urlAction][$userId] ) ) {
			return $this->_isAllowedCache[$urlAction][$userId] ;
		}
		if ( !isset( $this->_isAllowedCache[$urlAction] ) ) {
			// unrecognized action
			return false;
		}

		$this->_isAllowedCache[$urlAction][$userId] = false;
		// user must have at least 'follow-shared'
		if ( !NumericUrlCommon::isAllowed( 'follow-shared', $user ) ) {
			return false;
		}

		if ( $urlAction === 'follow' ) {
			if ( $this->isBasic() ) {
				$this->_isAllowedCache[$urlAction][$userId] = true;
			} else {
				// user must have at least 'follow-local'
				if ( !NumericUrlCommon::isAllowed( 'follow-local', $user ) ) {
					return false;
				}
				if ( $this->isLocal() ) {
					$this->_isAllowedCache[$urlAction][$userId] = true;
				} elseif ( NumericUrlCommon::isAllowed( 'follow-global', $user ) ) {
					$this->_isAllowedCache[$urlAction][$userId] = true;
				} else {
				}
			}
		} else {
			// to grant 'create', user must have 'follow'
			if ( !$this->isAllowed( 'follow', $user ) ) {
				return false;
			}
		}

		return true;
	}

	/** */
	public function getRegionsInfo() {
		if ( $this->_regionsInfo === null ) {
			$this->_regionsInfo = array();
			wfRunHooks(
				'NumericUrlRegionCheck',
				array(
					&$this->_regionsInfo,
					$this->getText(),
					$this->getParsed(),
					$this->getAuthority(),
					$this,
				)
			);
			// validate the regions for this URL
			foreach ( $this->_regionsInfo as $regionId => $regionInfo ) {
				//if ( !preg_match( '/^[a-z][a-z0-9_]*$/', $regionId ) ) {
				if ( !isset( NumericUrlCommon::$config->regions->{$regionId} ) ) {
					trigger_error(
						sprintf( '%s: rejected unknown region "%s"', __METHOD__, $regionId ),
						E_USER_WARNING);
					// remove the invalid region
					unset( $this->_regionsInfo[$regionId] );
				} elseif ( !isset( $this->_regionsInfo[$regionId]->{'description-message'} ) ) {
					trigger_error(
						sprintf( '%s: missing region descripton; rejected region "%s"', __METHOD__, $regionId ),
						E_USER_WARNING);
					// remove the invalid region
					unset( $this->_regionsInfo[$regionId] );
				}
			}
		}
		return $this->_regionsInfo;
	}

	/** */
	public function getAllowedRegions( $user = null ) {
		if ( $user === null ) {
			$user = RequestContext::getMain()->getUser();
		}
		$userId = $user->getId();
		if ( !isset( $this->_allowedRegionsCache[$userId] ) ) {
			$this->_allowedRegionsCache[$userId] = array( 'follow' => array(), 'create' => array() );
			$basicAllowed = array(
				'follow' => NumericUrlCommon::isAllowed( 'follow-shared', $user ),
			);
			// A user without follow-shared rights can't have any region rights
			if ( !$basicAllowed['follow'] ) {
				return $this->_allowedRegionsCache[$userId];
			}
			$basicAllowed['create'] = NumericUrlCommon::isAllowed( 'create-basic', $user );
			$globalAllowed = array(
				'follow' => NumericUrlCommon::isAllowed( 'follow-global', $user ),
				'create' => $basicAllowed['create'] && NumericUrlCommon::isAllowed( 'create-global', $user ),
			);
			foreach ( array( 'follow', 'create' ) as $perm ) {
				$this->_allowedRegionsCache[$userId][$perm] = array();
				foreach ( $this->getRegionsInfo() as $region => $info ) {
					if ( $basicAllowed[$perm]
					 && ( $globalAllowed[$perm]
								|| NumericUrlCommon::isAllowed( "$perm-region-$region", $user  ) )
					 && ( ( $perm === 'follow' )
							 || isset( $this->_allowedRegionsCache[$userId]['follow'][$region] ) )
					) {
						$this->_allowedRegionsCache[$userId][$perm][$region] = true;
					}
				}
			}
		}
		return $this->_allowedRegionsCache[$userId];
	}

	/** */
	public function isLocal() {
		if ( $this->_isLocal !== null ) {
			return $this->_isLocal;
		}
		$this->_isLocal = false;

		if ( !$this->getValidity() ) {
			return false;
		}

		// if there's an authority and it doesn't match this server's authority, then it's not local
		if ( $this->hasAuthority() && !$this->hasSameAuthority( self::$localServer ) ) {
			return false;
		}

		$parsed = $this->getParsed();
		// If on the same server and if this scheme contradicts the local scheme, it's not local
		if ( self::$localServerScheme && isset( $parsed['scheme'] ) && ( self::$localServerScheme !== $parsed['scheme'] ) ) {
			return false;
		}

		$this->_isLocal = true;
		return true;
	}

	public function setPrivacy( $user ) {
		if ( false ) {
			$this->_isPrivate = true;
		}
	}

	public function getPrivacy() {
		return (bool)$this->_isPrivate;
	}

	public function getRegionInfo( $regionId ) {
		$regionsInfo = $this->getRegionsInfo();
		if ( isset( $regionsInfo[$regionId] ) ) {
			return $regionsInfo[$regionId];
		}
		return null;
	}

	public function isGlobal() {
	}

	protected function _reset() {
		parent::_reset();
		$this->_isLocal = null;
		$this->_isValid = null;
		$this->_regionsInfo = null;
		$this->_regionsAllowed = null;
		$this->_absoluteUrl = null;
		$this->_allowedRegionsCache = array();
		$this->_isPrivate = null;
	}

	/** */
	private $_isValid;

	/** */
	private $_isLocal;

	/** */
	private $_regionsInfo;

	/** */
	private $_allowedRegionsCache = array();

	/** */
	private $_isPrivate;

	/** */
	private $_isAllowedCache = array( 'follow' => array(), 'create' => array() );

}
