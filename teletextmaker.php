<?php

/**
 * TeletextMaker - Modular teletext page crator using templates.
 *
 * @version 0.01
 * @copyright 2016 Rob O'Donnell.
 */

# Configuration.

define('TEMPLATES','/home/pi/Templates/');
define('PAGES','/home/pi/Pages/');
define('MODULES','./modules/');
define('DIRSEP','/');

define('HISTORY','./runhistory');


define('DONOTHING',0);
define('COPYFILE',1);
define('SAVEFILE',2);

# get last process time for rach entry

if (file_exists(HISTORY) && ($history = file_get_contents(HISTORY))) {
	$lastrun = json_decode($history,true);
} else {
	$lastrun = array();
}


# Scan templates folder for pages.
echo "Scanning foler: TEMPLATES\n";

$templates = array_diff(scandir(TEMPLATES), array('..', '.'));

# for each page,

foreach ($templates as $template){

	echo "Processing ".$template." - ";

	$action = DONOTHING;

	$filename = TEMPLATES . $template;
	$ext = pathinfo($filename, PATHINFO_EXTENSION);

	if (!is_dir($filename) &&
		($ext == "tti" || $ext == "ttix" ) &&
		false !== ($content = file_get_contents($filename))) {

# read description field/
		$lines = array_map('trim',preg_split("/((\r(?!\n))|((?<!\r)\n)|(\r\n))/", $content));

		$de = array_filter($lines, function($val){
			return substr($val,0,3) == 'DE,';
		});

		if (!count($de) ) {
			echo "No description found. ";
			$action = COPYFILE;
		} else {
//			print_r($de);
			$desc = trim(substr(reset($de),3));

# extract interval and module name
			$params = array_map('trim',explode(' ',trim($desc),2));

# if not correct format, or module not found
			if (count($params) == 2) {
				$interval = $params[0];
				$module = $params[1];

				$interval = '+' . str_replace(
										array('m','h','d','w'),
										array('minutes','hours','days','weeks'),
										$interval);

				$nextrun = strtotime($interval,(isset($lastrun[$template]) ? $lastrun[$template] : 0));
				if ($nextrun === false) {	// error in date so not a valid description
					# copy verbatimto pages folder.
					echo "DE field not in correct format. ";
					# else
					$action = COPYFILE;
				} else {
					#	check intercal.  If it's time to process..
					if (time() >= $nextrun) {
						if (file_exists(MODULES . $module . '.php')) {
							include_once (MODULES . $module . '.php');
							if (class_exists($module)) {
								$mod = new $module;
								if (method_exists($mod, 'process')) {
									echo "Calling $module ";
									$content = $mod->process($content);
									if ($content === false) {
										$action = DONOTHING;
										echo "..failed! ";
									} else {
										$action = SAVEFILE;
										echo "..complete. ";
									}
								} else {
									echo "Module/class $module does not include 'process'. ";
								}
							} else {
								echo "Specified module $module does not include appropriate class. ";
							}
						} else {
							echo "Specified module $module not found. ";
						}
					} else {
						echo "Not time to do this again. ";
					}
				}
			} else {
				echo "DE field does not have two entries in it!. ";
			}
		} // end of processing if has a description
		# 		save returned data to Pages

		switch($action){
			case SAVEFILE:
				if (is_array($content)) {	// module returned multiple pages
					echo count($content)." pages to save..";
					foreach ($content as $key=>$value){
						if ($key == "0") {
							$filename = PAGES . $template;
						} else {
							$filename = PAGES . $key;
						}
						file_put_contents($filename.'.tmp', $value);
						rename($filename.'.tmp',$filename);

					}
					// should probably be done at time of processing each frame.
					$lastrun[$template] = time();
					echo "Saved\n";
					break;
				}
				// else single page, drop through to ..
			case COPYFILE:	//  arrive here with $content still filled
				$filename = PAGES . $template;
				file_put_contents($filename.'.tmp', $content);
				rename($filename.'.tmp',$filename);
				echo "Saved\n";
				// should probably be done at time of processing each frame.
				$lastrun[$template] = time();
				break;
			default:
				echo "Nothing to do\n";
				;
		} // switch

	}
}
echo "Saving lastrun times..";
file_put_contents(HISTORY,json_encode($lastrun));
echo "done\n";
?>