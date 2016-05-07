<?php

/**
 * Simple demonstration testmodue
 *
 * @version $Id$
 * @copyright 2016
 */

/**
 *
test *
 */
class testmodule{
	/**
	 * Constructor
	 */
	function __construct(){

	}


	function process($content){

		// Construct data ready to place into file.

		$values = array();

		$file= "quotes.txt";
		$quotes = file($file);
		srand((double)microtime()*1000000);
		$randomquote = rand(0, count($quotes)-1);

		$values[1] = trim($quotes[$randomquote]);

		$values[2] = 100+rand(1,19);


		// place data into file. Two methods -

// Line by line method
/* remove the REMOVEME below
		// First, split into lines and find all the content lines.
		$lines = preg_split("/((\r(?!\n))|((?<!\r)\n)|(\r\n))/", $content);


		foreach ($lines as &$line){
			if (substr($line,0,3) == 'OL,') {
//				echo $line."\n";
//				if (preg_match_all("/([1-9]?[0-9])%/", $line, $matches,PREG_OFFSET_CAPTURE)) {
				if (preg_match_all("/([1-9]?[0-9])%-*REMOVEME/", $line, $matches,PREG_OFFSET_CAPTURE)) {
						//					print_r($matches);
					foreach ($matches[0] as $match){
//						echo $match;
						$posn = $match[1];
						$last = $posn+strlen($match[0]);

						$i = (int)$match[0];
//						echo $i;
						if (isset($values[$i])) {
							$newline = substr($line,0,$posn) .
								substr(str_pad($values[$i],$last-$posn),0,$last-$posn) .
								substr($line,$last);
							$line = $newline;
						}
					}
				}

			}
		}
		// return pagefile content!

		return implode("\r\n",$lines);
*/

// just replace matches within the whole thing method.
		if (preg_match_all("/([1-9]?[0-9])%-*/", $content, $matches,PREG_OFFSET_CAPTURE)) {
				//					print_r($matches);
			foreach ($matches[0] as $match){
//						echo $match;
				$posn = $match[1];
				$last = $posn+strlen($match[0]);

				$i = (int)$match[0];
//						echo $i;
				if (isset($values[$i])) {
					$newline = substr($content,0,$posn) .
						substr(str_pad($values[$i],$last-$posn),0,$last-$posn) .
						substr($content,$last);
					$content = $newline;
				}
			}
		}

		return $content;

	}
}