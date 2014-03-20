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
  public $mQuery;
  
  /** */
  public $mRevision;

  /** */
  public $mTarget;

  /** */
  public $mNumericUrl;

  /** */
  public $mNumericUrlExpiry;

  /** */
  public $mScheme;

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
    parse_str( $query, $args );
    $this->mQuery = array();
    foreach ( array( 'title', 'curid', 'oldid', 'action', 'pageid' ) as $prop ) {
      if ( isset( $args[$prop] ) ) {
        $this->mQuery[$prop] = $args[$prop];
        NumericUrlCommon::_debugLog( 20,
          sprintf('%s(): %s=%s', __METHOD__, $prop, $this->mQuery[$prop] )
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
    
    $target = $this->mTarget;
    $targetPrefixHtml = '';
    
    // remove our own base URL for brevity
    if (NumericUrlCommon::$mBaseUrl === substr( $target, 0, strlen( NumericUrlCommon::$mBaseUrl ) ) ) {
      $target = substr( $target, strlen( NumericUrlCommon::$mBaseUrl ) );
      $targetPrefixHtml = '&hellip;';
    }
 
    if ( $this->mTitle ) {
      $htmlTitle = htmlspecialchars( $this->mTitle->getPrefixedText() );
      // only hyperlink the title if this is for the latest revision of the title
      if ( !( isset( $this->mQuery['curid'] ) || $this->mRevision ) ) {
        $htmlTitle = Html::rawElement( 'a', array( 'href' => $target ), $htmlTitle );
      }
      $fields['target-title'] = array(
        'type' => 'info',
        'cssclass' => "$mp-target-title",
        'label-message' => "$mp-target-title",
        'raw' => true,
        'default' => $htmlTitle,
      );

      if ( $this->mRevision ) {
        $context = $this->getContext();
        $timestamp = $context->getLanguage()->userTimeAndDate( $this->mRevision->getTimestamp(), $context->getUser() );
        $htmlTimestamp = Html::rawElement( 'a', array( 'href' => $target ), $timestamp );
        $fields['oldid'] = array(
          'type' => 'info',
          'label-message' => "$mp-oldid",
          'raw' => true,
          'default' => $htmlTimestamp,
        );
      } elseif ( isset( $this->mQuery['curid'] ) ) {
        $fields['curid'] = array(
          'type' => 'info',
          'label-message' => "$mp-curid",
          'default' => $this->mQuery['curid'],
        );
      }
    }

    $fields['target'] = array(
      'type' => 'info',
      'cssclass' => "$mp-target",
      'label-message' => "$mp-target",
      'raw' => true,
      'default' =>
        Html::rawElement( 'a',
          array(
            'href' => $target,
            'title' => $this->mTarget,
          ),
          $targetPrefixHtml . htmlspecialchars( $target )
          ),  
    );
    
    // display scheme selection if our host isn't scheme-specific
    if ( NumericUrlCommon::$mBaseScheme === NumericUrlCommon::URL_SCHEME_FOLLOW ) {
      $scheme = array(
        NumericUrlCommon::URL_SCHEME_HTTPS  => "$mp-https",
        NumericUrlCommon::URL_SCHEME_HTTP   => "$mp-http",
        NumericUrlCommon::URL_SCHEME_FOLLOW => "$mp-any-scheme",
      );
      $fields['scheme'] = array(
        'type' => 'select',
        'label-message' => "$mp-scheme",
        'options' => array(),
        'default' => $this->mScheme,
      );
      // Store the scheme options with i18n text
      foreach( $scheme as $k => $v ) {
        $v = $this->msg( $v )->text();
        $fields['scheme']['options'][$v] = $k;
      }
    }
 
    if ( 0 && $this->mNumericUrl ) {
      $fields['numeric'] = array(
        'type' => 'info',
        'label-message' => "$mp-numeric",
        'readonly' => true,
        'default' => 'default?',
      );

      $fields['expiry'] = array(
        'type' => 'info',
        'cssclass' => 'mw-info-numeric-expiry',
        'label-message' => "$mp-expiry",
        'default' => strftime( '%Y-%m-%d %H:%M:%SZ', time() + 3600*24*7*13 ), // 91 days
      );
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
  
  private function _getTitle() {
    if ( ( !$this->mTitleObject ) && $this->mQuery['title'] ) {
      $this->mTitleObject = Title::newFromText( $this->mQuery['title'] );
    }
    return $this->mTitleObject;
  }

  /** */
  private function _buildTarget() {

    // We require the title text, at the very least
    // (This avoids problems with stale links if the title changes.)
    if ( !isset( $this->mQuery['title'] ) ) {
      return;
    }
    
    // Fetch the title object for the given title text
    $this->mTitle = Title::newFromText( $this->mQuery['title'] );
    if ( !$this->mTitle ) {
      return;
    }
 
    $query = array();
 
    // If it's an old revision or a page ID, we have to path through index.php
    if ( isset( $this->mQuery['curid'] ) || isset( $this->mQuery['oldid'] ) ) {
      if ( isset( $this->mQuery['oldid'] ) ) {
        unset( $this->mQuery['curid'] );
        $this->mRevision = Revision::newFromTitle( $title, $this->mQuery['oldid'] );
        $query[] = "oldid={$this->mQuery['oldid']}";
      } else {
        // curid must match the title's article ID
		    if ( $this->mQuery['curid'] != $this->mTitle->getArticleID() ) {
          return;
        }
        $query[] = "curid={$this->mQuery['curid']}";
      }
      global $wgScript;
      $path = $wgScript;
    } else {
      // This is the URL for the latest revision of the article of the specified title
      $this->mRevision = null;
      global $wgArticlePath;
      $path = str_replace( '$1', $this->mQuery['title'], $wgArticlePath );
    }
 
    // add the action
    if ( isset( $this->mQuery['action'] ) ) {
      $query[] = "action={$this->mQuery['action']}";
    }
    if ( count($query) ) {
      $path .= '?' . implode( '&', $query );
    }
    
    // set the scheme
    $this->mScheme = NumericUrlCommon::$mBaseScheme;
 
    // set the redirection target
    $this->mTarget = NumericUrlCommon::$mBaseUrl . $path ;
    
  }
  
  /** */
  private function _redirect( $out, $url, $status = 307 ) {
    NumericUrlCommon::_debugLog( 20, __METHOD__ );
		$out->redirect( $url, $status );
  }

  /** */
  private static function _parseUrl( $url ) {
    $urlParts = parse_url( $url );
    if ( !( $urlParts && !isset( $urlParts['scheme'] ) ) ) {
      if ( substr( $url, 0, 2 ) === '//' ) {
        $urlParts = parse_url( WebRequest::detectProtocol() . ":{$url}" );
      }
      if ( !( $urlParts && $urlParts['scheme'] ) ) {
        return false;
      }
      unset( $urlParts['scheme'] );
    }
    if ( !( isset( $urlParts['host'] ) && isset( $urlParts['path'] ) ) ) {
      return false;
    }
    return $urlParts;
  }
 
  /** */
  private function _isValidToolPageQuery() {
    NumericUrlCommon::_debugLog( 20, __METHOD__ );
 
    if ( !$this->mTarget ) {
      $this->_buildTarget();
    }
 
    // validate the target URL
    $urlParts = self::_parseUrl( $this->mTarget );
    if ( !$urlParts ) {
      NumericUrlCommon::_debugLog( 10,
        sprintf('%s(): Invalid target URL: <%s>', __METHOD__, $this->mTarget )
      );
      return false;
    }
 
    return true;
  }

  /** */
	private function _toolPage() {
    NumericUrlCommon::_debugLog( 20, __METHOD__ );
 
    if ( !$this->_isValidToolPageQuery() ) {
      return $this->_unknownQueryPage() ;
    }
 
    $this->_showToolForm = true;
 
    $out = $this->getOutput();
		$out->setArticleRelated( false ); // bugbug: set accordingly
		$out->setRobotPolicy( 'noindex,nofollow' );
    
    $this->setHeaders();
    $out->addModuleStyles('ext.numericUrl.toolpage');
    
    $this->outputHeader( "{$this->_mp}-toolform-summary" );

    $form = $this->getForm();
    if ( $form->show() ) {
      $this->onSuccess();
    }

	}

  /** */
	private function _noSuchPage() {
    NumericUrlCommon::_debugLog( 20, __METHOD__ );
 
		$this->_prepareErrorPage()->showErrorPage(
      "{$this->_mp}-nosuchpage",
      "{$this->_mp}-nosuchpagetext"
    );
	}
  
  /** */
	private function _unknownQueryPage() {
    NumericUrlCommon::_debugLog( 20, __METHOD__ );

		$this->_prepareErrorPage()->showErrorPage(
      "{$this->_mp}-unknownquerypage",
      "{$this->_mp}-unknownquerypagetext"
    );
	}
  
  /** */
	private function _prepareErrorPage( $statusCode = 404 ) {
    NumericUrlCommon::_debugLog( 20, __METHOD__ );
    $out = $this->getOutput();
		$out->setArticleRelated( false );
		$out->setRobotPolicy( 'noindex,nofollow' );

    global $wgSend404Code;
		if ( ($statusCode != 404) || $wgSend404Code ) {
			$out->setStatusCode( $statusCode );
		}
    
    return $out;
	}
  
  /**
   * @static
   */
  /* ///
  public static function _initStatic() {
  }
  / *///

  /** i18n message prefix */
  private $_mp;

  /** */
  private $_showToolForm;
  
}
//NumericUrlSpecialPage::_initStatic();

/** @}*/
