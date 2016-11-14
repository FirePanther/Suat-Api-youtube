<?php
/**
 * @author           Suat Secmen (http://suat.be)
 * @copyright        2016 Suat Secmen
 * @license          GNU General Public License
 */

// google drive or dropbox
$gdrive = 1;

require 'conf.php';

header('content-type: text/plain');

// get source of url
function curlGet($url) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
	$tmp = curl_exec($ch);
	curl_close($ch);
	return $tmp;
}

$actions = [];
#$actions = ['avail', 'str'];

// vertify password
#if (!isset($_GET["p"]) || md5($_GET["p"]) !== $passwordMd5 && !in_array($_GET["p"], $actions)) die("wrong password");

// youtube "v" (video id) param
if (!isset($_GET["v"])) die("no v param");
$my_id = $_GET["v"];

// debug mode (...?debug)
$debug = isset($_GET['debug']);

// temp file to skip already downloaded videos
$tmpfile = sys_get_temp_dir()."/youtube-dl-v2-".md5($my_id);
if (!$debug && is_file($tmpfile) && !isset($_GET['again'])) die('this video was already downloaded');

// download some extra video informations (e.g. date (publishedAt))
$date = 0;
/*
// will be removed for gdrive, date is not necessary, the gdrive binary can't upload the file create time :(
$ch = curl_init("https://www.googleapis.com/youtube/v3/videos?id=$my_id&part=snippet&key=$googleKey");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$json = curl_exec($ch);
if ($json && substr($json, 0, 1) == "{") {
	$array = @json_decode($json, true);
	if (isset($array["items"])) {
		$snippet = $array["items"][0]["snippet"];
		$title = $snippet["title"];
		$description = $snippet["description"];
		$thumbnails = $snippet["thumbnails"];
		$date = strtotime($snippet["publishedAt"]);
		$channel = $snippet["channelTitle"];
	} else {
		echo "Something went wrong, no JSON:\nmy_id: $my_id\n";
		var_dump($json);
		exit;
	}
} else {
	echo "Something went wrong, no JSON:\nmy_id: $my_id\n";
	var_dump($json);
	exit;
}
*/

// download video informations
$my_video_info = 'http://www.youtube.com/get_video_info?&video_id='.$my_id.'&asv=3&el=detailpage&hl=en_US';
$my_video_info = curlGet($my_video_info);
$thumbnail_url = $title = $type = $url = '';
parse_str($my_video_info);

// gdrive or dropbox
if (!isset($gdrive)) $gdrive = 0;
if (!isset($dropbox)) $dropbox = 0;
if (!$gdrive && !$dropbox) $gdrive = 1;

// target dir
if ($gdrive) $dir = 'dl';
else $dir = '/home/firepanther/Dropbox/YouTube';

// filename (+ path), escaped
$filename = $dir.'/'.cleanName($author).' • '.cleanName($title).' • '.cleanName($my_id).'.mp4';
$filename = preg_replace_callback('~[^\w\s/!-.]+~', function($m) {
	return '\\x'.implode('\\x', str_split(bin2hex($m[0]), 2));
}, $filename);
$filename = escapeshellarg($filename);

// download video into target folder
$shell = './youtube-dl --mark-watched '.($debug ? '--verbose' : '-q').' --no-call-home --recode-video mp4 --embed-subs '.
	' -f bestvideo[ext=mp4]+bestaudio[ext=m4a] '.
	'--embed-thumbnail --add-metadata -o '.
		'"'.__dir__.'/tmp/dl-'.$my_id.'.%(ext)s" '.
	'--exec "'.
		($date ? 'touch -a -m -d \"'.date('Y-m-d', $date).'\" {} && ' : ''). // correct the date, not necessary for gdrive anymore
		'mv {} \"\$(/bin/echo -e \"'.str_replace("'", '', $filename).'\")\"'. // rename file
		' && php -f '.__DIR__.'/upload.php '.$my_id. // upload (just this file) to gdrive
	'" "http://youtu.be/'.$my_id.'"'.($debug ? ' &>&1' : ' > /dev/null 2> dl-errors.log &');
$exec2 = exec($shell, $exec);

// for debugging, print the execution logs
if ($debug) {
	echo $shell."\n\n";
	print_r($exec);
	print_r($exec2);
	die(PHP_EOL.'finished');
}

// save info for the upload script (currently just for the telegram info)
file_put_contents($filename.'.json', json_encode([
	'title' => $title,
	'channel' => $author,
	'id' => $my_id,
	'thumb' => $thumbnail_url
]));

// save a tmp file to prevend a second download
file_put_contents($tmpfile, '');

/*
// will be removed
if ($gdrive) {
	// get youtube folder id: gdrive list -q "trashed = false and name = 'YouTube'"
	@unlink('errorlogs.log');



	file_put_contents('executed-command.log', '/home/firepanther/fp/gdrive'.
		' -c '.$_SERVER['DOCUMENT_ROOT'].'api/.gdrive'.
		' upload --no-progress --delete'.
		' "'.str_replace("'", '', $filename).'" -p 0B2F-aT17EcS2RXh5a011TzU2cVk 2> errorlogs.log');
		
		
		
	exec('/home/firepanther/fp/gdrive'.
		' -c '.$_SERVER['DOCUMENT_ROOT'].'api/.gdrive'.
		' upload --no-progress --delete'.
		' "'.str_replace("'", '', $filename).'" -p 0B2F-aT17EcS2RXh5a011TzU2cVk 2> errorlogs.log');
	if (file_exists('errorlogs.log')) {
		$src = file_get_contents('errorlogs.log');
		if ($src) {
			file_get_contents('https://api.telegram.org/bot'.file_get_contents('/home/firepanther/telegram').'/sendMessage?chat_id=33357188&parse_mode=Markdown&text='.urlencode(
				"🐞 *YouTube-Fehler:*\n`".__FILE__."`\n```\n$src```"
			));
		}
	} else {
		file_get_contents('https://api.telegram.org/bot'.file_get_contents('/home/firepanther/telegram').'/sendMessage?chat_id=33357188&parse_mode=Markdown&text='.urlencode(
			"🎬 *Neues Video*[:]($thumbnail_url) [$title](http://youtu.be/$my_id) (von *$channel*)"
		));
	}
}
*/

// if everything is successful, print success for google apps script
die('success');

// cleans the filename parameters
function cleanName($s) {
	$rpl = [
		'/' => ' ⁄ ',
		' ⁄  ⁄ ' => ' ⁄ ⁄ ',
		'\'' => '´',
		'–' => '-'
	];
	foreach ($rpl as $k => $v) {
		$s = str_replace($k, $v, $s);
	}
	
	$s = preg_replace('~\s~', ' ', $s);
	$s = preg_replace('~[^a-zA-Z0-9'.preg_quote('äöüÄÖÜß!()[]{}⁄ .,;:@^§$%&=*+`´°#<>_-').']+~', '-', $s);
	return $s;
}
