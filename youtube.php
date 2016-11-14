<?php
/**
 * @author           Suat Secmen (http://suat.be)
 * @copyright        2015 - 2016 Suat Secmen
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

// vertify password
if (!isset($_GET["p"]) || md5($_GET["p"]) !== $passwordMd5) die("wrong password");

// youtube "v" (video id) param
if (!isset($_GET["v"])) die("no v param");
$my_id = $_GET["v"];

// debug mode (...?debug)
$debug = isset($_GET['debug']);

// temp file to skip already downloaded videos
$tmpfile = sys_get_temp_dir()."/youtube-dl-v2-".md5($my_id);
if (!$debug && is_file($tmpfile) && !isset($_GET['again'])) die('this video was already downloaded');

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
$filenameEscaped = preg_replace_callback('~[^\w\s/!-.]+~', function($m) {
	return '\\x'.implode('\\x', str_split(bin2hex($m[0]), 2));
}, $filename);
$filenameEscaped = escapeshellarg($filenameEscaped);

// download video into target folder
$shell = './youtube-dl --mark-watched '.($debug ? '--verbose' : '-q').' --no-call-home --recode-video mp4 --embed-subs '.
	' -f bestvideo[ext=mp4]+bestaudio[ext=m4a] '.
	'--embed-thumbnail --add-metadata -o '.
		'"'.__dir__.'/tmp/dl-'.$my_id.'.%(ext)s" '.
	'--exec "'.
		'mv {} \"\$(/bin/echo -e \"'.str_replace("'", '', $filenameEscaped).'\")\"'. // rename file
		' && php -f '.__DIR__.'/upload.php '.$my_id. // upload (just this file) to gdrive
	'" "http://youtu.be/'.$my_id.'"'.($debug ? ' &>&1' : ' > /dev/null 2> dl-errors.log &');
$exec2 = exec($shell, $exec);

// for debugging, print the execution logs
if ($debug) {
	echo $shell."\n\n";
	print_r($exec);
	print_r($exec2);
	echo $filename.' - '.$filenameEscaped;
	die(PHP_EOL.'finished');
}

// save info for the upload script (currently just for the telegram info)
file_put_contents("$dir/$my_id.json", json_encode([
	'title' => $title,
	'channel' => $author,
	'id' => $my_id,
	'thumb' => $thumbnail_url
]));

// save a tmp file to prevent a second download
file_put_contents($tmpfile, '');

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
