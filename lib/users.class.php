<?php

/**
* User Class
* Contains function related to users
* @author Mihai Ionut Vilcu (ionutvmi@gmail.com)
* 11 - May - 2013
* 
*/

class User {

	/**
	 * Stores the object of the mysql class
	 * @var object 
	*/
	var $db; 
	/**
	 * Stores the users details encoded with htmlentities()
	 * @var object
	*/
	var $filter;
	/**
	 * stores the user data without any filter
	 * @var object
	*/
	var $data;
	/**
	 * contains the group details about the current user
	 * @var object
	 */
	var $group;

	function __construct($db) {
		$this->db = $db;
		$this->data = new stdClass();
		$this->filter = array();


		if($this->islg()){ // set some vars
			$this->data = $this->grabData($_SESSION['user']);
			
			if($this->data) {

				foreach ($this->data as $k => $v) {
					$this->filter[$k] = htmlentities($v, ENT_QUOTES);
				}

				$this->filter = (object)$this->filter; // we make it an object
			} else // in case the user was deleted
				$this->logout();

		} 



		if(!$this->islg()) { // we need to call it again in case the user was deleted while logged in

			// we set some default values
			// by doing this we won't have to do an extra check to display user or `guest` on the site
			$this->filter = new stdClass();
			$this->data = new stdClass();
			$this->filter->username = "Guest";
			$this->data->userid = 0;
			$this->data->groupid = 1;
			$this->data->banned = 0;
		}

		$this->group = $this->getGroup();
	}
	/**
	 * Checks if user is logged in
	 * @return bool
	*/
	function islg() {
		if(isset($_SESSION['user']))
			return true;
		return false;
	}
	/**
	 * Gets the url to the avatar of the user
	 * @param  int $userid the user id if none given it will take the current user
	 * @return string          url to the image
	 */
	function getAvatar($userid = 0, $size = null) {
		global $set;
		if($size)
			$size = "?s=$size";
		if(!$userid) {
			if($this->data->showavt)
				return "http://www.gravatar.com/avatar/".md5($this->data->email).$size;
			else
				return "$set->url/img/default-avatar.png";
		}
		$u = $this->db->getRow("SELECT `email`, `showavt` FROM `".MLS_PREFIX."users` WHERE `userid` = ?i", $userid);
		if($u->showavt)	
			return "http://www.gravatar.com/avatar/".md5($u->email).$size;
		else	
			return "$set->url/img/default-avatar.png";

	}

	/**
	 * get the group details about the user
	 * @param  int $userid the user id if none given it will take the current user
	 * @return object          the object with the group details
	 */
	function getGroup($userid = 0) {

		if(!$userid)
			return $this->db->getRow("SELECT * FROM `".MLS_PREFIX."groups` WHERE `groupid` = ?i", $this->data->groupid);

		$u = $this->db->getRow("SELECT `groupid` FROM `".MLS_PREFIX."users` WHERE `userid` = ?i", $userid);
		return $this->db->getRow("SELECT * FROM `".MLS_PREFIX."groups` WHERE `groupid` = ?i", $u->groupid);
	}
	/**
	 * get the ban details about the user
	 * @param  int $userid the user id if none given it will take the current user
	 * @return object          the object with the ban details
	 */
	function getBan($userid = 0) {

		if(!$userid)
			$userid = $this->data->userid;
		return $this->db->getRow("SELECT * FROM `".MLS_PREFIX."banned` WHERE `userid` = ?i", $userid);
	}

	/**
	 * shows the username of the used formated according to the group
	 * @param  integer $userid the user id if none provided it will use the current one
	 * @return string          formated username
	 */
	function showName($userid = 0) {

		if(!$userid)
			if($this->data->banned)
				return "<strike>".$this->filter->display_name."</strike>";
			else	
				return "<font color='".$this->group->color."'>".$this->filter->display_username."</font>";

		$u = $this->db->getRow("SELECT `display_name`,`banned` FROM `".MLS_PREFIX."users` WHERE `userid` = ?i", $userid);
		$group = $this->getGroup($userid);
	
		if($u->banned)
			return "<strike>".htmlentities($u->display_name, ENT_QUOTES)."</strike>";		
		else	
			return "<font color='".$group->color."'>".htmlentities($u->display_name, ENT_QUOTES)."</font>";		
	}



	/**
	 * checks if `userid2` has the privilege to act on/over userid
	 * @param  integer  $userid  the user acted on
	 * @param  integer $userid2 the user who wants to act
	 * @return boolean          true if userid2 can
	 */
	function hasPrivilege($userid, $userid2 = 0) {
		
		$group = $this->getGroup($userid);		
		
		if(!$userid2) {
			if(($this->group->type >=3) || ($this->group->type > $group->type) || (($this->group->type == $group->type) && ($this->group->priority > $group->priority)))
				return TRUE;
			return FALSE;
		}

		$group2 = $this->getGroup($userid2);		
		
		if(($group2->type > $group->type) || (($group2->type == $group->type) && ($group2->priority > $group->priority)))
			return TRUE;
		return FALSE;



	}
	/**
	 * checks if the provoded id is valid
	 * @param  integer $userid id to be checked
	 * @return boolean          true if exists
	 */
	function exists($userid) {
		if($this->db->getRow("SELECT `userid` FROM `".MLS_PREFIX."users` WHERE `userid` = ?i", $userid))
			return TRUE;
		return FALSE;
	}
	/**
	 * grabs the data about the user
	 * @param  integer $userid the id to grab data for
	 * @return object         data about the specified id
	 */
	function grabData($userid) {
		return $this->db->getRow("SELECT * FROM `".MLS_PREFIX."users` WHERE `userid` = ?i", $userid);
	}
	/**
	 * Checks if a user is admin
	 * @param  integer $userid user to be checked if none provided we take the current user
	 * @return boolean         true if yes
	 */
	function isAdmin($userid = 0) {
		if(!$userid)
			if($this->group->type >= 3)
				return TRUE;
			else
				return FALSE;

		$u = $this->db->getRow("SELECT `username`,`banned` FROM `".MLS_PREFIX."users` WHERE `userid` = ?i", $userid);
		$group = $this->getGroup($userid);
		if($group->type >= 3)
			return TRUE;
		return FALSE;
	}
	/**
	 * logges out the current user
	 * @return void 
	 */
	function logout() {
		global $set;
		session_unset('user');
		$path_info = parse_url($set->url);
		setcookie("user", 0, time() - 3600 * 24 * 30, $path_info['path']); // delete
		setcookie("pass", 0, time() - 3600 * 24 * 30, $path_info['path']); // delete
	}


}

 
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * PHP Google two-factor authentication module.
 *
 * See http://www.idontplaydarts.com/2011/07/google-totp-two-factor-authentication-for-php/
 * for more details
 *
 * @author Phil
 **/

class Google2FA {

	const keyRegeneration 	= 30;	// Interval between key regeneration
	const otpLength		= 6;	// Length of the Token generated

	private static $lut = array(	// Lookup needed for Base32 encoding
		"A" => 0,	"B" => 1,
		"C" => 2,	"D" => 3,
		"E" => 4,	"F" => 5,
		"G" => 6,	"H" => 7,
		"I" => 8,	"J" => 9,
		"K" => 10,	"L" => 11,
		"M" => 12,	"N" => 13,
		"O" => 14,	"P" => 15,
		"Q" => 16,	"R" => 17,
		"S" => 18,	"T" => 19,
		"U" => 20,	"V" => 21,
		"W" => 22,	"X" => 23,
		"Y" => 24,	"Z" => 25,
		"2" => 26,	"3" => 27,
		"4" => 28,	"5" => 29,
		"6" => 30,	"7" => 31
	);

	/**
	 * Generates a 16 digit secret key in base32 format
	 * @return string
	 **/
	public static function generate_secret_key($length = 16) {
		$b32 	= "234567QWERTYUIOPASDFGHJKLZXCVBNM";
		$s 	= "";

		for ($i = 0; $i < $length; $i++)
			$s .= $b32[rand(0,31)];

		return $s;
	}

	/**
	 * Returns the current Unix Timestamp devided by the keyRegeneration
	 * period.
	 * @return integer
	 **/
	public static function get_timestamp() {
		return floor(microtime(true)/self::keyRegeneration);
	}

	/**
	 * Decodes a base32 string into a binary string.
	 **/
	public static function base32_decode($b32) {

		$b32 	= strtoupper($b32);

		if (!preg_match('/^[ABCDEFGHIJKLMNOPQRSTUVWXYZ234567]+$/', $b32, $match))
			throw new Exception('Invalid characters in the base32 string.');

		$l 	= strlen($b32);
		$n	= 0;
		$j	= 0;
		$binary = "";

		for ($i = 0; $i < $l; $i++) {

			$n = $n << 5; 				// Move buffer left by 5 to make room
			$n = $n + self::$lut[$b32[$i]]; 	// Add value into buffer
			$j = $j + 5;				// Keep track of number of bits in buffer

			if ($j >= 8) {
				$j = $j - 8;
				$binary .= chr(($n & (0xFF << $j)) >> $j);
			}
		}

		return $binary;
	}

	/**
	 * Takes the secret key and the timestamp and returns the one time
	 * password.
	 *
	 * @param binary $key - Secret key in binary form.
	 * @param integer $counter - Timestamp as returned by get_timestamp.
	 * @return string
	 **/
	public static function oath_hotp($key, $counter)
	{
	    if (strlen($key) < 8)
		throw new Exception('Secret key is too short. Must be at least 16 base 32 characters');

	    $bin_counter = pack('N*', 0) . pack('N*', $counter);		// Counter must be 64-bit int
	    $hash 	 = hash_hmac ('sha1', $bin_counter, $key, true);

	    return str_pad(self::oath_truncate($hash), self::otpLength, '0', STR_PAD_LEFT);
	}

	/**
	 * Verifys a user inputted key against the current timestamp. Checks $window
	 * keys either side of the timestamp.
	 *
	 * @param string $b32seed
	 * @param string $key - User specified key
	 * @param integer $window
	 * @param boolean $useTimeStamp
	 * @return boolean
	 **/
	public static function verify_key($b32seed, $key, $window = 4, $useTimeStamp = true) {

		$timeStamp = self::get_timestamp();

		if ($useTimeStamp !== true) $timeStamp = (int)$useTimeStamp;

		$binarySeed = self::base32_decode($b32seed);

		for ($ts = $timeStamp - $window; $ts <= $timeStamp + $window; $ts++)
			if (self::oath_hotp($binarySeed, $ts) == $key)
				return true;

		return false;

	}

	/**
	 * Extracts the OTP from the SHA1 hash.
	 * @param binary $hash
	 * @return integer
	 **/
	public static function oath_truncate($hash)
	{
	    $offset = ord($hash[19]) & 0xf;

	    return (
	        ((ord($hash[$offset+0]) & 0x7f) << 24 ) |
	        ((ord($hash[$offset+1]) & 0xff) << 16 ) |
	        ((ord($hash[$offset+2]) & 0xff) << 8 ) |
	        (ord($hash[$offset+3]) & 0xff)
	    ) % pow(10, self::otpLength);
	}



}
