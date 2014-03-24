<?php

error_reporting(-1);

class NumericUrlLongUrl {

  /** 
   * @var mLongUrl Long version of the URL
   */
   
  public $mLongUrl;

  public function __construct( $longUrl ) {
  }
  
  public function isLocal() {
  }
  
  public function isBasic() {
  }
  
  public function isInGroup( $groupName = 'iw' ) {
  }
  
  public function parse() {
  }
  
  public static function _initStatic() {
    if ( !isset( self::$_backtraceOptions ) ) {
    } else {
      throw new ErrorException( __METHOD__ . ' is not callable' );
    }
  }
 
  
}
//NumericUrlLongUrl::_initStatic();
