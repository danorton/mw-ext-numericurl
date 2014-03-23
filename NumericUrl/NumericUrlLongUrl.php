<?php

error_reporting(-1);

class NumericUrlLongUrl {

  /** 
   * @var mLongUrl Long version of the URL
   */
   
  public $testing;

  public function __construct( $longUrl ) {
  }
  
  public function isLocal() {
  }
  
  public function isBasic() {
  }
  
  public function isInterWiki() {
  }
  
  public function parse() {
  }
  
  private static function _notice_undefined( $name = null ) {
    if ( !( error_reporting() & E_NOTICE ) ) {
      return;
    }
    $caller = self::getCaller( 3 );
    if ( $name === null ) {
      $name = $caller['args']['0'];
    }
    error_log(
      sprintf("PHP Notice:  Undefined property: %s::$%s in %s on line %u",
        __CLASS__,
        $name,
        $caller['file'],
        $caller['line']
      ),
      0
    );
    //var_dump( $caller ); exit(3);
  }
 
  private function &_getPrivateProperty( $name ) {
    $privateName = "_PUBLIC_$name";
    if ( !property_exists( $this, $privateName ) ) {
      self::_notice_undefined();
      return self::$_dummy;
    }
    return $this->{$privateName};
  }
 
  function __set( $name, $value ) {
    $prop = &$this->_getPrivateProperty( $name );
    $prop = $value;
    self::$_dummy = null;
  }
  
  function __get( $name ) {
    return $this->_getPrivateProperty( $name );
  }
  
  public static function getCaller( $depth = 1 ) {
    if ( self::$_backtraceHasLimit ) {
      $t = debug_backtrace( self::$_backtraceOptions, $depth );
    } else {
      $t = debug_backtrace( self::$_backtraceOptions );
    }
    return array_intersect_key( $t[$depth], self::$_propsGetCaller );
  }
  
  public static function _initStatic() {
    if ( !isset( self::$_backtraceOptions ) ) {
      if ( defined('DEBUG_BACKTRACE_IGNORE_ARGS') ) {
        self::$_backtraceOptions = DEBUG_BACKTRACE_PROVIDE_OBJECT; //DEBUG_BACKTRACE_IGNORE_ARGS;
      } else {
        self::$_backtraceOptions = true;
      }
      self::$_backtraceHasLimit = ( PHP_VERSION_ID >= 50400 );
    } else {
      throw new ErrorException( __METHOD__ . ' is not callable' );
    }
  }
 
  private $_PUBLIC_mLongUrl;
 
  private static $_backtraceOptions;
  private static $_backtraceHasLimit;
  private static $_propsGetCaller = array( 'file' => 1, 'line' => 1, 'args' => 1 );
  private static $_dummy = null;
  
}
NumericUrlLongUrl::_initStatic();

function t() {
  $n = new NumericUrlLongUrl(0);
} 
t();
