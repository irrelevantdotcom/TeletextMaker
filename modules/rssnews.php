<?php

/**
 * rssnews module for TeletextMaker
 *
 * This module reads an RSS url, and constructs a set of teletext pages
 * based upon the contents.
 *
 * The page this is linked to will be the index page, and should contain
 *  the following fields.
 *
 * 1%<url>	- link to RSS url. URL shorteners will be followed.
 * 2%<nn> - number of items to process (mas 23)
 * 3%<nn> - start page number for detail pages (or absent = don't create them)
 * 4%<fsp> - template page for detail pages. Create/rename as .tpl
 *
 * The following fields will be filled in.
 *
 * 5%   - Feed Title
 * 6%<datefmt>  - Last update date/time (see php date format)
 * 7%	- location for headline
 * 8%   - location for detail page number
 *
 * 7 and 8 should be on the same line.  This will be duplicated across
 * subsequent lines, to the quantity specified by 2% !
 *
 * @version $Id$
 * @copyright 2016
 */


/**
 *
 *
 */
class rssnews{

	private $url;
	private $qty;
	private $dpage;
	private $dtpl;

	/**
	 * Constructor
	 */
	function __construct(){

	}

	function process($content){

	// find fields

		echo "Find fields..";
		if (preg_match_all("/([1-9]?[0-9])%-*/", $content, $matches,PREG_OFFSET_CAPTURE)) {
			//					print_r($matches);
			foreach ($matches[0] as $match){
				echo $match[0] . ' ';
				$posn = $match[1];
				$last = $posn+strlen($match[0]);
				$i = (int)$match[0];

				switch($i){
					case 1:
							preg_match('/[\x21-\x7E]*/s',substr($content,$posn+2),$m);
						$this->url = $m[0];
						if (substr($this->url,0,4) != 'http') {
							$this->url = 'http://' . $this->url;
						}
						echo $m[0] . ' ';
						break;
					case 2:
						$this->qty = min((int)substr($content,$posn+2),23);
						echo $this->qty . ' ';
						break;
					case 3:
						$this->dpage = (int)substr($content,$posn+2,3);
						echo $this->dpage . ' ';
						break;
					case 4:
						preg_match('/[\x21-\x7E]*/s',substr($content,$posn+2),$m);
						$tpl = $m[0];
						if (file_exists(TEMPLATES . $m[0] . '.tpl')) {
							$this->dtpl = file_get_contents(TEMPLATES . $m[0] . '.tpl');
						}
						break;
				} // switch
			}
		} else {
			echo 'No fields found in template page. ';
			return false;
		}

		$values = array();
		$pages = array();
		if (!empty($this->url)) {
			if (false !==($data = file_get_contents($this->url))) {
				if (($x = simplexml_load_file($this->url))) {

					$title = (string) $x->channel->title;
					$desc = (string) $x->channel->description;
					$items = array();
					$date = time();

					foreach ($x->channel->item as $item){
						$newsitem = array();
						$newsitem['headline'] = (string) $item->title;
						if (strpos($newsitem['headline'],'VIDEO') === false) {
							if (isset($item->description)) {
								$newsitem['description'] = (string) $item->description;
							}
							// omly if template specified try and create story page
							if (!empty($this->dtpl)) {
								$url = $item->guid;
								if (false!==($story = $this->getStory($url))) {
									$newsitem['story'] = $story;
									$pages[$this->dpage . '.tti'] = $this->createStoryPage($this->dtpl, $this->dpage, $newsitem);
								}
							}
							$items[$this->dpage] = $newsitem;
							$this->dpage++;
						}
						if ( count($pages) >= $this->qty )
							break;
					}

					$content = $this->createIndexPage(
						$content, $title, $desc, date('D j M',$date), $items);

					return array($content) + $pages;
				} else {
					echo 'Error loading xml. ';
					return false;
				}
			} else {
				echo 'Unable to fetch url '.$this->url.' ';
				return false;
			}
		} else {
			echo 'No RSS url specified. ';
			return false;
		}

	}

	private function getStory($url) {

		$data = file_get_contents($url);

		// parse data for story!
//		$data = trim(strip_tags($data)); // temp!!!

		$dom = new domDocument;
		@$dom->loadHTML($data);

		$paras = $dom->getElementsByTagName('p');
		$story = '';
		$rc = 'story';
		foreach ($paras as $p){
			$class = $p->getAttribute('class');
			if ($rc == '' || strpos($class,$rc) !== false) {
				$story .= $p->textContent . "**ENDPARA**";
				$rc = '';
			}
		}
		$story = trim(preg_replace('/[\x00-\x20]+/',' ',$story),'\x00-\x20');
		return str_replace("**ENDPARA**","\n\n",$story);

	}



	private function createStoryPage($template,$pnum,$newsitem) {

		// first insert all the fixed fields
		// adjust page number
		// then break down into lines
		// find first story line field
		// duplicate story across sibsequent lines.
		// rebuild page.

//		print_r($newsitem);

		$data = $this->replace_block($template,
			array(  1 => $newsitem['headline'],
					2 => $newsitem['story']));

		return preg_replace('/PN,[0-9][0-9][0-9]/','PN,'.$pnum,$data,1);
	}

	private function createIndexPage($template,$title,$desc,$date,$newslist) {

		$data = $this->replace($template,array(	5 => $title,
												9 => $desc,
												6 => $date));

		$data = $this->replace_block($data,
			array( 	7 => array_column($newslist,'headline'),
					8 => array_keys($newslist)));


		return $data;
	}


	private function replace($content, $values) {

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

	private function replace_block($content, $values) {

		// this is horrible.


		// if value is an array, treat as one entry per line
		// if value is a string, wordwrap it into array..!

		// We need to break down the template into lines.
		// break up into line types.
		// and sort just the OL lines

		// for each line
		//   if not OL, copy into output
		//   if OL, scan for fields
		//     if field present, is it in values
		//       get width and depth from field
		//		  if depth>1 if value not aray, wordwrap it into array
		//       for loop = 0 to depth-1
		//         if OL+loop does not exist
		//	  		 create new line based on original line
		//         place value into new line at same posn as field.
		//   copy line to output
		//


		// We need to break down the template into lines.

		// break up into line types.
		$lines = preg_split("/((\r(?!\n))|((?<!\r)\n)|(\r\n))/", $content);
		// and sort just the OL lines
		usort ($lines, function($a, $b){
			$order = array('DE'=>1,'DS'=>2,'SP'=>3,'CT'=>4,'PS'=>5,'RE'=>6,
			'PN'=>7,'SC'=>8,'OL'=>9,'FL'=>10,0=>99,''=>99);
			$a2 = substr($a,0,2);
			$b2 = substr($b,0,2);
			if ($a2 == "OL" && $b2 == "OL" ) {
				return (int)substr($a,3) < (int)substr($b,3) ? -1 : 1;
			}
			return $order[$a2] < $order[$b2]  ? -1 : 1;
		});

//		print_r($lines);

		$data = array();

		// for each line
		foreach ($lines as $line){
			//   if not OL, copy into output
			if (substr($line,0,2) != 'OL') {
				$data[] = $line;
			} else {
				$ln = (int)substr($line,3);
		//   if OL, scan for fields
				if (preg_match_all("/([1-9]?[0-9])%([1-9]?[0-9]?)-*/", $line, $matches,PREG_OFFSET_CAPTURE)) {
		//     if field present, is it in values
					foreach ($matches[0] as $match){
//						print_r($match);
						$i = (int)$match[0];
						echo $i;
						if (isset($values[$i])) {
	//						echo $match;
					//       get width and depth from field
							$posn = $match[1];
							$last = $posn+strlen($match[0]);
							$depth = (int) substr($line,1+strpos($line,'%',$posn));
		echo "($posn,$last,$depth)";
			//		  if depth>1 if value not aray, wordwrap it into array
							if ($depth) {
								if (!is_array($values[$i])) {
									$values[$i] = explode("\n",wordwrap($values[$i],$last-$posn,"\n",true));
								}
							}
							//       for loop = 0 to depth-1
							for ($d=0; $d<$depth; $d++) {
								if ($ln<10 && $d>0 && $ln+$d == 10) {
									$posn++;
									$last++;
								}
								if (!isset($values[$i][$d])) {
									$values[$i][$d] = '';
								}
								if (count($l = preg_grep('/^OL,'.($ln + $d).',/',$lines))) {
									$destline = reset($l);
									$key = key($l);
									$lines[$key] = substr($destline,0,$posn) .
										substr(str_pad($values[$i][$d],$last-$posn),0,$last-$posn) .
										substr($destline,$last);

								} else {
									//         if OL+loop does not exist
									//	  		 create new line based on original line
									$s = explode(',',$line,3);
									$destline = 'OL,' . ($ln + $d) . ',' . $s[2];
									$lines[] = substr($destline,0,$posn) .
										substr(str_pad($values[$i][$d],$last-$posn),0,$last-$posn) .
										substr($destline,$last);
								}
							}

						}
					}

				}
			}

		}

		return implode("\n",$lines);

	}

}