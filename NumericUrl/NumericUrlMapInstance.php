<?php

if ( !defined( 'MW_EXT_NUMERICURL_NAME' ) ) {
	echo "This file is an extension to MediaWiki software and is not designed for standalone use.\n";
	die ( 1 );
}

require_once "{$GLOBALS['IP']}/extensions/Weirdo/Weirdo.php";

class NumericUrlMapInstance extends WeirdoUrl {

	public $source;

	public $localServer;

	/**
	 * This is possibly blank, for servers that allow connection via both http and https.
	 */
	public $localServerScheme;

	public function __construct( $urlText = null ) {
		parent::__construct( $urlText );

		global $wgServer;
		$this->localServer = new WeirdoUrl( $wgServer ) ;
		$serverParsed = $this->localServer->getParsed();
		$this->localServerScheme = isset ( $serverParsed['scheme'] ) ? $serverParsed['scheme'] : null;
	}

	/** */
	public function setText( $urlText ) {
		$this->_reset();
		parent::setText( $urlText );
	}

	/** */
	public function isValid() {
		if ( $this->_isValid !== null ) {
			return $this->_isValid;
		}
		if ( !$this->getValidity() ) {
			$this->_isValid = false;
			return false;
		}
		// $this->_parsed is defined at this point
		// we don't allow URLs with passwords
		$isValid = !isset( $this->_parsed['pass'] );

		// don't waste time with hooks code if none are registered
		if ( $isValid && Hooks::isRegistered( 'NumericUrlValidityCheck' ) ) {
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
			// validate the regions
			foreach ( $this->_regionsInfo as $regionId => $regionInfo ) {
				if ( !preg_match( '/^[a-z][a-z0-9_]*/', $regionId ) ) {
					trigger_error(
						sprintf( '%s: invalid region ID; rejected region "%s"', __METHOD__, $regionId ),
						E_USER_WARNING);
					// remove the invalid region
					unset( $this->_regionsInfo[$regionId] );
				} elseif ( !isset( $this->_regionsInfo[$regionId]['description-message'] ) ) {
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
	public function isLocal() {
		if ( $this->_isLocal !== null ) {
			return $this->_isLocal;
		}
		$this->_isLocal = false;

		if ( !$this->getValidity() ) {
			return false;
		}

		// if there's an authority and it doesn't match this server's authority, then it's not local
		if ( $this->hasAuthority() && !$this->hasSameAuthority( $this->localServer ) ) {
			return false;
		}

		$parsed = $this->getParsed();
		// If on the same server and if this scheme contradicts the local scheme, it's not local
		if ( $this->localServerScheme && isset( $parsed['scheme'] ) && ( $this->localServerScheme !== $parsed['scheme'] ) ) {
			return false;
		}

		$this->_isLocal = true;
		return true;
	}

	public function isBasic() {
	}

	public function getRegionInfo( $regionId ) {
		$regionsInfo = $this->getRegionsInfo();
		if ( isset( $regionsInfo[$regionId] ) ) {
			return $regionsInfo[$regionId];
		}
		return null;
	}

	public function isInterwiki() {
	}

	public function isInterwikiLocal() {
	}

	public function isGlobal() {
	}

	protected function _reset() {
		parent::_reset();
		$this->_isLocal = null;
		$this->_isValid = null;
		$this->_regionsInfo = null;
	}

	/** */
	private $_isValid;

	/** */
	private $_isLocal;

	/** */
	private $_regionsInfo;

}
