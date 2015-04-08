<?php
/**
 * Support for the "html5gallery" video player (https://html5box.com/html5gallery/index.php). It will play video natively
 *  via HTML5 in capable browsers if the appropiate multimedia formats are provided. It will fall back to flash in older browsers.
 * The player size is responsive to the browser size.

 * Audio: This plugin does not play audio files.<br>
 * Video: <var>.m4v</var>/<var>.mp4</var> - Counterpart format <var>.webm</var> supported (see note below!)
 *
 * IMPORTANT NOTE ON WEBM COUNTERPART FORMATS:
 *
 * The counterpart format is not a valid format for Zenphoto itself as that would confuse the management.
 * Therefore this format can be uploaded via ftp only.
 * The files needed to have the same file name except extension (beware the character case!).
 *
 * IMPORTANT NOTE ON HD and SD FORMATS:
 *
 * This player is capable of switching between HD and SD video files. To enable this feature the HD files should
 * be uploaded as described above. The SD files should be uploaded to a companion albums folder that has the same path and starts in the same folder
 * as the albums folder, but the root folder must be the same name as the normal albums folder with '.SD' appended to it. For example:
 * 
 * HD video files go here: <var>/albums/videos/myvideo.mp4</var><br>
 * SD video files go here: <var>/albums.SD/videos/myvideo.mp4</var>
 *
 * (The counterpart videos must follow the same paths.)
 *
 * <b>NOTE:</b> This player does not support external albums!<br>
 * <b>NOTE:</b> This plugin does not support playlists!
 *
 * @author Jim Brown
 * @package plugins
 * @subpackage media
 */
$plugin_is_filter = 5 | CLASS_PLUGIN;
$plugin_description = gettext("Enable <strong>html5gallery</strong> to handle multimedia files.");
$plugin_notice = gettext("<strong>IMPORTANT</strong>: Only one multimedia extension plugin can be enabled at the time and the class-video plugin must be enabled, too.") . '<br /><br />' . gettext("Please see <a href='https://html5box.com/html5gallery/index.php'>html5box.com</a> for more info about the player and its license.");
$plugin_author = "Jim Brown";
$plugin_disable = (getOption('album_folder_class') === 'external') ? gettext('This player does not support <em>External Albums</em>.') : extensionEnabled('class-video') ? false : gettext('The class-video plugin must be enabled for video support.');

if (!empty($_zp_multimedia_extension->name) || $plugin_disable) {
	enableExtension('html5gallery', 0);

//NOTE: the following text really should be included in the $plugin_disable statement above so that it is visible
//on the plugin tab

	if (isset($_zp_multimedia_extension)) {
		trigger_error(sprintf(gettext('html5gallery not enabled, %s is already instantiated.'), get_class($_zp_multimedia_extension)), E_USER_NOTICE);
	}
} else {
	if (OFFSET_PATH != 2)
		require_once(SERVERPATH . '/' . ZENFOLDER . '/' . PLUGIN_FOLDER . '/class-video.php');
	Gallery::addImageHandler('mp4', 'Video');
	Gallery::addImageHandler('m4v', 'Video');
}

class html5gallery {

	public $width = '';
	public $height = '';

	function __construct() {
		$this->width = 1280;
		$this->height = 720;
	}

	static function headJS() { ?>
    <script type="text/javascript" src="<?php echo WEBPATH; ?>/plugins/html5gallery/jquery.js"></script>
    <?php
	}

	static function footJS() { ?>
    <script type="text/javascript" src="<?php echo WEBPATH; ?>/plugins/html5gallery/html5gallery.js"></script>
    <?php
	}

	/**
	 * Get the player configuration of html5gallery
	 *
	 * @param mixed $movie the image object
	 * @param string $movietitle the title of the movie
	 *
	 */
	function getPlayerConfig($movie, $movietitle = NULL) {
		global $_zp_current_album;
		
		$moviepath = $movie->getFullImage(FULLWEBPATH);

		$ext = getSuffix($moviepath);
		if (!in_array($ext, array('m4v', 'mp4', 'flv'))) {
			return '<span class="error">' . gettext('This multimedia format is not supported by html5gallery') . '</span>';
		}

		$videoThumb = $movie->getCustomImage(null, $this->width, $this->height, $this->width, $this->height, null, null, true);

		$videoTitle = "";
		if (getOption('zenfluid_titlebreadcrumb')) {
			$parentalbum = $_zp_current_album->getParent();
			if(!empty($parentalbum)) {
				$videoTitle = $parentalbum->getTitle();
			}
			$videoTitle = $videoTitle . ": " . getAlbumTitle() . ": ";
		} 
		$videoTitle = $videoTitle . getImageTitle();

		$videoUID = getImageData('id');

		$metadata = getImageMetaData(NULL,false);

		$vidWidth = $metadata['VideoResolution_x'];
		$vidHeight = $metadata['VideoResolution_y'];

    if ($this->getCounterpartFile($moviepath, "mp4", "SD")) {
		$playerconfig = '
			<div id="vidstage" style="margin: 0px auto; max-width: ' . $vidWidth . '; max-height: ' . $vidHeight . ';">
        <div class="html5gallery" data-width="' . $vidWidth . '" data-height="' . $vidHeight . '" data-hddefault="true" data-responsive="true" data-padding=0 style="display:none;">
          <a href="' . $this->getCounterpartFile($moviepath, "mp4", "SD") . '"
            data-hd="' . $this->getCounterpartFile($moviepath, "mp4", "HD") . '"
            data-webm="' . $this->getCounterpartFile($moviepath, "webm", "SD") . '"
            data-hdwebm="' . $this->getCounterpartFile($moviepath, "webm", "HD") . '"
            data-poster="' . $videoThumb . '">
            <img src="' . $videoThumb . '" alt="' . $videoTitle . '">
          </a>
        </div>
      </div>';
    } else {
		$playerconfig = '
			<div id="vidstage" style="margin: 0px auto; max-width: ' . $vidWidth . '; max-height: ' . $vidHeight . ';">
        <div class="html5gallery" data-width="' . $vidWidth . '" data-height="' . $vidHeight . '" data-padding=0 style="display:none;">
          <a href="' . $this->getCounterpartFile($moviepath, "mp4", "HD") . '"
            data-poster="' . $videoThumb . '">
            <img src="' . $videoThumb . '" alt="' . $videoTitle . '">
          </a>
        </div>
      </div>';
    }
      
      $playerconfig .= '
			<script type="text/javascript">
			// <!-- <![CDATA[
				var viewportwidth;
				var viewportheight;
				var maxvidheight = ' . $vidHeight . '
				var maxvidwidth = ' . $vidWidth . '
				var vidwidth
				var vidheight
				var vidratio = maxvidheight / maxvidwidth
				function setStage(){
					if (typeof window.innerWidth != "undefined") {
						viewportwidth = window.innerWidth,
						viewportheight = window.innerHeight
					} else if (typeof document.documentElement != "undefined" && typeof document.documentElement.clientWidth != "undefined" && document.documentElement.clientWidth != 0) {
						viewportwidth = document.documentElement.clientWidth,
						viewportheight = document.documentElement.clientHeight
					} else {
						viewportwidth = document.getElementsByTagName("body")[0].clientWidth,
						viewportheight = document.getElementsByTagName("body")[0].clientHeight
					}
					vidheight = viewportheight - 50
					if (vidheight > maxvidheight) {
						vidheight = maxvidheight
					};
					vidwidth = vidheight / vidratio,
					$("#vidstage").css({"max-width" : vidwidth + "px"})
					$("#vidstage").css({"max-height"  : vidheight + "px"})
				};
				$(document).ready(function() {
					setStage()
				});
				var resizeTimer;
				$(window).resize(function() {
					clearTimeout(resizeTimer);
					resizeTimer = setTimeout(setStage, 100);
				});
			// ]]> -->
			</script>';
		return $playerconfig;
	}

	/**
	 * outputs the player configuration HTML
	 *
	 * @param mixed $movie the image object if empty (within albums) the current image is used
	 * @param string $movietitle the title of the movie. if empty the Image Title is used
	 * @param string $count unique text for when there are multiple player items on a page
	 */
	function printPlayerConfig($movie = NULL, $movietitle = NULL) {
		global $_zp_current_image;
		if (empty($movie)) {
			$movie = $_zp_current_image;
		}
		echo $this->getPlayerConfig($movie, $movietitle);
	}

	/**
	 * Returns the width of the player
	 * @param object $image the image for which the width is requested
	 *
	 * @return int
	 */
	function getWidth($image = NULL) {
		return $this->width;
	} 

	/**
	 * Returns the height of the player
	 * @param object $image the image for which the height is requested
	 *
	 * @return int
	 */
	function getHeight($image = NULL) {
		return $this->height;
	} 

	function getCounterpartfile($moviepath, $ext, $definition) {
		$counterpartFile = '';
		$counterpart = str_replace("mp4", $ext, $moviepath);
		$albumPath = substr(ALBUM_FOLDER_WEBPATH, strlen(WEBPATH));
		$vidPath = getAlbumFolder() . str_replace(FULLWEBPATH . $albumPath,"",$counterpart);
		switch (strtoupper($definition)) {
			case "HD":
				if (file_exists($vidPath)) {
					$counterpartFile = pathurlencode($counterpart);
				}
				break;
			case "SD":
				$vidPath = str_replace(rtrim(getAlbumFolder(),"/"), rtrim(getAlbumFolder(),"/") . ".SD", $vidPath);
				$counterpart = str_replace(rtrim(ALBUM_FOLDER_WEBPATH,"/"), rtrim(ALBUM_FOLDER_WEBPATH,"/") . ".SD", $counterpart);
				if (file_exists($vidPath)) {
					$counterpartFile = pathurlencode($counterpart);
				}
				break;
		}
		return $counterpartFile;
	}
	
}

$_zp_multimedia_extension = new html5gallery(); // claim to be the flash player.
zp_register_filter('theme_head', 'html5gallery::headJS');
zp_register_filter('theme_body_close', 'html5gallery::footJS');
?>
