<?php
/**
 * @author           Suat Secmen (http://su.at)
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
	if (preg_match('~ ([\w-]{11})\.mp4$~', $f, $m)) {
		$json = @file_get_contents('dl/'.$m[1].'.json');
		$arr = @json_decode($json, 1);
		$title = $arr['title'];
		$channel = $arr['channel'];
		$id = $arr['id'];
		$thumb = $arr['thumb'];
	} else $json = 0;
	
	@unlink('error.log');
	// upload the file to a given parent (folder "YouTube")
	exec('/home/firepanther/fp/gdrive -c '.__DIR__.'/../.gdrive upload --no-progress --delete "'.$f.'" -p 0B2F-aT17EcS2RXh5a011TzU2cVk 2> error.log');
	$src = @file_get_contents('error.log');
	if ($src) {
		// send errors via telegram
		file_get_contents('https://api.telegram.org/bot'.file_get_contents('/home/firepanther/telegram').'/sendMessage?chat_id=33357188&parse_mode=Markdown&text='.urlencode(
			"🐞 *YouTube-Fehler:*\n`".__FILE__."`\n```\n$src```"
		));
	} else {
		// send success via telegram (with youtube video thumbnail and link)
		@unlink('error.log');
		if ($json) {
			file_get_contents('https://api.telegram.org/bot'.file_get_contents('/home/firepanther/telegram').'/sendMessage?chat_id=33357188&parse_mode=Markdown&text='.urlencode(
				"🎬 *Neues Video*".($thumb ? "[:]($thumb)" : ':')." [$title](http://youtu.be/$id) (von *$channel*)"
			));
			@unlink("dl/$id.json");
		}
	}
}
