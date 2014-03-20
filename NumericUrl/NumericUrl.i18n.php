<?php
/**
 * @ingroup Extensions
 * @{
 * NumericUrl extension i18n
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

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "This file is an extension to MediaWiki software and is not designed for standalone use.\n" ;
	die( 1 ) ;
}

// reset
$messages = array() ;
$specialPageAliases = array() ;

/** English
 * @author Daniel Norton
 */
$messages['en'] = array(
'numericurl'                => 'Numeric URLs',

'numericurl-toolbox-title'  => 'Generate a numeric URL to this page',
'numericurl-toolbox-text'   => 'Numeric URL',

'numericurl-nosuchpage'     => 'No such numeric URL',
'numericurl-nosuchpagetext' => '<strong>The requested numeric URL page does not exist.</strong>',

'numericurl-unknownquerypage'     => 'Unrecognized numeric URL request',
'numericurl-unknownquerypagetext' => '<strong>This page does not recognize the given request parameters.</strong>',

'numericurl-toolform'         => 'Numeric URL tool',
'numericurl-toolform-summary' => 'This is the Numeric URL tool page summary.',
'numericurl-toolform-legend'  => 'Create a numeric URL',
'numericurl-toolform-scope'   => 'Scope',
'numericurl-toolform-local'   => 'Local',
'numericurl-toolform-global'  => 'Global',
'numericurl-toolform-scheme'  => 'Scheme',
'numericurl-toolform-https'   => 'https://',  # DO NOT TRANSLATE
'numericurl-toolform-http'    => 'http://',   # DO NOT TRANSLATE
'numericurl-toolform-any-scheme'=> '//',      # DO NOT TRANSLATE
'numericurl-toolform-shared'  => 'Shared',
'numericurl-toolform-revision'=> 'Specific revision',
'numericurl-toolform-oldid'   => 'Revision:',
'numericurl-toolform-curid'   => 'Page ID:',
'numericurl-toolform-target-title' => 'Page title:',
'numericurl-toolform-target'  => 'Long URL:',
'numericurl-toolform-submit'  => 'Make numeric',
'numericurl-toolform-expiry'  => 'Expiration:',
'numericurl-toolform-numeric' => 'Numeric URL:',
) ;
$specialPageAliases['en'] = array(
MW_EXT_NUMERICURL_NAME => array( 'NumericUrl' ),
) ;

/** @}*/
