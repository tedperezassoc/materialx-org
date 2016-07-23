<?php
/* ----------------------------------------------------------------
DYNAMIC IMAGE RESIZING SCRIPT - V2
The following script will take an existing JPG image, and resize it
using set options defined in your .htaccess file (while also providing
a nice clean URL to use when referencing the images)
Images will be cached, to reduce overhead, and will be updated only if
the image is newer than it's cached version.

The original script is from Timothy Crowe's 'veryraw' website, with
caching additions added by Trent Davies:
http://veryraw.com/history/2005/03/image-resizing-with-php/

Further modifications to include antialiasing, sharpening, gif & png 
support, plus folder structues for image paths, added by Mike Harding
http://sneak.co.nz

For instructions on use, head to http://sneak.co.nz
---------------------------------------------------------------- */


//ERROR REPORT
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set("display_errors", 1);
set_time_limit(20); //Timeout after 20 seconds

include("../../inc/utils.php");
include("../../inc/libs/security/security.class.php");

// max_width and image variables are sent by htaccess
$image = ValidationUtils::getRequestVar("both","image","0");
$width = ValidationUtils::getRequestVar("both","width","0");
$height = intVal(ValidationUtils::getRequestVar("both","height","0"));
if (strrchr($image, '/')) {
	$filename = substr(strrchr($image, '/'), 1); // remove folder references
} else {
	$filename = $image;
}

$imgObj = new ImageSize();
$imgObj->execute($filename,$width,$height,intval(ValidationUtils::getRequestVar("both","forceFresh","0")));

class ImageSize {
	//public static $debug = false;
	public static $cacheSeconds = 0;
	public static $today;
	public static $now;
	public static $cacheObj;
	public static $usedDB = 0;
	public static $forceFresh = 0;
	
	public function __construct() {
		self::$today = date("Y-m-d");
		self::$now = date("Y-m-d H:i:s");
		self::$cacheSeconds = CACHE_SECONDS;
		
		//Setup cache path (always use MEMCACHE if it's available)
		if (MEMCACHE_SERVER != ""){
			self::$cacheObj = new Cacher("","memcached");
		}else{
			self::$cacheObj = new Cacher("../../".CACHE_PATH);
		}
	}

	public function execute($image,$dst_w,$dst_h,$forceFresh=0){
		self::$forceFresh = intval($forceFresh);
		
		// get source image size
		$size = getimagesize($image);
		$orig_w = $size[0];
		$orig_h = $size[1];

		// get header based on image mime type
		$mime_type = $size['mime'];
		switch($mime_type) { 
			case "image/jpeg":
				$contentType = "Content-type: image/jpeg";
				break;
			case "image/gif":
				$contentType = "Content-type: image/jpeg";
				break;
			case "image/png":
				$contentType = "Content-type: image/png";
				break;
			default:
				//notfound();
		}
		
		// if the full image is requested, spit it out without any image processing
		if ($dst_w === "full"){
			// headers
			header($contentType);
			// show the full image
			readfile($image);
			//exit
			exit;
		}

		// if dst_h isn't set, maintain the image proportion
		if ($dst_h === 0){
			$dst_h = round($orig_h * ($dst_w / $orig_w));
		}

		/* Caching additions by Trent Davies */
		// first check cache
		// cache must be world-readable
		$image_path = pathinfo($image);
		$resized = '_tmp/' . $image_path['filename'] . '-' . $dst_w . 'x' . $dst_h . '.' . $image_path['extension'];
		$imageModified = @filemtime($image);
		$thumbModified = @filemtime($resized);
		
		// if thumbnail is newer than image, then output cached thumbnail and exit
		if($imageModified < $thumbModified) {
			// headers
			header($contentType);
			header("Last-Modified: ".gmdate("D, d M Y H:i:s",$thumbModified)." GMT");
			readfile($resized);
			exit;
		}


		switch($mime_type) { 
			case "image/jpeg":
				$contentType = "Content-type: image/jpeg";
				$src_image = imagecreatefromjpeg($image);
				break;
			case "image/gif":
				$contentType = "Content-type: image/jpeg";
				$src_image = imagecreatefrompng($image);
				break;
			case "image/png":
				$contentType = "Content-type: image/png";
				$src_image = imagecreatefromgif($image);
				break;
			default:
				//notfound();
		}

		// get the ratio needed
		$x_ratio = $dst_w / $orig_w;
		$y_ratio = $dst_h / $orig_h;

		//work with the smaller of the two proportions
		$ratio = ($x_ratio > $y_ratio) ? $x_ratio : $y_ratio;

		// define the source image rectangle to be copied
		if($x_ratio > $y_ratio){
			//fill width, crop height
			$src_x = 0;
			$src_y = round($orig_h/2 - $dst_h/($ratio * 2));
			$src_w = $orig_w;
			$src_h = $dst_h/$ratio;
		} else {
			//fill height, crop width
			$src_x = round($orig_w/2 - $dst_w/($ratio * 2));
			$src_y = 0;
			$src_w = $dst_w/$ratio;
			$src_h = $orig_h;
		}
		
		// set up canvas
		$dst_image = imagecreatetruecolor($dst_w, $dst_h);
		imageantialias($dst_image, true);

		// copy resized image to new canvas
		imagecopyresampled ($dst_image, $src_image, 0, 0, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);

		/* Sharpening adddition by Mike Harding */
		// sharpen the image (only available in PHP5.1)
		if (function_exists("imageconvolution")) {
			$matrix = array(array( -1, -1, -1 ),
							array( -1, 32, -1 ),
							array( -1, -1, -1 ) );
			$divisor = 24;
			$offset = 0;

			imageconvolution($dst_image, $matrix, $divisor, $offset);
		}
		// send the header and new image
		header($contentType);
		switch($mime_type) { 
			case "image/jpeg":
				// output image
				imagejpeg($dst_image, null, -1);
				// write the thumbnail to cache
				imagejpeg($dst_image, $resized, -1);
				break;
			case "image/gif":
				// output image
				imagegif($dst_image);
				// write the thumbnail to cache
				imagegif($dst_image, $resized);
				break;
			case "image/png":
				// output image
				imagepng($dst_image, NULL, 2, PNG_NO_FILTER);
				// write the thumbnail to cache
				imagepng($dst_image, $resized, 2);
				break;
			default:
				notfound();
		}
		
		
		


		// clear out the resources
		imagedestroy($src_image);
		imagedestroy($dst_image);
	}

	public static function clearCache($cacheID){
		self::init();
		self::$cacheObj->setDataCache($cacheID,"");
	}
}
?>