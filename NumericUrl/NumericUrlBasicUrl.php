<?php

if ( !defined( 'MW_EXT_NUMERICURL_NAME' ) ) {
	echo "This file is an extension to MediaWiki software and is not designed for standalone use.\n";
	die ( 1 );
}

require_once "{$GLOBALS['IP']}/extensions/Weirdo/Weirdo.php";

class NumericUrlBasicUrl extends WeirdoUrl {

	/** */
	public static function newFromUrl( $url ) {
		return new self( $url );
	}

	/** */
	public static function newFromParsed( $parsed ) {
		return new self( $parsed );
	}

	/** */
	public function __construct( $urlOrParts = null ) {
		parent::__construct( $urlOrParts );

	}

	/** */
	public function init( $urlOrParts ) {
		parent::init( $urlOrParts );
		global $wgServer;
		$this->_queryInputSeparators = '&';
	}

	/**
	 * Don't allow changing queryInputSeparators.
	 *
	 * In MW it's hardcoded as '&'.
	 */
	public function setQueryInputSeparators( $queryInputSeparators ) {
		throw new MWException( sprintf( '%s: this method is not available', __METHOD__ ) );
	}

	/** */
	public function isBasic() {
		if ( $this->_isBasic === null ) {
			$this->_isBasic = false;
			$query = $this->getQuery();
			unset( $query['curid'] );
			unset( $query['oldid'] );
			unset( $query['title'] );
			if ( !count( $query ) ) {
				$this->_isBasic = (bool)$this->getTitle();
			}
		}
		return $this->_isBasic;
	}

	/** */
	public function getTitle() {
		if ( !$this->_title ) {
			$this->_title = false;
			if ( $this->isLocal() ) {
				$id = $this->getQueryValue( 'oldid' );
				if ( !$id ) {
					$id = $this->getQueryValue( 'curid' );
					if ( !$id ) {
						$id = $this->getQueryValue( 'pageid' );
					}
				}
				if ( $id ) {
					$this->_title = Title::newFromID( $id );
				}
				if ( !$this->_title ) {
					$titleText = $this->getQueryValue( 'title' );
					if ( !$titleText ) {
						$parsed = $this->getParsed();
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
						$this->_title = Title::newFromText( $titleText );
						if ( $this->_title && !$this->_title->isKnown() ) {
							$this->_title = null;
						}
					}
				}
			}
		}
		return $this->_title;
	}

	protected function _reset() {
		parent::_reset();
		$this->_queryInputSeparators = '&';
		$this->_isBasic = null;
		$this->_title = null;
	}

	private $_isBasic;

	private $_title;

}
