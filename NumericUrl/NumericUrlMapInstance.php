<?php

if ( !defined( 'MW_EXT_NUMERICURL_NAME' ) ) {
	echo "This file is an extension to MediaWiki software and is not designed for standalone use.\n";
	die ( 1 );
}

require_once __DIR__ . '/NumericUrlBasicUrl.php';

class NumericUrlMapInstance extends NumericUrlBasicUrl {

	/** */
	public function __construct( $urlOrParts = null ) {
		parent::__construct( $urlOrParts );
	}

	/** */
	public function init( $urlOrParts ) {
		parent::init( $urlOrParts );

		global $wgServer;
		self::$_localServer = new NumericUrlBasicUrl( $wgServer );
		$serverParsed = self::$_localServer->getParsed();
		self::$_localServerScheme = isset( $serverParsed['scheme'] ) ? $serverParsed['scheme'] : null;
	}

	/** */
	public function __clone() {
		// placeholder (so far, clone works fine without doing anything here)
	}

	/** */
	public static function newFromUrl( $url ) {
		return new self( $url );
	}

	/** */
	public static function newFromParsed( $parsed ) {
		return new self( $parsed );
	}

	/** */
	public static function newFromKey( $key ) {
		$o = new self();
		return $o->load( $key ) ? $o : false;
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
		$parsed = $this->getParsed();

		// we don't allow URLs with passwords
		if ( isset( $parsed['pass'] ) && !NumericUrlCommon::$config->allowPasswordInUrl ) {
			return false;
		}

		// The path must exist and must be absolute
		if ( ( !isset( $parsed['path'][0] ) ) || ( $parsed['path'][0] !== '/' ) ) {
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
					$parsed,
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
		if ( $this->_isAbsolute !== null ) {
			return $this->_isAbsolute;
		}
		$this->_isAbsolute = false;
		$validity = $this->getValidity();
		if ( !$validity ) {
			return false;
		}
		if ( $this->hasAuthority() ) {
			$this->_isAbsolute = true;
			if ( !isset( $this->_parsed['path'] ) ) {
				$this->_parsed['path'] = '/';
				$this->_text = null;
			}
			return true;
		}
		$absoluteUrl = $this->createMerged( self::$_localServer );
		if ( !$absoluteUrl ) {
			return false;
		}
		$validity = $this->getValidity();
		$this->_parsed = $absoluteUrl->getParsed();
		$this->_text = null;
		$this->_validity = $validity;
		$this->_authority = $absoluteUrl->getAuthority();
		$this->_hasAuthority = ( $this->_authority !== null );
		$this->_isAbsolute = true;
		return true;
	}

	/**
	 * Indicate if the user is allowed the specified action on this URL.
	 */
	public function isActionAllowed( $action, $user = null ) {
		if ( $user === null ) {
			$user = NumericUrlCommon::getCurrentUser();
		}
    // initialize the cache
		if ( !$this->_isAllowedCache ) {
      $this->_isAllowedCache = self::$_actions;
		}
		$userId = $user->getId();
    
    // first, check for cached response
		if ( isset( $this->_isAllowedCache[$action][$userId] ) ) {
			return $this->_isAllowedCache[$action][$userId];
		}
		if ( !isset( $this->_isAllowedCache[$action] ) ) {
			// unrecognized action
			return false;
		}

    // confirm user has 'view' access before testing anything else
    if ( $action !== 'view' ) {
      if ( !isset( $this->_isAllowedCache['view'][$userId] ) ) {
        // recurse
        $this->isActionAllowed( 'view', $user );
      }
      // if 'view' isn't allowed, no other action is allowed
      if ( !$this->_isAllowedCache['view'][$userId] ) {
        $this->_isAllowedCache[$action][$userId] = false;
        return false;
      }
    }
 
		// shortcut out if this user has 'all' regions privilege
		if ( NumericUrlCommon::isAllowed( "$action-all", $user ) ) {
			$this->_isAllowedCache[$action][$userId] = true;
			return true;
		}
    
    if ( $this->isLocal() ) {
      // check local region hooks for access
      $localRegions = $this->_getRegions( 'local' );
      if ( count( $localRegions ) ) {
        // user must be allowed access to *all* matching regions
        foreach ( $localRegions as $localRegion ) {
          // denial in any region immediately fails the test
          if ( $this->_isUserDeniedActionInRegion( $user, $userId, $action, "local-$localRegion" )
          ) {
            return false;
          }
        }
        // All matches allowed. Cache and report access allowed.
        $this->_isAllowedCache['view'][$userId] = true;
        if ( $action !== 'view' ) {
          $this->_isAllowedCache[$action][$userId] = true;
        }
        return true;
      }
      // The URL didn't match any local region. See if other local access denied
      if ( $this->_isUserDeniedActionInRegion( $user, $userId, $action, 'local' ) {
        return false;
      }
      $this->_isAllowedCache['view'][$userId] = true;
      if ( $action !== 'view' ) {
        $this->_isAllowedCache[$action][$userId] = true;
      }
      return true;
    }

    // If this is a Basic URL, basic
    if ( $this->isBasic() ) {
      if ( $this->_isUserDeniedActionInRegion( $user, $userId, $action, 'basic' ) ) {
        return false;
      }
      $this->_isAllowedCache['view'][$userId] = true;
      if ( $action !== 'view' ) {
        $this->_isAllowedCache[$action][$userId] = true;
      }
      return true;
    }

    // process non-local regions
    $this->makeAbsolute();
		// check hooks for global regions
		foreach ( $this->_getRegions( 'global' ) as $globalRegion ) {
			$region = "global-$globalRegion";
			...
		}

		// everything else is a global URL
		$region = 'global'
		...

		return true;
	}

	/** */
	public function cloneLocalPart() {
		$parsed = $this->getParsed();
		$localPart = clone $this;
		unset( $parsed['scheme'] );
		unset( $parsed['user'] );
		unset( $parsed['pass'] );
		unset( $parsed['host'] );
		unset( $parsed['port'] );
		$localPart->setParsed( $parsed );
		return $localPart;
	}

	/** */
	/*///
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
	//*///

	/** */
	/*///
	public function getAllowedRegions( $user = null ) {
		if ( !isset( $this->_allowedRegionsCache[$userId] ) ) {
			if ( $this->_allowedRegionsCache === null ) {
				$this->_allowedRegionsCache = array();
			}
			if ( $user === null ) {
				$user = RequestContext::getMain()->getUser();
			}
			$userId = $user->getId();
			$this->_allowedRegionsCache[$userId] = array( 'view' => array(), 'create' => array() );
			$basicAllowed = array(
				'view' => NumericUrlCommon::isAllowed( 'view-shared', $user ),
			);
			// A user without view-shared rights can't have any region rights
			if ( !$basicAllowed['view'] ) {
				return $this->_allowedRegionsCache[$userId];
			}
			$basicAllowed['create'] = NumericUrlCommon::isAllowed( 'create-basic', $user );
			$globalAllowed = array(
				'view' => NumericUrlCommon::isAllowed( 'view-global', $user ),
				'create' => $basicAllowed['create'] && NumericUrlCommon::isAllowed( 'create-global', $user ),
			);
			foreach ( array( 'view', 'create' ) as $perm ) {
				$this->_allowedRegionsCache[$userId][$perm] = array();
				foreach ( $this->getRegionsInfo() as $region => $info ) {
					if ( $basicAllowed[$perm]
					 && ( $globalAllowed[$perm]
								|| NumericUrlCommon::isAllowed( "$perm-region-$region", $user  ) )
					 && ( ( $perm === 'view' )
							 || isset( $this->_allowedRegionsCache[$userId]['view'][$region] ) )
					) {
						$this->_allowedRegionsCache[$userId][$perm][$region] = true;
					}
				}
			}
		}
		return $this->_allowedRegionsCache[$userId];
	}
	//*///

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
		if ( $this->hasAuthority() && !$this->hasSameAuthority( self::$_localServer ) ) {
			return false;
		}

		$parsed = $this->getParsed();
		// If on the same server and if this scheme contradicts the local scheme, it's not local
		if ( self::$_localServerScheme && isset( $parsed['scheme'] ) && ( self::$_localServerScheme !== $parsed['scheme'] ) ) {
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

	/** */
	public function setDbMaster( $db ) {
		$this->_dbMaster = $db;
	}

	/** */
	public function getDbMaster() {
		if ( $this->_dbMaster === null ) {
			$this->_dbMaster = wfGetDB( DB_MASTER );
		}
		return $this->_dbMaster;
	}

	/** */
	public function setDbSlave( $db ) {
		$this->_dbSlave = $db;
	}

	/** */
	public function getDbSlave() {
		if ( $this->_dbSlave === null ) {
			$this->_dbSlave = wfGetDB( DB_SLAVE );
		}
		return $this->_dbSlave;
	}

	/** */
	public function setTimestamp( $timestamp ) {
		$this->_timestamp = $timestamp;
	}

	/** */
	public function getTimestamp() {
		if ( $this->_timestamp === null ) {
			$this->_timestamp = wfTimestamp( TS_MW );
		}
		return $this->_timestamp;
	}

	/** */
	public function setExpiry( $expiry ) {
		$this->_expiry = $expiry;
	}

	/** */
	public function getExpiry() {
		return $this->_expiry;
	}

	/** */
	public function setEmbargo( $embargo ) {
		$this->_embargo = $embargo;
	}

	/** */
	public function getEmbargo() {
		return $this->_embargo;
	}

	/** */
	public function setCreator( $creator ) {
		$this->_creator = $creator;
	}

	/** */
	public function getCreator() {
		if ( $this->_creator === null ) {
			$this->_creator = RequestContext::getMain()->getUser();
		}
		return $this->_creator;
	}

	/** */
	public function setEnabled( $enabled = true ) {
		$this->_enabled = (bool)$enabled;
	}

	/** */
	public function getEnabled() {
		if ( $this->_enabled === null ) {
			$this->_enabled = false;
		}
		return $this->_enabled;
	}

	/** */
	public function setKey( $key ) {
		$this->_key = $key;
	}

	/** */
	public function getKey() {
		return $this->_key;
	}

	/**
	 * Load the map instance from the database even if it's not active.
	 */
	public function load( $key = null ) {
		if ( $key !== null ) {
			$this->_reset();
			$this->_key = $key;
		}
		return $this->_initFromDbRow( $this->_loadDbRow( $this->getDbSlave(), $this->_key ) );
	}

	/**
	 * Load the map instance from the database only if it's active.
	 */
	public function loadActive( $key = null ) {
		if ( $key !== null ) {
			$this->_reset();
			$this->_key = $key;
		}
		$now = wfTimestamp( TS_MW ); // SQL-safe
		$db = $this->getDbSlave();
		$conds = array(
			'numap_key' => $this->_key,
			'numap_enabled' => 1,
			$db->makeList( array ( 'numap_expiry' => null, "numap_expiry > '$now'", ), LIST_OR ),
			$db->makeList( array ( 'numap_embargo' => null, "numap_embargo <= '$now'", ), LIST_OR ),
		);
		return $this->_initFromDbRow( $this->_loadDbRow( $db, $key, $conds ) );
	}

	/**
	 * Store the map instance into the database.
	 *
	 * @todo - create maintenance task to remove orphans from the links table.
	 *
	 * @returns string key of instance
	 */
	public function store() {
		if ( !$this->makeAbsolute() ) {
			return false;
		}
		$this->getParsed();
		$this->getText();
		$this->getCreator();
		$values = $this->_dbValuesFromObject( $this );
		$result = $this->_store( $this->getDbMaster(), $values );
		if ( ( $result !== false ) && ( $this->_key === null ) ) {
			$this->_key = $result;
		}
		return $result;
	}

	/**
	 * Generate a random key.
	 *
	 * Call hooked function, else generate a base-36 key. The key must be SQL-safe.
	 *
	 *  @param $length The number of characters in the key.
	 */
	protected function _generateRandomKey( $length = null ) {
		if ( $length === null ) {
			$length = 15;
		}
		$randomKey = null;
		if ( Hooks::isRegistered( 'NumericUrlGenerateRandomKey' ) ) {
			wfRunHooks(
				'NumericUrlGenerateRandomKey',
				array(
					&$randomKey,
					$length === null ? 15 : max( 1, min( $length, 15) ),
				)
			);
			if ( $randomKey !== null ) {
				return $randomKey;
			}
		}
		$randomKey = MWCryptRand::generateHex( $length === null ? 12 : max( 1, min( $length, 12), true ) );
		return $randomKey;
	}

	protected function _reset() {
		parent::_reset();
		$this->_isLocal = null;
		$this->_isValid = null;
		$this->_regionsInfo = null;
		$this->_regionsAllowed = null;
		$this->_isAbsolute = null;
		$this->_allowedRegionsCache = null;
		$this->_isAllowedCache = null;
		$this->_isPrivate = null;
		$this->_key = null;
		$this->_expiry = null;
		$this->_embargo = null;
		$this->_timestamp = null;
		$this->_enabled = null;
		// Leave these alone, as they are independent of other settings:
		//  $this->_dbMaster
		//  $this->_dbSlave
		//  $this->_creator
	}

	/**
	 * Load the map instance row from the database.
	 */
	protected function _loadDbRow( $db, $key, $conds = array(), $vars = array() ) {
		if ( $key === null ) {
			$key = $this->_key;
		}
		$vars = array(
			'numap_id',
			'numap_link',
			'numap_enabled',
			'numap_creator',
			'numap_regions_count',
			'numap_expiry',
			'numap_embargo',
			'numap_timestamp',
			)
			+ $vars;
		if ( !isset( $conds['numap_key'] ) ) {
			$conds['numap_key'] = $key;
		}
		$row = $db->selectRow( self::$_dbTableMap, $vars, $conds, __METHOD__ );
		if ( $row ) {
			// We could have done an OUTER JOIN to collect all the regions in the above query,
			// but we expect relatively few mappings with regions, so we only check when we
			// see that this mapping has regions.
			$user = RequestContext::getMain()->getUser();
			$userId = $user->getId();
			if ( $row->numap_regions_count > 0 ) {
				$row->numaps_regions = $this->_loadDbRegions( $row->numap_id, $row->numap_regions_count );
				$matched = false;
				if ( is_array( $row->numaps_regions ) ) {
					// Allowed access to *any* of these regions allows access to this map instance
					foreach ( $row->numaps_regions as $region ) {
						if ( NumericUrlCommon::isAllowed( "view-region-$region", $user ) ) {
							$matched = true;
							break;
						}
					}
				} else {
					$row->numaps_regions = array();
				}
				// this user is not in any of these regions, so discard the mapping
				if ( ( !$matched ) && ( 1 ) ) {
				}
			} else {
				$row->numaps_regions = array();
			}
		}
		return $row;
	}
  
  /**
   * Worker function for isActionAllowed() for sorting out permissions.
   */
  private function _isUserDeniedActionInRegion( $user, $userId, $action, $region ) {
    // if 'view' isn't allowed, everything's denied
    if ( !NumericUrlCommon::isAllowed( "view-$region", $user ) ) {
      $this->_isAllowedCache['view'][$userId] = false;
      if ( $action !== 'view' ) {
        $this->_isAllowedCache[$action][$userId] = false;
      }
      return true;
    }
    if ( ( $action !== 'view' ) &&  !NumericUrlCommon::isAllowed( "$action-$region", $user ) ) {
      $this->_isAllowedCache[$action][$userId] = false;
      return true;
    }
    // nothing denied here
    return false;
  }

	/**
	 * @todo - maintenance task to remap DB regions from hooks
	 */
	private function _getRegions( $scope ) {
		if ( isset( $this->_regions[$scope] ) ) {
			return $this->_regions[$scope];
		}

		$hookedRegions = array();
		wfRunHooks( 'NumericUrlRegion' . ucfirst( $scope ),
			array(
				&$hookedRegions,
				$this->cloneLocalPart(),
			)
		);

		$this->_regions[$scope] = array();
		foreach ( array_keys( $hookedRegions ) as $region ) {
			// valid names must start with a letter and have remaining alphanumeric or underscore characters
		  if ( preg_match( '/^[a-z][a-z0-9_]*$/', $region ) ) {
				$this->_regions[$scope][] = $region;
			} else {
				trigger_error(
					sprintf('%s: Ignoring invalid %s scope region name: "%s"', __METHOD__, $scope, $region )
					E_USER_WARNING);
			}
		}

		return $this->_regions['scope'];
	}

	/**
	 * Load regions as stored in the DB.
	 */
	private function _loadDbRegions( $mapId, $expectedCount = null ) {
		$vars = array(
			'nurgn_region',
		);
		$conds = array(
			'nurgn_numap_id' => $mapId,
		);
		$rows = array();
		$res = $db->select( self::_dbTableRegions, $vars, $conds, __METHOD__ );
		if ( $res ) {
			foreach ( $res as $row ) {
				if ( $row->nurgn_region ) {
					$rows[$row->nurgn_region] = true;
				}
			}
		}
		if ( ( $expectedCount !== null) && ( count( $rows ) != $expectedCount ) ) {
			// @todo create maintenance cleanup task to find and fix  miscounts and to
			// report related security issue. (If we're not properly recording the regions
			// count, and we incorrectly count zero, we incorrectly allow '*' access.)
			trigger_error(
				sprintf('%s: Expected %u regions, but found %u; numap_id=%u; perform cleanup to fix', __METHOD__,
					$expectedCount, count( $rows )
				),
				E_USER_WARNING );
		}
		return $rows;
	}

	/**
	 * Init $this from a db row object.
	 */
	private function _initFromDbRow( $row, $key = null ) {
		if ( !$row ) {
			return false;
		}
		if ( !$key ) {
			if ( isset( $row->numap_key ) ) {
				$key = $row->numap_key;
			} else {
				$key = $this->_key;
			}
		}
		if ( isset( $row->numap_link ) ) {
			$this->setText( $row->numap_link );
		} else {
			$this->_reset();
		}
		$this->_key = $key;
		foreach (
			array( 'link' => 'text', 'expiry', 'embargo', 'timestamp', 'enabled' )
			as $k1 => $k2
		) {
			if ( is_numeric( $k1 ) ) {
				$k1 = $k2;
			}
			if ( isset( $row->{"numap_$k1"} ) ) {
				$this->{"_$k2"} = $row->{"numap_$k1"};
			}
		}
		return true;
	}

	/**
	 * Get DB values array from object.
	 */
	private function _dbValuesFromObject( $object ) {
		if ( ( !isset( $object->_parsed['scheme'] ) )
			|| ( !$object->_parsed['scheme'] )
			|| ( $object->_parsed['scheme'] === 'https' )
		) {
			$insecureScheme = 0;
		} else {
			$insecureScheme = 1;
		}
		$values = array();
		foreach ( array(
			'insecure' => 'parsed', 'creator', 'link'=>'text', 'key', 'expiry', 'embargo',
			'enabled'
			)
			as $k1 => $k2
		) {
			if ( is_numeric( $k1 ) ) {
				$k1 = $k2;
			}
			$k2 = "_$k2";
			if ( isset( $object->{$k2} ) ) {
				if ( $k1 === 'enabled' ) {
					$v = (int)(bool)$object->{$k2};
				} elseif ( $k1 === 'creator' ) {
					$v = $object->{$k2}->getId();
				} elseif ( $k1 === 'insecure' ) {
					$v = $insecureScheme;
				} else {
					$v = $object->{$k2};
				}
				$values["numap_$k1"] = $v;
			}
		}
		return $values;
	}

	/** */
	private function _store( $db, $values ) {

		$affectedRows = 0;
		$triesRemaining = 10;
		$randomKey = !isset( $values['numap_key'] );
		do {
			if ( $randomKey ) {
				$values['numap_key'] = self::_generateRandomKey();
			}
			$values['numap_timestamp'] = wfTimestamp( TS_MW );
			if ( $db->insert( self::$_dbTableMap, $values, __METHOD__, 'IGNORE' ) ) {
				$affectedRows = $db->affectedRows();
				if ( $affectedRows != 1 ) {
					if ( $randomKey && ( $triesRemaining > 0 ) ) {
						trigger_error(
							sprintf('%s: Can\'t insert (collision?); will retry; key="%s"', __METHOD__,  $values['numap_key'] ),
							E_USER_NOTICE );
					}
				}
			} else {
				throw new DBUnexpectedError(
					$db,
					'INSERT IGNORE returned false but didn\'t throw an exception.'
				);
			}
		} while ( $randomKey && ( $affectedRows != 1 ) && ( $triesRemaining-- > 0 ) );
		if ( $affectedRows != 1 ) {
			trigger_error(
				sprintf('%s: Can\'t insert (collision?); key="%s"', __METHOD__,  $values['numap_key'] ),
				E_USER_WARNING
			);
		}
		return $affectedRows == 1 ? $values['numap_key'] : false;
	}

	/**
	 * Add a link to the links table.
	 *
	 * @todo - create maintenance task to cull duplicates
	 */
	private function _storeLink( $db ) {
		if ( !$this->makeAbsolute() ) {
			return false;
		}
		if ( ( !isset( $this->_parsed['scheme'] ) )
			|| ( !$this->_parsed['scheme'] )
			|| ( $this->_parsed['scheme'] === 'https' )
		) {
			$insecureScheme = 0;
		} else {
			$insecureScheme = 1;
		}
		$values = array(
			'numap_link' => $this->getText(),
			'numap_insecure' => $insecureScheme,
		);
		if ( ! $db->insert( self::$_dbTableLinks, $values, __METHOD__ ) ) {
			return false;
		}
		return $db->insertId();
	}

	/** */
	private static $_localServer;

	/**
	 * This is possibly blank, for servers that allow connection via both http and https.
	 */
	private static $_localServerScheme;

	/** */
	private $_isValid;

	/** */
	private $_isLocal;

	/** */
	private $_regionsInfo;

	/** */
	private $_allowedRegionsCache;

	/** */
	private $_user;

	/** */
	private $_isPrivate;

	/** */
	private $_isAllowedCache;

	/** */
	private $_dbSlave;

	/** */
	private $_dbMaster;

	/** */
	private $_key;

	/** */
	private $_timestamp;

	/** */
	private $_expiry;

	/** */
	private $_embargo;

	/** */
	private $_creator;

	/** */
	private $_enabled;

	/** */
	private static $_dbTableMap = 'numericurlmap';

	/** */
	private static $_dbTableRegions = 'numericurlregions';

	/**
	 * Subset of $wgActions that we support.
	 *
	 * Combine these names with a region (below) to get a permission tag.
	 */
	private static $_actions = array(
		'create' => array(),  // create a mapping
		'delete' => array(),  // n.b. an extremely rare action! (even then, it only sets a flag)
		'edit'   => array(),  // can't change long URL, just various attributes
		'view'   => array(),  // view and/or follow the redirect
	);

	/**
	 * Built-in URL regions
	 *
	 * These with actions map to permissions, e.g. "numericurl-create-basic"
	 */
	private static $_builtInRegions = array(
		'basic'  => 1,  // a "basic" page on this wiki (configurable)
		// 'local-*'  <-- hooked local regions go here
		'local'  => 1,  // other URLs on this site not in a hooked region
		// 'global-*' <-- hooked global regions go here
		'global' => 1,  // other URLs anywhere else not in a hooked region
		'all'    => 1,  // grants permission to all URL
	);

}
