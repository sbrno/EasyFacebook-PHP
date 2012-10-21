<?php
/*
 * EasyFacebook PHP
 *
 * This is an easy to use Facebook Api extension for faster and simplier application development.
 * {@link http://www.vavatukina.com}
 * 
 * @author     Sandor Huszagh
 * @copyright  (c) 2011 - Sandor Huszagh
 * @version    1.1
 * @license http://opensource.org/licenses/gpl-3.0.html GNU General Public License v3
 */
 
class EasyFacebook {
    
    private $appId;
    private $appSec;
	private $fb;
	public $user;
	public $info;
	private $album;
	
    public function __construct($appId,$appSec) {
		$this->appId = $appId;
		$this->appSec = $appSec;
		$this->fb = new Facebook(array(
		  'appId'  => $appId,
		  'secret' => $appSec,
		  'cookie' => true,
		));
		$this->user = null;
    }
	
	/**
	 * Check the page liked or not
	 * @return bool
	 */

	public function isLiked() {
	    if (isset($_REQUEST['signed_request'])) {
		  $encoded_sig = null;
		  $payload = null;
		  list($encoded_sig, $payload) = explode('.', $_REQUEST['signed_request'], 2);
		  $sig = base64_decode(strtr($encoded_sig, '-_', '+/'));
		  $data = json_decode(base64_decode(strtr($payload, '-_', '+/'), true));
		  return $data->page->liked;
		}
		return false;
	}
	
	/**
	 * Load the Facebook App class and ask for permissions
	 *
	 * Usage:
	 *
	 * $fb = new faceApp('API Key','Secret Key');
	 * $fb->userLogin(
	 *	array(
	 *		'scope'        => 'Permissions for your application',
	 *		'redirect_uri' => 'Redirect url. The iframe application's url'
	 *	)
	 * );
	 */

	public function userLogin($details) {
		$fb = $this->fb;
	    $this->user = $fb->getUser();   
	
		$loginUrl = $fb->getLoginUrl($details);

		if ($this->user) {
		  try {
			$this->info = $fb->api('/me');
		  } catch (FacebookApiException $e) {
			$this->showError($e);
			$this->user = null;
		  }
		}

		if (!$this->user) {
			echo "<script type='text/javascript'>top.location.href = '".$loginUrl."';</script>";
			exit;
		}
	}

	/**
	 * String replace function to put user's name in a string. Use %name% in the place where you want put the user's name.
	 * 
	 * Usage:
	 *
	 * $this->appendName('Some text and here is the user's name: %name%');
	 *
	 * @return string the text where the %name% changed to the user's name
	*/
	
	public function appendName($string) {
		if ($this->user) {
			$string = str_replace("%name%", $this->info["name"], $string);
		} elseif (isset($_SESSION['name'])) {
			$string = str_replace("%name%", $_SESSION['name'], $string);
		} else {
			return false;
		}
		return $string;
	}
	
	/**
	 * Create a photo album
	 * 
	 * Needed permissions: publish_stream, photo_upload, user_photos 
	 *
	 * Usage: 
	 *
	 * $fb->createPhotoAlbum(
	 *	array(
	 *		'message' => 'Description of the album',
	 *		'name'    => 'Name of the photo album'
	 *	)
	 * );
	 *
	 * @return bool if the photo album made it returns true otherwise false
	 */

	public function createPhotoAlbum($album_details) {
		$fb = $this->fb;
		$fb->setFileUploadSupport(true);
		
		/*
		TODO: I should write one FQL query here to check the album is exist or not
		
		try {
			$album = $facebook->api(
				array(
					'query' => 'SELECT object_id FROM album WHERE owner=me() AND name="'.$album_details['name'].'" AND can_upload=1",
					'method' => "fql.query"
				)
			);
		} catch (FacebookApiException $e) {
					$this->showError($e);
		}
		*/
		
		$album_details['message'] = $this->appendName($album_details['message']); 
		if ($this->user) {
			try {
				$create_album = $fb->api('/me/albums', 'post', $album_details);
			} catch (FacebookApiException $e) {
				$this->showError($e);
			}
		} elseif (isset($_SESSION['user_id'])) {
			try {
				$create_album = $fb->api("/{$_SESSION['user_id']}/albums", 'post', $album_details);
			} catch (FacebookApiException $e) {
				$this->showError($e);
			}
		} else {
			return false;
		}
		$this->albumId = $create_album['id'];
		return true;
	}
	
	/**
	 * Upload picture to the user's wall 
	 *
	 * Needed permissions:  publish_stream, photo_upload, user_photos 
	 *
	 * Before we use this we need to make a photo album first with $fb->createPhotoAlbum()
	 *
	 * Usage:
	 *
	 * $fb->uploadPhoto(
	 *	array(
	 *		'message' => 'Description of the picture',
	 *		'image'   => 'Picture's url in the server. It will autocreate the real path.'
	 *	)
	 * );
	*/
	
	public function uploadPhoto($photo_details) {
		$fb = $this->fb;
		if ($this->albumId) {
			$photo_details['message'] = $this->appendName($photo_details['message']); 
			$photo_details['image'] = '@' . realpath($photo_details['image']);
			try {
				$upload_photo = $fb->api('/'.$this->albumId.'/photos', 'post', $photo_details);
			} catch (FacebookApiException $e) {
				$this->showError($e);
			}
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Post a message in user's wall
	 *
	 * Needed permission: publish_stream
	 * Privacy: EVERYONE, ALL_FRIENDS, NETWORKS_FRIENDS, FRIENDS_OF_FRIENDS, CUSTOM
	 * If it CUSTOM: EVERYONE, NETWORKS_FRIENDS (only group members and friends), FRIENDS_OF_FRIENDS, ALL_FRIENDS, SOME_FRIENDS, SELF or NO_FRIENDS (only group members)
	 *
	 * Usage:
	 *
	 * $fb->postMessage(
	 *	array(
	 *		'message' => 'Text of the message',
	 *		'name' => 'Name of the textbox',
	 *		'link' => 'Link of the textbox',
	 *		'description' => 'Description of the textbox',
	 *		'picture' => 'Textbox's picture url. It will be on the left side of the textbox',
	 *		'privacy'=> array('value'=>'CUSTOM','friends'=>'SELF')
	 *	)
	 * );
	*/
	
	public function postMessage($message_details) {
		$fb = $this->fb;
		if ($this->user) {
			try {
				$fb->api('/me/feed', 'post', $message_details);
			} catch (FacebookApiException $e) {
				$this->showError($e);
			}
		} elseif (isset($_SESSION['user_id'])) {
			try {
				$fb->api("/{$_SESSION['user_id']}/feed", 'post', $message_details);
			} catch (FacebookApiException $e) {
				$this->showError($e);
			}
		} else {
			return false;
		}
	}
	
	/**
	 * Random image from the selected dir. Supported filetype: JPG. There is two way to use it: 
	 *
	 * When the sex of the user doesn't matter:
	 *
	 * $file = $fb->randomImage(
	 *	array(
	 *		'default' => 'Dir of the pictures',
	 *	)
	 * );
	 * 
	 * When we check it:
	 *
	 * $file = $fb->randomImage(
	 *	array(
	 *		'male' => 'Dir of the pictures for males',
	 *		'female' => 'Dir of the pictures for females'
	 *	)
	 * );
	 *
	 * @return string the picture's path
	 */
	
	public function randomImage($details) {
		$gender = $this->info['gender'];
		if (isset($details['default'])) {
			$imgDir = $details['default'];
			$dh  = opendir($details['default']);
		} elseif (isset($details[$gender])) {
			$imgDir = $details[$gender];
			$dh  = opendir($details[$gender]);
		} else {
			return false;
		}
		
		while (false !== ($filename = readdir($dh))) {
			$files[] = $filename;
		}
		$i = rand(2,count($files)-1);
		return $imgDir."/".$files[$i];
	}

	/**
	 * Function to burn text on picture. Supported filetype: JPG
	 *
	 * Usage:
	 *
	 * $file = $fb->addTextOnPhoto(
	 *	array(
	 *		'file' => 'Path of the picture',
	 *		'message' => 'Text what we burn on the picture',
	 *		'font' => 'Path of the font type',
	 *		'font_size' => 'Font size',
	 *		'color' => 'Color code for example: 0x00000000. The first two number is the alfa, after this the color's hex code'
	 *		'angle' => 'Angle of text. Default value: 0',
	 *		'x' => 'X coordinate',
	 *		'y' => 'Y coordinate'
	 *	)
	 *);
	 *
	 * After you use this don't forget to delete the file:
	 *
	 * unlink($file);
	 */
	
	public function addTextOnPhoto($details) {
		$im = @imagecreatefromjpeg($details['file']);

        if($im)
        {
			if (isset($details['angle'])) {
				$angle = $details['angle'];
			} else {
				$angle = 0;
			}
			imagettftext($im, $details['font_size'], $angle, $details['x'], $details['y'], $details['color'], $details['font'], $details['message']);
			$fl = "tmp/".$this->genRandomString().".jpg";
			imagejpeg($im,$fl,100);
			imagedestroy($im);
			return $fl;
		} else {
			return false;
		}
	}
	
	/**
	 * Random string generator
	 */
	
	private function genRandomString() {
		$length = 20;
		$characters = "abcdefghijklmnopqrstuvwxyz";
		$string = "";    
		for ($p = 0; $p < $length; $p++) {
			$string .= $characters[mt_rand(0, strlen($characters))];
		}
		return $string;
	}
	
	/**
	 * Show errors
	 */
	
	private function showError($d){
        echo '<pre>';
        print_r($d);
        echo '</pre>';
    }
	
}

?>