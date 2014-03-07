<?php

/**
 * Template: This PHP class is a Template Engine responsible fopr page rendering of a website using tags and predefined templates that manages all the website's pages
 * in a professional and easy way.
 * It support user defined tags, loops and conditions (coming soon).
 * It also takes care to the state the user is on, either logged-in or logged-out, also with a special tag.
 * It support user defined functins and PHP's built in functions, another Open Source library I released, nsgRPN.php (https://github.com/egpo/Reverse-Polish-Notation) and 
 * string manipulations with another open source library I released, Strings.php (https://github.com/egpo/php-jqString).
 *
 * latest release can be found on GitHub: https://github.com/egpo/Template
 * 
 * Written by Ze'ev Cohen (zeevc AT egpo DOT net)
 * http://dev.egpo.net
 *
 *
 * License: The MIT License (MIT)
 *
 * Copyright (c) 2014 Ze'ev Cohen (zeevc@egpo.net)
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * http://opensource.org/licenses/MIT
 */

require_once('String.php');
require_once('nsfRPN.php');

class Template {
	private $template;  // String Object
	private $data; 		// Array Object
	
	private $user_login = false;
	
	static $tag_regex = "/{[a-z: ]+}/";
	private $user_callback_obj;
	private $user_callback_func;	
	public $rpn;
	
	function __construct($string="") {
		$this->data = Array();
		$this->user_callback_obj  = null;
		$this->user_callback_func = '';
		$this->rpn = new nsfRPN();
		$this->rpn->__construct();
		$this->rpn->user_callback('varval',$this);
		$pathname = is_pathname($string);
		$filename = is_filename($string);

		// Initialize the class with a ready many string or read template form filesystem
		if (!$pathname && !$filename){
			$this->template = new String($string);
		} else {
			if ($filename){
				$pageload = ROOT_PATH.PAGE_PATH.$string;
			} else {
				if ($pathname){
					$pageload = $string;
				}				
			}
			if (file_exists($pageload)){
				$this->template = new String(join("", file($pageload)));
			} else {
				return false;
			}
		}
	}

	// This class support user's logged-in / loggfed-out state, according to a session cookie 'USER_AUTH'
	// $_SESSION['USER_AUTH'] == 1 => The user is logged-in.
	function isUserLogin(){
		if (isset($_SESSION['USER_AUTH']) && ($_SESSION['USER_AUTH'] == 1)){
			return true;
		} else {
			return false;
		}
	}
	
	// Load a page form filesystem
	function loadpage($page, $lang="en"){
		$this->template = $this->_loadpage($page);
	} // loadpage
	
	private function _loadpage($page, $lang="en"){
		// in future: add ability for pages fetched form database, cache will be using memcached
		
		$pageload = ROOT_PATH.PAGE_PATH.$page;
		if (file_exists($pageload)){
			$html = join("", file($pageload));
		} else {
			return false;
		}
		
		return $html;
	}
	
	// Process the login-logout on the page.
	// When user is logged-in, remove the blocks of text not needed to display and vise-versa.
	// TAG: {login}   ....  {/login}
	// TAG: {logout}  ....  {/logout}
	private function loginout(){	
		if ($this->isUserLogin()){	
			// When user logged-in, remove all parts for logged-out blocks
			$blockAr = $this->template->block('/{logout}/', '/{\/logout}/');
			foreach($blockAr as $block) {
				$this->template = $this->template->replace($block, '');
			};
				
			$this->template = $this->template->replace('{login}', '');
			$this->template = $this->template->replace('{/login}', '');
		} else {
			// When user logged-out, remove all parts for logged-in blocks
			$blockAr = $this->template->block('/{login}/', '/{\/login}/');
			foreach($blockAr as $block) {
				$this->template = $this->template->replace($block, '');
			};
			$this->template = $this->template->replace('{logout}', '');
			$this->template = $this->template->replace('{\/logout}', '');
		}
	} // loginout

	// Read include files and add them into the template
	// TAG: {include page.html}
	private function incfiles(){
		$inc_regex = "/{include[ \s]+[\d\w.-_]+}/";

		$matchAr = $this->template->match($inc_regex);
		foreach($matchAr as $inctag) {
			$incfilenameAr = StaticString::word_count($inctag,WC_WORDS,'.-_0123456789');
			if ($incfilename = $incfilenameAr[1]){
				$incfilename = ROOT_PATH.PAGE_PATH.$incfilename;
				if (file_exists($incfilename)){
					$inchtml = join("", file($incfilename));
				} else {
					$inchtml = "INCLUDE FILE NOT FOUND";
				}
				if ($incfile = new String($inchtml)){
					$block = $incfile->block('/<body[ \s]*[ \s\d\w"\';(){}\[\]:=]*>/','/<\/body>/',PREG_OFFSET_CAPTURE_INNER);
					$inchtml = StaticString::_substring($block[0][0],$block[0][2],$block[0][3]-$block[0][1]);
				}
			}
			$this->template = $this->template->replace($inctag, $inchtml);
		};		
	}

// looping
/*
			$incfile->block($begin_regex,$end_regex,PREG_OFFSET_CAPTURE_INNER)->each(function($block){
				//$inchtml = $incfile->substring($begin[1]+StaticString::strlen($begin[0]),$end[1]);
				$inchtml = StaticString::_substring($block[0],$block[2],$block[3]-$block[1]);
				/ *					$incfile->match($body_regex)->each(function($val){
				 $begin = $incfile->match("/<body[\s0-9a-zA-Z\"'=;()]*>/",PREG_OFFSET_CAPTURE)[0];
						$end = $incfile->match("/<\/body>/",PREG_OFFSET_CAPTURE)[0];
						$inchtml = $incfile->substring($begin[1]+StaticString::strlen($begin[0]),$end[1]);
						* /					});
			
	
	
	} // incfiles
	*/
/*working

	private function incfiles(){
		$inc_regex = "/{include[\s]+[0-9a-z.-_]+}/";
	
		$this->template->match($inc_regex)->each(function ($inctag){
			$body_regex = "/<body[\s\d\w\"';():=]*>[\s\d\w\r\n\"';():=.]*<\/body>/";
			if ($incfilename = StaticString::word_count($inctag,WC_WORDS,'.-_0123456789')[1]){
				if ($incfile = new String($this->_loadpage($incfilename))){
					$begin = $incfile->match("/<body[\s0-9a-zA-Z\"'=;()]*>/",PREG_OFFSET_CAPTURE)[0];
					$end = $incfile->match("/<\/body>/",PREG_OFFSET_CAPTURE)[0];
					$inchtml = $incfile->substring($begin[1]+StaticString::strlen($begin[0]),$end[1]);
				}
			}
			$this->template = $this->template->replace($inctag, $inchtml);
		});
	} // incfiles
	
	*/
//	function conditions($html, $data)
	//{
		// {if <var>=
/*	
		$p1 = strpos($html, "%%cond_");
		while (!($p1 === false))
		{	$html[$p1+0] = "+";
		$html[$p1+1] = "+";
	
		$p2 = strpos($html, "_begin%%", $p1+7);
		if (!($p2 === false))
		{	$cond = substr($html, $p1+7, $p2-$p1-7);
	
		$p3 = strpos($html, "%%cond_".$cond."_end%%");
		if (!($p3 === false))
		{	$html[$p3+0] = "+";
		$html[$p3+1] = "+";
	
		$condline = substr($html, $p1+15+strlen($cond), $p3-$p1-(15+strlen($cond)));
		switch ($cond)
		{
			case "newsite":
				{	if ($data["newurl"] == "no")
				{	$condline = "";
				}
				} break;
			case "oldsite":
				{	if (($data["newurl"] == "yes") || ($data["curr_catid"] != $data["catid"]))
				{	$condline = "";
				}
				} break;
			case "modsite":
				{	if (($data["newurl"] == "yes") || ($data["curr_catid"] == $data["catid"]))
				{	$condline = "";
				}
				} break;
			case "catdesc":
				{	if ($data["description"] == "")
				{	$condline = "";
				}
				} break;
			case "browser_explorer":
				{	if (get_browser_type() != "IE")
				{	$condline = "";
				}
				} break;
			case "browser_firefox":
				{	if (get_browser_type() != "FF")
				{	$condline = "";
				}
				} break;
			case "browser_chrome":
				{	if (get_browser_type() != "CH")
				{	$condline = "";
				}
				} break;
			case "browser_other":
				{	if (get_browser_type() != "NA")
				{	$condline = "";
				}
				} break;
			case "logo":
				{	if ($data["logo"] == "none")
				{	$condline = "";
				}
				} break;
			case "nologo":
				{	if ($data["logo"] != "none")
				{	$condline = "";
				}
				} break;
			default:
				{	$condline = "";
				} break;
		}
		$html = substr($html, 0, $p1).$condline.substr($html, $p3+strlen+13+strlen($cond));
		}
		$p1 = strpos($html, "%%cond_");
		}
		}
		return $html;
		*/
	//} // process_conditions

	// Process loops, repeated blocks, in the Templates
	// TAG: {loop $loopvar} .... {/loop}
	private function loop(){
		$blockAr = $this->template->block('/{loop[ \s]+[$]{1}[_.\s\d\w]+}/', '/{\/loop}/',PREG_OFFSET_CAPTURE_INNER);
		foreach($blockAr as $block){
			$loop = StaticString::_substr($block[0], 0, $block[2]);
			$loopvarnameAr = StaticString::word_count($loop,WC_WORDS,'$0123456789._');
			if ($loopvarname = $loopvarnameAr[1]){
				if (!strpos($loopvarname, '.')){
					if ($loopvarname[0] == '$'){
						$loopvarname = substr($loopvarname, 1);						
						if ($this->user_callback_func && !isset($this->data[$loopvarname])){
							if (is_null($this->user_callback_obj)){
								// Call a regular user callback
								$this->data[$loopvarname] = call_user_func_array($this->user_callback_func, array($loopvarname));
							} else {
								// Call a class user callback
								$this->data[$loopvarname] = call_user_func_array(array($this->user_callback_obj, $this->user_callback_func), array($loopvarname));
							}	
						}
						if (isset($this->data[$loopvarname])){
							$loopidx = 0;
							$loopres = "";
							$this->data['loopinfo']['first'] = '1';
							$this->data['loopinfo']['last'] = '0';
							$this->data['loopinfo']['items'] = count($this->data[$loopvarname]);
							$loophtml = new String(StaticString::_substring($block[0],$block[2],$block[3]-$block[1]));
							
							$looptagsAr = $loophtml->match('/{[$]{1}[\d\w-_]+}/')->unique();
							foreach($looptagsAr as $looptag) {
								$newlooptag = substr_replace($looptag, '{$loopdata.',0,2); 
								$loophtml = $loophtml->replace($looptag,$newlooptag);
							}
							$looptagsAr = $loophtml->match('/\([$]{1}[\d\w-_]+\)/')->unique();
							foreach($looptagsAr as $looptag) {
								$newlooptag = substr_replace($looptag, '($loopdata.',0,2); 
								$loophtml = $loophtml->replace($looptag,$newlooptag);
							}
							foreach($this->data[$loopvarname] as $val){
								$loopidx++;
								if ($loopidx == $this->data['loopinfo']['items']){
									$this->data['loopinfo']['last'] = '1';
								}
								$this->data['loopinfo']['index'] = $loopidx;
								
								$oneloop = $loophtml;
								$this->data['loopdata'] = $val;
								$this->tags($oneloop);
								$loopres .= $oneloop->toString();
								
								$this->data['loopinfo']['first'] = '0';
							}
							$loopresobj = new String($loopres);
							$this->template = $this->template->replace($block[0], $loopresobj);
							unset($this->data['loopinfo']);
							unset($this->data['loopdata']);
						}// else {
							//$this->template = $this->template->replace($block[0], $loopresobj);
							
						//}
					}
				}
			}
		};
	} // loop

	// Process the tags in the Template, will also process the loops tags
	// TAG: {$tagname}
	private function tags(&$stringObject){
		$tags = $stringObject->match('/{[ \s\d\w.-_$()]+}/')->unique();
		foreach($tags as $rowtag){
			$val = '';
			$tag = StaticString::_substring($rowtag, 1, StaticString::strlen($rowtag)-1);
			
			$val = $this->rpn->rpn($tag);

			if ($val != ''){
				$stringObject = $stringObject->replace($rowtag, $val);
			}
		}
	}
	
	// Data load, load single varname
	function loadData($varname,$data){
		$this->data[$varname]=$data;
	}
	
	// Data load, load an array: key,value
	function loadDataArr($array_data){
		//$array = new Arr($array_data);
		foreach($array_data as $key => $val){
			$this->data[$key]=$val;
		};
	}
	
	// Callback function for the RPN (Reverse-Polish-Notation) class to provide it with the value of tags.
	// Vars can be a single array entry or a group (Array within array), whena group is used, the var has a '.' as a seperator
	// For example: single var:  'index', group var: 'day.index'
	function varval($groupvarname){
		if ($p = strpos($groupvarname, '.')){
			$vargroup = substr($groupvarname, 0, $p);
			$varname = substr($groupvarname, $p+1);
		} else {
			$vargroup = '';
			$varname = $groupvarname;
		}

		if ($vargroup == ''){
			if (!empty($this->data[$varname])){
				$val = $this->data[$varname];
			} else {
				if (is_null($this->user_callback_obj)){
					$this->data[$varname] = call_user_func_array($this->user_callback_func, array($varname));
				} else {
					$this->data[$varname] = call_user_func_array(array($this->user_callback_obj, $this->user_callback_func), array($varname));
				}
				$val = $this->data[$varname];
			}
		} else {
			if (!empty($this->data[$vargroup][$varname])){
				$val = $this->data[$vargroup][$varname];
			} else {
				if (is_null($this->user_callback_obj)){
					$this->data[$vargroup][$varname] = call_user_func_array($this->user_callback_func, array($groupvarname));
				} else {
					$this->data[$vargroup][$varname] = call_user_func_array(array($this->user_callback_obj, $this->user_callback_func), array($groupvarname));
				}
				$val = $this->data[$vargroup][$varname];
			}
		}
		
		return $val;
	}

	// Register a user callback, either a regular function or a class's method
	function user_callback($func, $obj=null)
	{
		if ($obj){
			if (!method_exists($obj,$func)){
				return false;
			}
			$this->user_callback_obj  = $obj;
			$this->user_callback_func = $func;
		} else {
			if (!function_exists($func)){
				return false;
			}
			$this->user_callback_obj  = null;
			$this->user_callback_func = $func;
		}
		return true;
	}

	// Process the Template, render the page.
	function parse(){
		if (is_null($this->template)){
			return "ERROR";
		}
		$this->loginout();
		$this->incfiles();
		$this->loginout();
		//$this->conditions();
		$this->loop();
		
		$this->tags($this->template);
		
		$this->template = $this->template->replace('{#', '{$');
		return $this->template->toString();
	}


	// Reset the class, remove all data loaded
	function resetTemp(){
		unset($data);
	}
}

?>