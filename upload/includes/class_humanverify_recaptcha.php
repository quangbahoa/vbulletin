<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2023 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . '/includes/class_vurl.php');

/**
* Human Verification class for reCAPTCHA Verification (http://recaptcha.net)
*
* @package 		vBulletin
* @version		$Revision: 93074 $
* @date 		$Date: 2017-02-22 21:13:10 -0800 (Wed, 22 Feb 2017) $
*
*/
class vB_HumanVerify_Recaptcha extends vB_HumanVerify_Abstract
{
	/**
	* Constructor
	*
	* @return	void
	*/
	function __construct(&$registry)
	{
		parent::__construct($registry);
	}

	/**
	* Verify is supplied token/reponse is valid
	*
	*	@param	array	Values given by user 'input' and 'hash'
	*
	* @return	bool
	*/
	function verify_token($input)
	{
		$this->registry->input->clean_array_gpc('p', array(
			'recaptcha_challenge_field' => TYPE_STR,
			'recaptcha_response_field'  => TYPE_STR,
		));

		if ($this->delete_token($input['hash']) AND $this->registry->GPC['recaptcha_response_field'] AND $this->registry->GPC['recaptcha_challenge_field'])
		{	// Contact recaptcha.net
			$private_key = ($this->registry->options['hv_recaptcha_privatekey'] ? $this->registry->options['hv_recaptcha_privatekey'] : '6LfHsgMAAAAAACYsFwZz6cqcG-WWnfay7NIrciyU');
			$query = array(
				'privatekey=' . urlencode($private_key),
				'remoteip=' . urlencode(IPADDRESS),
				'challenge=' . urlencode($this->registry->GPC['recaptcha_challenge_field']),
				'response=' . urlencode($this->registry->GPC['recaptcha_response_field']),
			);

			$vurl = new vB_vURL($this->registry);
			$vurl->set_option(VURL_URL, 'https://www.google.com/recaptcha/api/verify');
			$vurl->set_option(VURL_USERAGENT, 'vBulletin ' . FILE_VERSION);
			$vurl->set_option(VURL_POST, 1);
			$vurl->set_option(VURL_POSTFIELDS, implode('&', $query));
			$vurl->set_option(VURL_RETURNTRANSFER, 1);
			$vurl->set_option(VURL_CLOSECONNECTION, 1);

			if (($result = $vurl->exec()) === false)
			{
				$this->error = 'humanverify_recaptcha_unreachable';
				return false;
			}
			else
			{
				$result = explode("\n", $result);
				if ($result[0] === 'true')
				{
					return true;
				}

				switch ($result[1])
				{
					case 'invalid-site-public-key':
						$this->error = 'humanverify_recaptcha_publickey';
						break;
					case 'invalid-site-private-key':
						$this->error = 'humanverify_recaptcha_privatekey';
						break;
					case 'invalid-referrer':
						$this->error = 'humanverify_recaptcha_referrer';
						break;
					case 'invalid-request-cookie':
						$this->error = 'humanverify_recaptcha_challenge';
						break;
					case 'verify-params-incorrect':
						$this->error = 'humanverify_recaptcha_parameters';
						break;
					default:
						$this->error = 'humanverify_image_wronganswer';
				}

				return false;
			}
		}
		else
		{
			$this->error = 'humanverify_image_wronganswer';
			return false;
		}
	}

	/**
	 * Returns the HTML to be displayed to the user for Human Verification
	 *
	 * @param	string	Passed to template
	 *
	 * @return 	string	HTML to output
	 *
	 */
	function output_token($var_prefix = 'humanverify')
	{
		global $vbphrase, $stylevar, $show;
		$vbulletin =& $this->registry;

		$humanverify = $this->generate_token();

		if (REQ_PROTOCOL === 'https')
		{
			$show['recaptcha_ssl'] = true;
		}

		$humanverify['publickey'] = ($this->registry->options['hv_recaptcha_publickey'] ? $this->registry->options['hv_recaptcha_publickey'] : '6LfHsgMAAAAAAMVjkB1nC_nI5qfAjVk0qxz4VtPV');
		$humanverify['theme'] = $this->registry->options['hv_recaptcha_theme'];

		if (preg_match('#^([a-z]{2})-?#i', $stylevar['languagecode'], $matches))
		{
			$humanverify['langcode'] = strtolower($matches[1]);
		}

		eval('$output = "' . fetch_template('humanverify_recaptcha') . '";');

		return $output;
	}

	/**
	* expected answer - with this class, we don't know the answer
	*
	* @return	string
	*/
	function fetch_answer()
	{
		return '';
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 93074 $
|| # $Date: 2017-02-22 21:13:10 -0800 (Wed, 22 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
