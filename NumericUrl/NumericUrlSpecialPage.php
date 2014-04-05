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
 * Pages for @b Special:NumericUrl, for managing numeric URL redirects.
 */
class NumericUrlSpecialPage extends FormSpecialPage {

	/** */
	public $targetUrl;

	/** */
	public $numericUrlPath;

	/** */
	public $numericUrlExpiry;

	/** */
	public $scheme;

	/** For parameters and semantics, see FormSpecialPage::__construct().
	 *
	 * There's a very good chance that our page will not be displayed after construction,
	 * so we shouldn't waste much time here. Most notably, Special:SpecialPages creates
	 * an instance of *each* special page object simply to determine which ones are
	 * available to the current user, invoking isListed(), isRestricted() and userCanExecute().
	 * (If the page is available to the current user, Special:SpecialPages then invokes
	 * a few other property getters.)
	 */
	public function __construct() {
		NumericUrlCommon::_debugLog( 20, __METHOD__ );
		parent::__construct(
			NumericUrlCommon::SPECIAL_PAGE_TITLE,
			NumericUrlCommon::getFullUserRightsName( 'view-basic' )
		);
		$this->_mp = parent::getMessagePrefix();
		$this->_mpActivePage = $this->_mp;
		$this->_rq = $this->getRequest();
		$this->_query = $this->_rq->getValues();
	}

	/** For parameters and semantics, see SpecialPage::execute(). */
	public function execute( $subPage ) {
		NumericUrlCommon::_debugLog( 20,
			sprintf( '%s("%s")', __METHOD__, $subPage)
		);
		$this->setHeaders();
		$this->checkPermissions();

		// If the Wiki is in read-only mode, only allow GET
		if ( ( $this->getRequest()->getMethod() !== 'GET' ) && wfReadOnly() ) {
			throw new ReadOnlyError;
		}

		if ( $subPage == '' ) {
			if ( !$this->_isValidToolPageRequest() ) {
				$this->_unknownToolPageRequest() ;
				return;
			}
			$this->_toolPage();

		} else {
			$this->_noSuchPage();
		}

	}

	/**
	 * For basic parameters and semantics, see SpecialPage::checkReadOnly.
	 *
	 * We nullify this method and manage read-only mode separately, depending on the
	 * user and the request.
	 */
	public function checkReadOnly() {
	}

	/** For parameters and semantics, see FormSpecialPage::getFormFields(). */
	public function getFormFields() {
		NumericUrlCommon::_debugLog( 20, __METHOD__ );

		$context = $this->getContext();
		$user = $context->getUser();

		$hasBasicCreateRights    = NumericUrlCommon::isAllowed( array( 'create-basic' ), $user );
		$hasNonBasicCreateRights = NumericUrlCommon::isAllowed( array( 'create-local' ), $user );

		$canSubmitThis = $this->_mapInstance->isBasic() && $hasBasicCreateRights;

		$mp = $this->getMessagePrefix();
		$fields = array();

		$target = $this->_mapInstance->getText();
		if ( $this->_mapInstance->isLocal() ) {
			$target = $this->_mapInstance->getLocalText();
		}

		// display the title, if we have one
		if ( $this->_localTitle ) {
			$htmlTitle = htmlspecialchars( $this->_localTitle->getPrefixedText() );
			// only hyperlink the title if this URL tracks the current-latest revision of the title
			if ( !( $this->_getVal( 'curid' ) || $this->_getVal( 'oldid' ) ) ) {
				$htmlTitle = Html::rawElement(
					'a',
					array(
						'href' => $target,
						'target' => '_blank',
						'title' => wfMessage( "$mp-target-title-link-title" )->text(),
					),
					$htmlTitle );
			}
			$fields['target-title'] = array(
				'type' => 'info',
				'cssclass' => "$mp-target-title",
				'label-message' => "$mp-target-title-label",
				'raw' => true,
				'default' => $htmlTitle,
			);

			// display the revision timestamp if this link represents a specific revision
			if ( $this->_mapInstance->getQueryValue('oldid') ) {
				$revision = Revision::newFromTitle( $this->_localTitle, $this->_mapInstance->getQueryValue('oldid') );
				//var_dump($revision); exit(3);
				if ( $revision ) {
					$htmlTimestamp = $context->getLanguage()->userTimeAndDate( $revision->getTimestamp(), $user );
					if( $hasNonBasicCreateRights ) {
						$htmlTimestamp = Html::rawElement( 'a', array( 'href' => $target ), $htmlTimestamp );
					}
					$fields['oldid'] = array(
						'type' => 'info',
						'label-message' => "$mp-oldid",
						'raw' => true,
						'default' => $htmlTimestamp,
					);
				}
			} elseif ( ( $this->_getVal( 'curid' ) !== null ) ) {
				$fields['curid'] = array(
					'type' => 'info',
					'label-message' => "$mp-curid",
					'default' => $this->_getVal( 'curid' ),
				);
			}
		}

		$htmlTarget = htmlspecialchars( $target ) ;
		// If the user can't create non-basic URLs, just display the URL text
		$fields['target'] = array(
			'type' => 'text',
			'cssclass' => "$mp $mp-target" . ( $hasNonBasicCreateRights ? '' : " $mp-input-readonly" ),
			'label-message' => "$mp-target",
			'title' => "{$this->_mapInstance}",
			'raw' => true,
			'readonly' => !$hasNonBasicCreateRights,
			'default' => $target,
		);

		// display scheme selection if our host isn't scheme-specific
		if ( NumericUrlCommon::$baseScheme === NumericUrlCommon::URL_SCHEME_FOLLOW ) {
			$scheme = array(
				NumericUrlCommon::URL_SCHEME_HTTPS  => "$mp-https",
				NumericUrlCommon::URL_SCHEME_HTTP   => "$mp-http",
				NumericUrlCommon::URL_SCHEME_FOLLOW => "$mp-any-scheme",
			);
			$fields['scheme'] = array(
				'type' => 'select',
				'label-message' => "$mp-scheme",
				'options' => array(),
				'default' => $this->scheme,
			);
			// Store the scheme options with i18n text
			foreach( $scheme as $k => $v ) {
				$v = $this->msg( $v )->text();
				$fields['scheme']['options'][$v] = $k;
			}
		}

		if ( $this->numericUrlPath ) {
			$fields['numeric'] = array(
				'type' => 'info',
				'label-message' => "$mp-numeric",
				'readonly' => true,
				'default' => 'default?',
			);

			$fields['expiry'] = array(
				'type' => 'info',
				'cssclass' => "$mp-expiry",
				'label-message' => "$mp-expiry",
				'default' => strftime( '%Y-%m-%d %H:%M:%SZ', time() + 3600*24*7*13 ), // 91 days
			);
		}

		$fields['unavailable'] = array();

		// create our submit button
		$fields['submit'] = array(
			//'cssclass' => "$mp-nodisplay",
			'type' => 'submit',
			'default' => wfMessage( "$mp-submit" )->text(),
		);

		if ( !$hasNonBasicCreateRights ) {
			if ( ( !$this->numericUrlPath ) || ( !NumericUrlCommon::isAllowed( 'view-basic', $user ) ) ) {
				// We lack rights to create it or it exists, but we aren't allowed to see it
				// Simply report that the numeric URL is not available.
				$fields['unavailable'] = array(
					'type' => 'info',
					'cssclass' => "$mp-unavailable",
					'label-message' => "$mp-numeric",
					'default' => wfMessage( "$mp-unavailable" )->text(),
				);
			}

			// Disable the submit button if we can't create the numeric URL
			$fields['submit']['disabled'] = true;
			//$fields['submit']['cssclass'] .= "$mp-nodisplay";

		}
		$fields['submit']['disabled'] = !$canSubmitThis;

		if ( !count( $fields['unavailable'] ) ) {
			unset( $fields['unavailable'] );
		}

		return $fields;
	}

	/** For parameters and semantics, see FormSpecialPage::onSuccess(). */
	public function onSubmit( array $data ) {
		NumericUrlCommon::_debugLog( 20, __METHOD__ );
		return false;
	}

	/** For parameters and semantics, see FormSpecialPage::onSuccess(). */
	public function onSuccess() {
		NumericUrlCommon::_debugLog( 20, __METHOD__ );
    echo htmlspecialchars( sprintf( "<br> %s:%u: ***DEBUG EXIT ***<br>\n", __METHOD__, __LINE__ ) ); exit( 3 );
	}

	/** For parameters and semantics, see SpecialPage::getDescription(). */
	public function getDescription() {
		if ( $this->_mp !== $this->_mpActivePage ) {
      return $this->msg( $this->_mpActivePage )->text();
		} else {
			return parent::getDescription();
		}
	}

	/** */
	public function getMessagePrefix() {
    return $this->_mpActivePage;
	}

	/** For parameters and semantics, see FormSpecialPage::alterForm(). */
	protected function alterForm( HTMLForm $form ) {
		NumericUrlCommon::_debugLog( 20, __METHOD__ );
		if ( $this->_getVal( 'title' ) && ( $this->_getVal( 'curid' ) || $this->_getVal( 'oldid' ) ) ) {
			$form->addHiddenField( NumericUrlCommon::$config->queryPrefix . 'title', $this->_getVal( 'title' ) );
		}
	}

	/** */
	private function _getVal( $name, $default = null ) {
		return $this->_rq->getVal( NumericUrlCommon::$config->queryPrefix . $name, $default );
	}

	/** */
  /*///
	private function _getTitle() {
		if ( ( !$this->titleObject ) && $this->_getVal( 'title' ) ) {
			$this->titleObject = Title::newFromText( $this->_getVal( 'title' ) );
		}
		return $this->titleObject;
	}
  //*///

	/** */
  /*///
	private function _redirect( $out, $url, $status = 307 ) {
		NumericUrlCommon::_debugLog( 20, __METHOD__ );
		$out->redirect( $url, $status );
	}
  //*///

	/**
	 * Validate tool page request.
	 *
	 * Simply validate the request syntax. The URL in the request isn't necessarily valid.
	 */
	private function _isValidToolPageRequest() {
		NumericUrlCommon::_debugLog( 20, __METHOD__ );

    // an empty query is A-OK
		if ( !count( $this->_query ) ) {
			return true;
		}

		$this->_mapInstance = false;

		// if it's not empty, it must have a url parameter
		$targetUrl = $this->_getVal( 'url' );
		if ( !$targetUrl ) {
			// no URL at all
			return false;
		}

		$this->_mapInstance = new NumericUrlMapInstance( $targetUrl );

		return true;
	}

	/**
	 * Display the tool page.
	 */
	/** */
	private function _toolPage() {
		NumericUrlCommon::_debugLog( 20, __METHOD__ );

    $this->_mpActivePage = "{$this->_mp}-toolform";

		$out = $this->getOutput();
		$out->setRobotPolicy( 'noindex,nofollow' );

		//$this->setHeaders();
		$out->addModuleStyles( 'ext.numericUrl.toolpage' );

		$this->outputHeader( $this->getMessagePrefix() . "-summary" );

		// fetch a title for local URLs
		if ( $this->_mapInstance ) {
			$this->_localTitle = $this->_mapInstance->getTitle();
		} else {
			$this->_localTitle = null;
		}
		$form = $this->getForm();
		$form->setTableId( $mp = $this->getMessagePrefix() . '_toolform' );
		// we manage our own submit button
		$form->suppressDefaultSubmit();

		if ( $form->show() ) {
			$this->onSuccess();
		}
	}

	/** */
	private function _noSuchPage() {
		NumericUrlCommon::_debugLog( 20, __METHOD__ );
		$mp = $this->getMessagePrefix();
		$this->_prepareErrorPage()->showErrorPage(
			"$mp-nosuchpage",
			"$mp-nosuchpagetext"
		);
	}

	/** */
	private function _unknownToolPageRequest() {
		NumericUrlCommon::_debugLog( 20, __METHOD__ );
		$mp = $this->getMessagePrefix();
		$this->_prepareErrorPage()->showErrorPage(
			"$mp-unknownquerypage",
			"$mp-unknownquerypagetext"
		);
	}

	/** */
	private function _prepareErrorPage( $statusCode = 404 ) {
		NumericUrlCommon::_debugLog( 20, __METHOD__ );
		$out = $this->getOutput();
		$out->setArticleRelated( false );
		$out->setRobotPolicy( 'noindex,nofollow' );

		global $wgSend404Code;
		if ( ( $statusCode != 404 ) || $wgSend404Code ) {
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

	/** i18n message prefix for the active (sub-)page */
	private $_mpActivePage;

	/** WebRequest */
	private $_rq;

	/** */
	private $_localTitle;

	/** URL map instance. */
	private $_mapInstance;

	/** */
	public $_query;

	/** */
	private static $_queryParamKeys = array( 'title'=>null, 'curid'=>null, 'oldid'=>null, 'action'=>null, 'pageid'=>null ) ;

}
//NumericUrlSpecialPage::_initStatic();

/** @}*/
