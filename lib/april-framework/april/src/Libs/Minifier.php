<?php
/*
 * JS, CSS and HTML Minifier 
 *	http://tizardsbriefcase.com/896/php/minify-html-using-php
 *	http://farhadi.ir/projects/smartoptimizer/
 */

namespace April\Libs;

class Minifier {
	static public function minifyJS($sContent_){
		return self::minify_js($sContent_);
		#return self::getMinified($sContent_, 'http://javascript-minifier.com/raw');
	}
	
	static public function minifyCSS($sContent_){
		return self::minify_css($sContent_);
		#return self::getMinified($sContent_, 'http://cssminifier.com/raw');
	}

	static public function compress($buffer) {
		return preg_replace(array('/<!--(.*)-->/Uis',"/[[:blank:]]+/"),array('',' '),str_replace(array("\n","\r","\t"),'',$buffer));
	}

	static public function minify_css($str) {
		$res = '';
		$i=0;
		$inside_block = false;
		$current_char = '';
		while($i+1<strlen($str)){
			if($str[$i]=='"' || $str[$i]=="'") {//quoted string detected
				$res .= $quote = $str[$i++];
				$url = '';
				while ($i<strlen($str) && $str[$i]!=$quote) {
					if ($str[$i] == '\\') 
						$url .= $str[$i++];
					$url .= $str[$i++];
				}
				$res .= $url;
				$res .= $str[$i++];
				continue;
			}elseif($str[$i].$str[$i+1]=='/*'){//css comment detected
				$i+=3;
				while ($i<strlen($str) && $str[$i-1].$str[$i]!='*/') $i++;
				if ($current_char == "\n") $str[$i] = "\n";
				else $str[$i] = ' ';
			}
			
			if(strlen($str) <= $i+1) break;
			
			$current_char = $str[$i];
			if($inside_block && $current_char == '}')
				$inside_block = false;
			if($current_char == '{')
				$inside_block = true;
			if(preg_match('/[\n\r\t ]/', $current_char)) 
				$current_char = " ";
			if($current_char == " "){
				$pattern = $inside_block?'/^[^{};,:\n\r\t ]{2}$/':'/^[^{};,>+\n\r\t ]{2}$/';
				if(strlen($res) &&	preg_match($pattern, $res[strlen($res)-1].$str[$i+1]))
					$res .= $current_char;
			}else 
				$res .= $current_char;
			
			$i++;
		}
		if ($i<strlen($str) && preg_match('/[^\n\r\t ]/', $str[$i])) $res .= $str[$i];
		return $res;
	}


 	
 	
 	static public function minify_js($str) {
		$res = '';
		$maybe_regex = true;
		$i=0;
		$current_char = '';
		while ($i+1<strlen($str)) {
			if ($maybe_regex && $str[$i]=='/' && $str[$i+1]!='/' && $str[$i+1]!='*' && @$str[$i-1]!='*') {//regex detected
				if (strlen($res) && $res[strlen($res)-1] === '/') $res .= ' ';
				do {
					if ($str[$i] == '\\') {
						$res .= $str[$i++];
					} elseif ($str[$i] == '[') {
						do {
							if ($str[$i] == '\\') {
								$res .= $str[$i++];
							}
							$res .= $str[$i++];
						} while ($i<strlen($str) && $str[$i]!=']');
					}
					$res .= $str[$i++];
				} while ($i<strlen($str) && $str[$i]!='/');
				$res .= $str[$i++];
				$maybe_regex = false;
				continue;
			} elseif ($str[$i]=='"' || $str[$i]=="'") {//quoted string detected
				$quote = $str[$i];
				do {
					if ($str[$i] == '\\') {
						$res .= $str[$i++];
					}
					$res .= $str[$i++];
				} while ($i<strlen($str) && $str[$i]!=$quote);
				$res .= $str[$i++];
				continue;
			} elseif ($str[$i].$str[$i+1]=='/*' && @$str[$i+2]!='@') {//multi-line comment detected
				$i+=3;
				while ($i<strlen($str) && $str[$i-1].$str[$i]!='*/') $i++;
				if ($current_char == "\n") $str[$i] = "\n";
				else $str[$i] = ' ';
			} elseif ($str[$i].$str[$i+1]=='//') {//single-line comment detected
				$i+=2;
				while ($i<strlen($str) && $str[$i]!="\n" && $str[$i]!="\r") $i++;
			}
			
			$LF_needed = false;
			if (preg_match('/[\n\r\t ]/', $str[$i])) {
				if (strlen($res) && preg_match('/[\n ]/', $res[strlen($res)-1])) {
					if ($res[strlen($res)-1] == "\n") $LF_needed = true;
					$res = substr($res, 0, -1);
				}
				while ($i+1<strlen($str) && preg_match('/[\n\r\t ]/', $str[$i+1])) {
					if (!$LF_needed && preg_match('/[\n\r]/', $str[$i])) $LF_needed = true;
					$i++;
				}
			}
			
			if (strlen($str) <= $i+1) break;
			
			$current_char = $str[$i];
			
			if ($LF_needed) $current_char = "\n";
			elseif ($current_char == "\t") $current_char = " ";
			elseif ($current_char == "\r") $current_char = "\n";
			
			// detect unnecessary white spaces
			if ($current_char == " ") {
				if (strlen($res) &&
					(
					preg_match('/^[^(){}[\]=+\-*\/%&|!><?:~^,;"\']{2}$/', $res[strlen($res)-1].$str[$i+1]) ||
					preg_match('/^(\+\+)|(--)$/', $res[strlen($res)-1].$str[$i+1]) // for example i+ ++j;
					)) $res .= $current_char;
			} elseif ($current_char == "\n") {
				if (strlen($res) &&
					(
					preg_match('/^[^({[=+\-*%&|!><?:~^,;\/][^)}\]=+\-*%&|><?:,;\/]$/', $res[strlen($res)-1].$str[$i+1]) ||
					(strlen($res)>1 && preg_match('/^(\+\+)|(--)$/', $res[strlen($res)-2].$res[strlen($res)-1])) ||
					(strlen($str)>$i+2 && preg_match('/^(\+\+)|(--)$/', $str[$i+1].$str[$i+2])) ||
					preg_match('/^(\+\+)|(--)$/', $res[strlen($res)-1].$str[$i+1])// || // for example i+ ++j;
					)) $res .= $current_char;
			} else $res .= $current_char;
			
			// if the next charachter be a slash, detects if it is a divide operator or start of a regex
			if (preg_match('/[({[=+\-*\/%&|!><?:~^,;]/', $current_char)) $maybe_regex = true;
			elseif (!preg_match('/[\n ]/', $current_char)) $maybe_regex = false;
			
			$i++;
		}
		if ($i<strlen($str) && preg_match('/[^\n\r\t ]/', $str[$i])) $res .= $str[$i];
		return $res;
	}
}



	#static public function minifyCSS($sContent_){
	#	//remove comments ... remove tabs, spaces, newlines, etc.
	#	$buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!','',$sContent_);
	#	$buffer = str_replace(array("\r\n","\r","\n","\t",'  ','    ','    '),'',$buffer);
	#	return $buffer;
	#	
	#	return self::getMinified($sContent_, 'http://cssminifier.com/raw');
	#}

	#static public function compress($buffer) {
	#	//remove comments
	#	#$buffer = preg_replace("/((?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:\/\/.*))/", "", $buffer); //this is bugged with http:// ...
	#	//remove tabs, spaces, newlines, etc.
	#	$buffer = str_replace(array("\r\n","\r","\t","\n",'  ','    ','     '), '', $buffer);
	#	//remove other spaces before/after )
	#	$buffer = preg_replace(array('(( )+\))','(\)( )+)'), ')', $buffer);
	#	return $buffer;
	#}

	#static public function getMinified($sContent_, $sUrl_) {
	#	//https://github.com/promatik/PHP-JS-CSS-Minifier/blob/master/minifier.php
	#	$aPost = array('http' => array(
	#        'method'  => 'POST',
	#        'header'  => 'Content-type: application/x-www-form-urlencoded',
	#        'content' => http_build_query( array('input' => $sContent_) ) ) );
	#	return file_get_contents($sUrl_, false, stream_context_create($aPost));
	#}
?>
