<?php
/**
 * @author           Suat Secmen (http://suat.be)
 * @copyright        2015 - 2016 Suat Secmen
 * @license          GNU General Public License
 */

// if some files couldn't get uploaded (because gdrive is full), retry the upload
header('content-type: text/plain');

// just upload a specific video id or upload whole folder
// (uploading the whole folder with two or more running scripts could create duplicates)
if ($argc == 2) $search = '* '.$argv[1].'.mp4';
else $search = '*.mp4';

$s = glob(__DIR__.'/dl/'.$search);
foreach ($s as $f) {
	if (upload($f)) echo 'finished '.basename($f)."\n";
}

/**
 * upload the file to gdrive
 */
function upload($f) {
	$json = @file_get_contents($f.'.json');
	$arr = @json_decode($json, 1);
	$title = $arr['title'];
	$channel = $arr['channel'];
	$id = $arr['id'];
	$thumb = $arr['thumb'];
	
	@unlink('errorlog.log');
	// upload the file to a given parent (folder "YouTube")
	exec('/home/firepanther/fp/gdrive -c '.__DIR__.'/../.gdrive upload --no-progress --delete "'.$f.'" -p 0B2F-aT17EcS2RXh5a011TzU2cVk 2> errorlog.log');
	$src = @file_get_contents('errorlogs.log');
	if ($src) {
		// send errors via telegram
		file_get_contents('https://api.telegram.org/bot'.file_get_contents('/home/firepanther/telegram').'/sendMessage?chat_id=33357188&parse_mode=Markdown&text='.urlencode(
			"🐞 *YouTube-Fehler:*\n`".__FILE__."`\n```\n$src```"
		));
	} else {
		// send success via telegram (with youtube video thumbnail and link)
		@unlink('errorlogs.log');
		file_get_contents('https://api.telegram.org/bot'.file_get_contents('/home/firepanther/telegram').'/sendMessage?chat_id=33357188&parse_mode=Markdown&text='.urlencode(
			"🎬 *Neues Video*".($thumb ? "[:]($thumb)" : ':')." [$title](http://youtu.be/$id) (von *$channel*)"
		));
		@unlink($f.'.json');
	}
}
