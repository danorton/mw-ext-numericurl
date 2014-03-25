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
	public $query;

	/** */
	public $revision;

	/** */
	public $targetUrl;

	/** */
	public $numericUrlPath;

	/** */
	public $numericUrlExpiry;

	/** */
	public $scheme;

	/** For parameters and semantics, see FormSpecialPage::__construct(). */
	public function __construct() {
		NumericUrlCommon::_debugLog( 20, __METHOD__ );
		parent::__construct( NumericUrlCommon::SPECIAL_PAGE_TITLE );
		$this->_mp = parent::getMessagePrefix();
		$rq = $this->getRequest();

		$query = rawurldecode( $rq->getVal( NumericUrlCommon::$config->toolPageQueryParam ) );
		NumericUrlCommon::_debugLog( 20,
			sprintf('%s(): query=%s', __METHOD__, $query )
		);
		parse_str( $query, $args );
		$this->query = array_intersect_key( $args, self::$_queryParamKeys );
	}

	/** For parameters and semantics, see SpecialPage::execute(). */
	public function execute( $subPage ) {
		NumericUrlCommon::_debugLog( 20,
			sprintf('%s("%s")', __METHOD__, $subPage)
		);
		if ( $subPage == '' ) {
			if ( !NumericUrlCommon::isAllowed( 'follow', $this->getContext()->getUser() ) ) {
				throw new PermissionsError( NumericUrlCommon::getFullUserRightsName( 'follow' ) );
			}

			if ( !$this->_isValidToolPageRequest() ) {
				return $this->_unknownToolPageRequest() ;
			}
			$this->_toolPage();

		} else {
			return $this->_noSuchPage();
		}
	}

	/** For parameters and semantics, see FormSpecialPage::getFormFields(). */
	public function getFormFields() {
		NumericUrlCommon::_debugLog( 20, __METHOD__ );

		$context = $this->getContext();
		$user = $context->getUser();
		$hasCreateRights = NumericUrlCommon::isAllowed(
			array( 'create-basic', 'follow' ),
			$user );
		$canCreate = $hasCreateRights && !$this->numericUrlPath;

		$mp = "{$this->_mp}-toolform";
		$fields = array();

		$target = $this->targetUrl;
		$targetPrefixHtml = '';

		// remove our own base URL for brevity
		if (NumericUrlCommon::$baseUrl === substr( $target, 0, strlen( NumericUrlCommon::$baseUrl ) ) ) {
			$target = substr( $target, strlen( NumericUrlCommon::$baseUrl ) );
			$targetPrefixHtml = '&hellip;';
		}

		if ( isset( $this->title ) ) {
			$htmlTitle = htmlspecialchars( $this->title->getPrefixedText() );
			// only hyperlink the title if this is for the latest revision of the title
			if ( $canCreate && !( isset( $this->query['curid'] ) || $this->revision ) ) {
				$htmlTitle = Html::rawElement( 'a', array( 'href' => $target ), $htmlTitle );
			}
			$fields['target-title'] = array(
				'type' => 'info',
				'cssclass' => "$mp-target-title",
				'label-message' => "$mp-target-title",
				'raw' => true,
				'default' => $htmlTitle,
			);

			if ( $this->revision ) {
				$htmlTimestamp = $context->getLanguage()->userTimeAndDate( $this->revision->getTimestamp(), $user );
				if( $canCreate ) {
					$htmlTimestamp = Html::rawElement( 'a', array( 'href' => $target ), $htmlTimestamp );
				}
				$fields['oldid'] = array(
					'type' => 'info',
					'label-message' => "$mp-oldid",
					'raw' => true,
					'default' => $htmlTimestamp,
				);
			} elseif ( isset( $this->query['curid'] ) ) {
				$fields['curid'] = array(
					'type' => 'info',
					'label-message' => "$mp-curid",
					'default' => $this->query['curid'],
				);
			}
		}

		$htmlTarget = $targetPrefixHtml . htmlspecialchars( $target ) ;
		if( $canCreate ) {
				$htmlTarget = Html::rawElement( 'a',
					array(
						'href' => ( $canCreate ? $target : false ),
						'title' => $this->targetUrl,
					),
					$htmlTarget
				);
		}
		$fields['target'] = array(
			'type' => 'info',
			'cssclass' => "$mp-target",
			'label-message' => "$mp-target",
			'title' => $this->targetUrl,
			'raw' => true,
			'default' => $htmlTarget,
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
			'type' => 'submit',
			'default' => wfMessage("$mp-submit")->text(),
		);

		if ( !$canCreate ) {
			if ( ( !$this->numericUrlPath ) || ( !NumericUrlCommon::isAllowed( 'follow', $user ) ) ) {
				// We lack rights to create it or it exists, but we aren't allowed to see it
				// Simply report that the numeric URL is not available.
				$fields['unavailable'] = array(
					'type' => 'info',
					'cssclass' => "$mp-unavailable",
					'label-message' => "$mp-numeric",
					'default' => wfMessage("$mp-unavailable")->text(),
				);
			}

			// Disable the submit button if we can't create the numeric URL
			$fields['submit']['disabled'] = true;

		}
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
		var_dump(__METHOD__); exit(3);
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
		$form->addHiddenField( NumericUrlCommon::$config->toolPageQueryParam, wfArrayToCgi( $this->query ) );
	}

	private function _getTitle() {
		if ( ( !$this->titleObject ) && $this->query['title'] ) {
			$this->titleObject = Title::newFromText( $this->query['title'] );
		}
		return $this->titleObject;
	}

	/** */
	private function _buildTarget() {

		// We require the title text, at the very least
		// (This avoids problems with stale links if the title changes.)
		if ( !isset( $this->query['title'] ) ) {
			return;
		}

		// Fetch the title object for the given title text
		$this->title = Title::newFromText( $this->query['title'] );
		if ( !$this->title ) {
			return;
		}

		$query = array();

		// If it's an old revision or a page ID, we have to path through index.php
		if ( isset( $this->query['curid'] ) || isset( $this->query['oldid'] ) ) {
			if ( isset( $this->query['oldid'] ) ) {
				unset( $this->query['curid'] );
				$this->revision = Revision::newFromTitle( $this->title, $this->query['oldid'] );
				$query[] = "oldid={$this->query['oldid']}";
			} else {
				// curid must match the title's article ID
		    if ( $this->query['curid'] != $this->title->getArticleID() ) {
					return;
				}
				$query[] = "curid={$this->query['curid']}";
			}
			global $wgScript;
			$path = $wgScript;
		} else {
			// This is the URL for the latest revision of the article of the specified title
			$this->revision = null;
			global $wgArticlePath;
			$path = str_replace( '$1', $this->query['title'], $wgArticlePath );
		}

		// add the action
		if ( isset( $this->query['action'] ) ) {
			$query[] = "action={$this->query['action']}";
		}

		// add the query to the path
		if ( count($query) ) {
			$path .= '?' . implode( '&', $query );
		}

		// set the scheme
		$this->scheme = NumericUrlCommon::$baseScheme;

		// set the redirection target
		$this->targetUrl = NumericUrlCommon::$baseUrl . $path ;

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
	private function _isValidToolPageRequest() {
		NumericUrlCommon::_debugLog( 20, __METHOD__ );

		if ( !count( $this->query ) ) {
			// an empty query is A-OK
			return true;
		}

		if ( !$this->targetUrl ) {
			$this->_buildTarget();
			if ( !$this->targetUrl ) {
				return false;
			}
		}

		// validate the target URL
		$urlParts = NumericUrlCommon::parseUrl( $this->targetUrl );
		if ( !$urlParts ) {
			NumericUrlCommon::_debugLog( 10,
				sprintf('%s(): Invalid target URL: <%s>', __METHOD__, $this->targetUrl )
			);
			return false;
		}

		// bugbug actually look it up
		$this->numericUrlPath = null;

		return true;
	}

	/** */
	private function _toolPage() {
		NumericUrlCommon::_debugLog( 20, __METHOD__ );

		$this->_showToolForm = true;

		$out = $this->getOutput();
		$out->setArticleRelated( false ); // bugbug: set accordingly
		$out->setRobotPolicy( 'noindex,nofollow' );

		$this->setHeaders();
		$out->addModuleStyles('ext.numericUrl.toolpage');

		$this->outputHeader( "{$this->_mp}-toolform-summary" );


		$form = $this->getForm();
		// we manage our own submit button
		$form->suppressDefaultSubmit();

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
	private function _unknownToolPageRequest() {
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
  
  /** */
  private static $_queryParamKeys = array( 'title'=>null, 'curid'=>null, 'oldid'=>null, 'action'=>null, 'pageid'=>null ) ;

}
//NumericUrlSpecialPage::_initStatic();

/** @}*/
