<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.0.17 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # All PHP code in this file is ©2000-2026 Jelsoft Enterprises Ltd. # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/liceNse.html # ||
|| #################################################################### ||
\*======================================================================*/

define('MAIL_INCLUDED', true);

/**
* Standard Mail Sending Object
*
* This class sends email from vBulletin using the PHP mail() function
*
* @package 		vBulletin
* @version		$Revision: 9776 $
* @date 		$Date: 2004-07-04 15:52:01 -0500 (Sun, 04 Jul 2004) $
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class Mail
{
	/**
	* Standard email parameters
	*
	* This is where we keep the most important stuff like who to send the mail to etc.
	*
	* @var	fromEmail
	* @var	toEmail
	* @var	subject
	* @var	headers
	* @var	message
	* @var	success
	*/
	var $fromEmail = '';
	var $toEmail = '';
	var $subject = '';
	var $headers = '';
	var $message = '';
	var $success = false;

	/**
	* Constructor
	*
	* @param	string	Destination Email Address
	* @param	string	Email Subject
	* @param	string	Email Message Body
	* @param	string	Extra Email Headers
	* @param	string	Webmaster (From) Email Address
	* @param	boolean	Require '-f' parameter to sendmail?
	*/
	function Mail($toemail, $subject, $message, $headers, $fromemail, $minusf = false)
	{
		if ($minusf)
		{
			$this->success = @mail($toemail, $subject, $message, trim($headers), "-f $fromemail");
		}
		else
		{
			$this->success = @mail($toemail, $subject, $message, trim($headers));
		}
	}
}

/**
* SMTP Mail Sending Object
*
* This class sends email from vBulletin using an SMTP wrapper
*
* @package 		vBulletin
* @version		$Revision: 9776 $
* @date 		$Date: 2004-07-04 15:52:01 -0500 (Sun, 04 Jul 2004) $
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class SmtpMail extends Mail
{
	/**
	* SMTP email parameters
	*
	* Variables used to work with the SMTP protocol.
	*
	* @var    smtpHost
	* @var    smtpPort
	* @var    smtpUser
	* @var    smtpPass
	* @var    smtpSocket
	*/
	var $smtpHost = "smtp.example.com";
	var $smtpPort = 25;
	var $smtpUser = false;
	var $smtpPass = false;
	var $smtpSocket = null;

	/**
	* Constructor
	*
	* @param	string	Destination Email Address
	* @param	string	Email Subject
	* @param	string	Email Message Body
	* @param	string	Extra Email Headers
	* @param	string	Webmaster (From) Email Address
	* @param	boolean	Require '-f' parameter to sendmail?
	*/
	function SmtpMail($toemail, $subject, $message, $headers, $fromemail, $minusf = false)
	{
		// this class doesn't handle BCC or CC at the moment
		$this->toEmail = $toemail;
		$this->subject = $subject;
		$this->message = $message;
		$this->headers = trim($headers);
		
		$matches = array();
		
		preg_match('#From: ".*" <(.*)>#siU', $this->headers, $matches);
		if (!empty($matches))
		{
			$this->fromEmail = "<$matches[1]>";
		}
		else
		{
			$this->fromEmail = "<$fromemail>";
		}
		
		$this->success = $this->send();
	}
	
	/**
	* Sends instruction to SMTP server
	*
	* @param	string	Message to be sent to server
	* @param	mixed	Message code expected to be returned or false if non expected
	*
	* @return	boolean	Returns false on error
	*/
	function sendMessage($msg, $expectedResult = false)
	{
		if ($msg !== false && !empty($msg))
		{
			fputs($this->smtpSocket, $msg . "\r\n");
		}
		if ($expectedResult !== false)
		{
			$result = '';
			while ($line = fgets($this->smtpSocket, 1024))
			{
				$result .= $line;
				if (substr($result, 3, 1) == ' ')
				{
					break;
				}
			}
			return (intval(substr($result, 0, 3)) == $expectedResult);
		}
		return true;
	}
	
	/**
	* Triggers PHP warning on error
	*
	* @param	string	Error message to be shown
	*
	* @return	boolean	Always returns false (error)
	*/
	function errorMessage($msg)
	{
		trigger_error($msg, E_USER_WARNING);
		return false;
	}
	
	/**
	* Attempts to send email based on parameters passed into the constructor
	*
	* @return	boolean	Returns false on error
	*/
	function send()
	{
		$this->smtpSocket = fsockopen($this->smtpHost, intval($this->smtpPort), $errno, $errstr, 30);
		if ($this->smtpSocket)
		{
			if (!$this->sendMessage(false, 220))
			{
				return errorMessage("Unexpected response from SMTP server");
			}

			if (!$this->sendMessage("HELO " . $this->smtpHost, 250))
			{
				return errorMessage("Unexpected response from SMTP server");
			}

			if ($this->smtpUser AND $this->smtpPass)
			{
				if ($this->sendMessage("AUTH LOGIN", 334))
				{
					if (!$this->sendMessage(base64_encode($this->smtpUser), 334) OR !$this->sendMessage(base64_encode($this->smtpPass), 235))
					{
						return errorMessage("Authorization to the SMTP server failed");
					}
				}
			}

			if (!$this->sendMessage("MAIL FROM:" . $this->fromEmail, 250))
			{
				return errorMessage("Unexpected response from SMTP server");			
			}

			// we could have multiple addresses since a few people might expect this to be the same as PHP
			$addresses = explode(',', $this->toEmail);
			foreach ($addresses AS $address)
			{
				if (!$this->sendMessage("RCPT TO:<" . trim($address) . ">", 250))
				{
					return errorMessage("Unexpected response from SMTP server");			
				}
			}
			if ($this->sendMessage("DATA", 354))
			{
				$this->sendMessage("To: " . $this->toEmail, false);
				$this->sendMessage($this->headers, false);
				$this->sendMessage("Subject: " . $this->subject, false);
				$this->sendMessage($this->message, false);
			}
			else
			{
				return errorMessage("Unexpected response from SMTP server");
			}

			if (!$this->sendMessage(".", 250))
			{
				return errorMessage("Unexpected response from SMTP server");			
			}

			if (!$this->sendMessage("QUIT", 221))
			{
				return errorMessage("Unexpected response from SMTP server");				
			}

			fclose($this->smtpSocket);
			return true;
		}
		else
		{
			return errorMessage("Unable to connect to SMTP server");
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9776 $
|| ####################################################################
\*======================================================================*/
?>
