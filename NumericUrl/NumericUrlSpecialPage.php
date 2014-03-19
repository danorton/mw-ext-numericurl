<?php
/**
 * @ingroup SpecialPage
 * @{
 * NumericUrlSpecialPage class for NumericUrl extension
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

/**
 * This class provides the NumericUrl @b Special: page, which generates redirects.
 */
class NumericUrlSpecialPage extends FormSpecialPage {

  /** */
  public $mTitle;
  
  /** */
  public $mCurId;
  
  /** */
  public $mOldId;
  
  /** */
  public $mAction;

  /** */
  public $mTarget;

	/** For parameters and semantics, see FormSpecialPage::__construct(). */
	public function __construct() {
    NumericUrlCommon::_debugLog( 20, __METHOD__ );
		parent::__construct( NumericUrlCommon::SPECIAL_PAGE_TITLE );
    $this->_mp = parent::getMessagePrefix();
    $rq = $this->getRequest();
    $query = rawurldecode( $rq->getVal( 'nuquery' ) );
    NumericUrlCommon::_debugLog( 20,
      sprintf('%s(): query=%s', __METHOD__, $query )
    );
    parse_str( $query, $args ) ;
    foreach ( array( 'mTitle', 'mCurId', 'mOldId', 'mAction' ) as $mProp ) {
      $prop = strtolower( substr( $mProp, 1 ) );
      if ( isset( $args[$prop] ) ) {
        $this->{$mProp} = $args[$prop];
        NumericUrlCommon::_debugLog( 20,
          sprintf('%s(): %s=%s', __METHOD__, $prop, $this->{$mProp} )
        );
      }
    }
	}

	/** For parameters and semantics, see SpecialPage::execute(). */
	public function execute( $subPage ) {
    NumericUrlCommon::_debugLog( 20,
      sprintf('%s("%s")', __METHOD__, $subPage)
    );
    if ( $subPage == '' ) {
      $this->_toolPage();
		} else {
      return $this->_noSuchPage();
		}
	}

  /** For parameters and semantics, see FormSpecialPage::getFormFields(). */
  public function getFormFields() {
    NumericUrlCommon::_debugLog( 20, __METHOD__ );
 
    $mp = "{$this->_mp}-toolform";
    $fields = array();
    
    if ( !$this->mTarget ) {
      $this->_buildTarget() ;
    }
    
    $fields['target'] = array(
      'type' => 'url',
      'label-message' => "$mp-target",
    );

    $scope = array(
      'local'   => "$mp-local",
      'global'  => "$mp-global",
    );
    $fields['scope'] = array(
      'type' => 'select',
      'label-message' => "$mp-scope",
      'options' => array(),
      'default' => current( array_keys( $scope ) ),
    );
    // Store the scope options with i18n text
    foreach( $scope as $k => $v ) {
      $v = $this->msg( $v )->text() ;
      $fields['scope']['options'][$v] = $k;
    }
 
    $scheme = array(
      'https' => "$mp-https",
      'http'  => "$mp-http",
      'any'   => "$mp-any-scheme",
    );
    $fields['scheme'] = array(
      'type' => 'select',
      'label-message' => "$mp-scheme",
      'options' => array(),
      'default' => current( array_keys( $scheme ) ),
    );
    // Store the scheme options with i18n text
    foreach( $scheme as $k => $v ) {
      $v = $this->msg( $v )->text() ;
      $fields['scheme']['options'][$v] = $k;
    }
 
    $fields['shared'] = array(
      'type' => 'toggle',
      'label-message' => "$mp-shared",
      'default' => true,
    );
 
    $fields['revision'] = array(
      'type' => 'toggle',
      'label-message' => "$mp-revision",
      'default' => !empty( $this->mOldId )
    );
 
    $fields['revid'] = array(
      'type' => 'int',
      'label-message' => "$mp-revid",
      'default' => $this->mOldId,
    );

    $fields['pageid'] = array(
      'type' => 'int',
      'label-message' => "$mp-pageid",
      'default' => $this->mCurId,
    );

    $fields['expiry'] = array(
      'type' => 'text',
      'label-message' => "$mp-expiry",
      'default' => strftime( '%Y-%m-%d %H:%M:%SZ', time() + 3600*24*7*13 ), // 91 days
    );

    $fields['shorter'] = array(
      'type' => 'url',
      'label-message' => "$mp-shorter",
    );

    if ( !empty( $this->mScope ) ) {
      $fields['scope']['default'] = $this->mScope;
    }
    if ( !empty( $this->mScheme ) ) {
      $fields['scheme']['default'] = $this->mScheme;
    }
    if ( !empty( $this->mTitle ) ) {
      $fields['target']['default'] = $this->mTitle;
      $fields['target']['readonly'] = true;
    }
    if ( 1 ) {
      $fields['shorter']['default'] = mt_rand() . mt_rand() ;
      $fields['shorter']['readonly'] = true;
    }
 
    return $fields;
  }
  
  /** For parameters and semantics, see FormSpecialPage::onSubmit(). */
	public function onSubmit( array $data ) {
    NumericUrlCommon::_debugLog( 20, __METHOD__ );
    return false;
  }

  /** For parameters and semantics, see FormSpecialPage::onSuccess(). */
	public function onSuccess() {
    NumericUrlCommon::_debugLog( 20, __METHOD__ );
  }

  /** For parameters and semantics, see SpecialPage::getDescription(). */
  public function getDescription() {
    if ( $this->_showToolForm ) {
      $msgid = $this->getMessagePrefix();
    }
    else {
      return parent::getDescription();
    }
    return $this->msg( $msgid )->text();
  }
  
  /** */
  public function getMessagePrefix() {
    $prefix = $this->_mp;
    if ( $this->_showToolForm ) {
      $prefix = "$prefix-toolform";
    }
    return $prefix;
  }

  /** For parameters and semantics, see FormSpecialPage::alterForm(). */
  protected function alterForm( HTMLForm $form ) {
    $form->setSubmitTextMsg( $this->getMessagePrefix() . '-submit' );
  }

  /** */
  private function _buildTarget() {
 
    if ( $this->mScopeIsLocal ) {
      $this->mServer = $wgServer ;
    }
 
  }
  
  /** */
  private function _redirect( $out, $url, $status = 307 ) {
    NumericUrlCommon::_debugLog( 20, __METHOD__ );
		$out->redirect( $url, $status );
  }

  /** */
	private function _toolPage() {
    NumericUrlCommon::_debugLog( 20, __METHOD__ );
    $this->_showToolForm = true;
    
    $out = $this->getOutput();
		$out->setArticleRelated( false ); // bugbug: set accordingly
		$out->setRobotPolicy( 'noindex,nofollow' );
    
    $this->setHeaders();
    
    $this->outputHeader( "{$this->_mp}-toolform-summary" );

    $form = $this->getForm();
    if ( $form->show() ) {
      $this->onSuccess();
    }

	}

  /** */
	private function _noSuchPage() {
    NumericUrlCommon::_debugLog( 20, __METHOD__ );
    $out = $this->getOutput();
		$out->setArticleRelated( false );
		$out->setRobotPolicy( 'noindex,nofollow' );

		if ( $GLOBALS['wgSend404Code'] ) {
			$out->setStatusCode( 404 );
		}

		$out->showErrorPage( "{$this->_mp}-nosuchpage", "{$this->_mp}-nosuchpagetext" );
	}

  /** i18n message prefix */
  private $_mp;

  /** */
  private $_showToolForm;

}

/** @}*/
