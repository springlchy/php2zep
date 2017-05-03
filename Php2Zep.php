<?php

/**
 * +-----------------------------------------------------------------+
 * |  php2zep - A tool transating php language into zephir language  | 
 * +-----------------------------------------------------------------+
 * | With this tool, you can translate your php scripts into zephir  |
 * | scripts, and then, you can build php extensions with zephir.    |
 * | So, even your are not familliar with C or zephir, you can still |
 * | writing php extensions with pure PHP. Of course, this tool can  |
 * | not deal with all kinds of php code, there are some restirctions|
 * |                                                                 |
 * +-----------------------------------------------------------------+
 * |  Author: springlchy <sisbeau@126.com>  All Rights Reserved      |
 * +-----------------------------------------------------------------+
 */
class Php2Zep
{
	private $currentFile = '';
	private $currentLine = 0;
	private $currentMethod = '';
	private $currentClass = '';
	private $currentStaticVars = [];
	private $staticLetLines = [];
	private $foundFunction = false;

	private $lineFlag = '';
	private $keywords = ['object', 'string', 'array', 'function', 'for', 'foreach', 'while', 'do', 'if', 'else', 'elseif', 'type', 'unset', 'empty', 'isset', 'boolean', 'integer', 'int', 'float', 'double', 'class', 'public', 'private', 'static', 'protected', 'extends', 'else', 'elseif', 'throw', 'exception', 'final', 'abstract', 'parent', 'self', 'range', 'in', 'instanceof', 'default', 'switch', 'inline', 'var', 'let', 'const'];
	private $ignoreVars = ['this','_GET', '_POST', '_SERVER', '_FILES', '_COOKIE', '_SESSION'];
	private $varPrefix = 'x_';

	private $currentFuncLines;

	/**
	 * 针对函数体
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function getVars($str)
	{
		// 类变量
		$hasclassStaticProperties = preg_match_all('/[a-zA-Z_][a-zA-Z0-9_]*::(\$[a-zA-Z_][a-zA-Z0-9_]*)/', $str, $staticMatches);
		if ($hasclassStaticProperties) {
			$this->currentStaticVars = array_unique($staticMatches[1]);
		} else {
			$this->currentStaticVars = [];
		}
		// 其它变量
		$hasVars = preg_match_all('/[^:]{2}(\$[a-zA-Z_][a-zA-Z0-9_]*)/', $str, $matches);
		if ($hasVars) {
			return array_unique($matches[1]);
		}

		return [];
	}

	/**
	 * 针对函数参数
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function getArgs($str)
	{
		$hasFunc = preg_match_all('/\bfunction\s+\w+\([^\)]*\)/', $str, $func);
		if ($hasFunc) {
			$hasArgs = preg_match_all('/\$\w+/', $func[0][0], $funcArgs);
			if ($hasArgs) {
				return $funcArgs[0];
			}
		}

		return [];
	}

	/**
	 * 针对单行或多行
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function convertVars($str)
	{
		// 先替换类变量
		if (!empty($this->currentStaticVars)) {
			$str = preg_replace('/([a-zA-Z_][a-zA-Z0-9_]*)::
				\$([a-zA-Z_][a-zA-Z0-9_]*)/', "$1::$2", $str);
		}
		// 与类变量同名的其它变量需要加前缀
		$extendedKeywords = array_merge($this->keywords, $this->currentStaticVars);

		return preg_replace_callback('/\$(\w+)/', function($matches) use ($extendedKeywords){
			if (in_array($matches[1], $this->ignoreVars)) {
				return $matches[1]; // $this
			} elseif (in_array($matches[1], $extendedKeywords)) {
				return $this->varPrefix . $matches[1];
			} else {
				return $matches[1];
			}
		}, $str);
	}

	/**
	 * 针对单行
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function convertForeach($str)
	{
		$hasForeach = preg_match('/\bforeach\s*\(\s*([^\s]+)\s+as\s+([^\s\)]+)\s*(=>\s*\$\w+)?\)/', $str, $matches, PREG_OFFSET_CAPTURE); //([^\s]+)\s*\) (=>\s*\$\w+)?

		if ($hasForeach) {
			$this->lineFlag = 'foreach';
			//print_r($matches);
			if (count($matches) == 4) {
				$matches[3][0] = trim(str_replace("=>", "", $matches[3][0]));
				$newExpr = 'for ' . $matches[2][0] . ',' . $matches[3][0]  . ' in ' . $matches[1][0] ;
				//$str = substr($str, 0, $matches[0][1]) . $newExpr . substr($str, $matches[0][1] + strlen($matches[0][0]));
			} else {
				$newExpr = 'for ' . $matches[2][0] . ' in ' . $matches[1][0];
			}
			$str = substr($str, 0, $matches[0][1]) . $newExpr . substr($str, $matches[0][1] + strlen($matches[0][0]));
		}

		return $str;
	}

	/**
	 * 针对单行
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function convertAssigns($str)
	{
		$hasAssign = preg_match_all('/([^\s\(]+?\s*[^>=<!]=)/', $str, $matches, PREG_OFFSET_CAPTURE); //\+\-\*\/\&\|\^

		$hasLet = false;
		if ($hasAssign) {
			$offset = 0;
			//print_r($matches);
			foreach($matches[0] as $k => $mstr) {
				//echo $str, PHP_EOL;
				$lenMstr = strlen($mstr[0]);
				//echo $mstr[0];
				$c = $str[$lenMstr + $mstr[1] + $offset];
				$c01 = $str[$lenMstr + $mstr[1] + $offset - 2];
				//echo "#", $c,' ', $c01, PHP_EOL;
				if (!($c == '=' || $c == '>' || $c01 == '=' || $c01 == '>' || $c01 == '<' || $c01 == '!')) {
					// check special
					$j = $mstr[1]-1;
					while($j>=0 && $str[$j] == ' ') {
						$j--;
					}

					if ($j>=0 && $str[$j] == '.') {
						if (preg_match('/^\s*/', $str, $spaces)) {
							$str = $spaces[0] . 'let ' . ltrim($str);
						} else {
							$str = 'let ' . ltrim($str);
						}
					} else {
						$newExpr = "let " . $matches[1][$k][0] . ' ';
						$remainStr = substr($str, $offset+$mstr[1]+$lenMstr);
						//echo 'rem= ', $remainStr, PHP_EOL;
						if (preg_match('/(\s*)if\s*\(/', $str, $spaces)) {
							$varName = rtrim($matches[1][$k][0], " =");
							$remainLen = strlen($remainStr);
							$hasParences = false;
							$leftParences = 0;
							$rightParences = 0;
							for($x = 0; $x < $remainLen; $x++) {
								if ($remainStr[$x] == '(') {
									$hasParences = true;
									$leftParences++;
								} elseif ($remainStr[$x] == ')') {
									$rightParences++;
									if (!$hasParences) {
										break;
									} elseif ($rightParences>$leftParences) {
										break;
									}
								}
							}
			
							//echo ": ", substr($remainStr, 0, $x), PHP_EOL;
							$newExpr = $spaces[1] . $newExpr . substr($remainStr, 0, $x) . ";\n";
							//echo "newExpr= ", $newExpr, PHP_EOL;
							$remainStr = substr($remainStr, $x);
							$str = $newExpr .  substr($str, 0, $mstr[1]+$offset) . $varName . $remainStr;
							$offset += (strlen($newExpr) + strlen($varName) - $lenMstr - $x);
						} elseif(preg_match('/(\s*)while\s*\(/', $str, $spaces)) {
							$varName = rtrim($matches[1][$k][0], " =");
							$remainLen = strlen($remainStr);
							$hasParences = false;
							$leftParences = 0;
							$rightParences = 0;
							for($x = 0; $x < $remainLen; $x++) {
								if ($remainStr[$x] == '(') {
									$hasParences = true;
									$leftParences++;
								} elseif ($remainStr[$x] == ')') {
									$rightParences++;
									if (!$hasParences) {
										break;
									} elseif ($rightParences>$leftParences) {
										break;
									}
								}
							}
			
							//echo ": ", substr($remainStr, 0, $x), PHP_EOL;
							$newExpr = $spaces[1] . $newExpr . substr($remainStr, 0, $x) . ";\n";
							//echo "newExpr= ", $newExpr, PHP_EOL;
							$remainStr = substr($remainStr, $x);
							$str = $newExpr .  substr($str, 0, $mstr[1]+$offset) . $varName . $remainStr;
							$offset += (strlen($newExpr) + strlen($varName) - $lenMstr - $x);

							$whileEnd = $spaces[1] . '}';
							for($xx = 0; $xx < count($this->currentFuncLines); $xx++) {
								if (strpos($this->currentFuncLines[$xx], $whileEnd) === 0) {
									break;
								}
							}
							$this->currentFuncLines[$xx-1] .= ("\n" . "    " . $newExpr);
						} else {
							$str = substr($str, 0, $mstr[1]+$offset) . $newExpr . $remainStr;
							$offset += strlen($newExpr) - $lenMstr;								
						}
					}

					if (preg_match('/(\s*)return /', $str, $returnMatch, PREG_OFFSET_CAPTURE)) {
						//print_r($returnMatch);
						$letExpr = substr($str, strlen($returnMatch[0][0]) + 4);
						//echo $letExpr,PHP_EOL;
						$lenExpr = strlen($letExpr);
						for($i=0; $i<$lenExpr; $i++) {
							if ($letExpr[$i] == '=') {
								break;
							}
						}
						$xvar = substr($letExpr, 0, $i);
						$letLine = $returnMatch[1][0] . 'let ' . $letExpr;
						$letLine .= ($returnMatch[1][0] . 'return ' . $xvar . ";\n");
						$str = $letLine;
					}
					$hasLet = true;
				}
				//echo "typeof " . $matches[1][$k] . ' == "' . $type . '"', "\n";
			}
		}

		return $str;
	}

	/**
	 * 针对单行
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function convertIsType($str)
	{
		$isTypeArr = [
			'/\bis_array\s*\(([^ \)]+)\)/' => 'array',
			'/\bis_object\s*\(([^ \)]+)\)/' => 'object',
			'/\bis_callable\s*\(([^ \)]+)\)/' => 'callable',
			'/\bis_string\s*\(([^ \)]+)\)/' => 'string',
			'/\bis_integer\s*\(([^ \)]+)\)/' => 'integer',
			'/\bis_double\s*\(([^ \)]+)\)/' => 'double',
			'/\bis_float\s*\(([^ \)]+)\)/' => 'double',
			'/\bis_boolean\s*\(([^ \)]+)\)/' => 'boolean',
			'/\bis_int\s*\(([^ \)]+)\)/' => 'integer',
			'/\bis_resource\s*\(([^ \)]+)\)/' => 'resource',
		];
		
		foreach ($isTypeArr as $pattern => $type) {
			if (preg_match_all($pattern, $str, $matches, PREG_OFFSET_CAPTURE)) {
				//print_r($matches);
				$offset = 0;
				foreach($matches[0] as $k => $mstr) {
					//echo "typeof " . $matches[1][$k] . ' == "' . $type . '"', "\n";
					$newExpr = "(typeof " . $matches[1][$k][0] . ' == "' . $type . '")';
					$lenMstr = strlen($mstr[0]);
					$str = substr($str, 0, $mstr[1]+$offset) . $newExpr . substr($str, $offset+$mstr[1]+$lenMstr);
					$offset += strlen($newExpr) - $lenMstr;
				}
			}
		}

		if (preg_match_all('/\bis_number\s*\(([^ \)]+)\)/', $str, $matches, PREG_OFFSET_CAPTURE)) {
			$offset = 0;
			foreach ($matches[0] as $k => $mstr) {
				$newExpr = "(typeof " . $matches[1][$k][0] . ' == "integer" || typeof ' . $matches[1][$k][0] . ' == "double")';
				$lenMstr = strlen($mstr[0]);
				$str = substr($str, 0, $mstr[1]+$offset) . $newExpr . substr($str, $offset+$mstr[1]+$lenMstr);
				$offset += strlen($newExpr) - $lenMstr;
			}
		}

		return $str;
	}

	/**
	 * 针对单行
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function convertEmpty($str)
	{
		$hasEmpty = preg_match_all('/\bempty\s*\(([^ \)]+)\)/', $str, $matches, PREG_OFFSET_CAPTURE);
		if ($hasEmpty) {
			$offset = 0;
			foreach ($matches[0] as $k => $mstr) {
				$newExpr = "(empty " . $matches[1][$k][0] . ')';
				$lenMstr = strlen($mstr[0]);
				$str = substr($str, 0, $mstr[1]+$offset) . $newExpr . substr($str, $offset+$mstr[1]+$lenMstr);
				$offset += strlen($newExpr) - $lenMstr;
			}
		}

		return $str;
	}

	/**
	 * 针对单行
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function convertIsset($str)
	{
		$hasIsset = preg_match_all('/\bisset\s*\(([^ \)]+)\)/', $str, $matches, PREG_OFFSET_CAPTURE);
		if ($hasIsset) {
			$offset = 0;
			foreach ($matches[0] as $k => $mstr) {
				$newExpr = "(isset " . $matches[1][$k][0] . ')';
				$lenMstr = strlen($mstr[0]);
				$str = substr($str, 0, $mstr[1]+$offset) . $newExpr . substr($str, $offset+$mstr[1]+$lenMstr);
				$offset += strlen($newExpr) - $lenMstr;
			}
		}

		return $str;	
	}

	/**
	 * 针对单行
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function convertUnset($str)
	{
		$hasIsset = preg_match_all('/\bunset\s*\(([^\)]+)\)/', $str, $matches, PREG_OFFSET_CAPTURE);
		if ($hasIsset) {
			$offset = 0;
			foreach ($matches[0] as $k => $mstr) {
				if (strpos($matches[1][$k][0], ',')) {
					$newExpr = '';
					$toUnsetArr = explode(',', $matches[1][$k][0]);
					foreach ($toUnsetArr as $v) {
						$newExpr .= ("unset " . $v . ";\n");
					}
				} else {
					$newExpr = "unset " . $matches[1][$k][0] . '';
				}
				
				$lenMstr = strlen($mstr[0]);
				$str = substr($str, 0, $mstr[1]+$offset) . $newExpr . substr($str, $offset+$mstr[1]+$lenMstr);
				$offset += strlen($newExpr) - $lenMstr;
			}
		}

		return $str;	
	}

	/**
	 * 针对单行
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function convertQuote($str)
	{
		//$str = str_replace("'", '"', $str);
		$len = strlen($str);
		$i = 0;
		$inQuote = false;
		$inTimes = 0;
		$inDoubleQuote = false;
		$dqTimes = 0;
		while($i < $len) {
			if ($str[$i] == "'" && !$inDoubleQuote) {
				if (!$inQuote) {
					$str[$i] = '"';
					$inQuote = true;
					$inTimes++;
				} else {
					//if ($i>0 && $str[$i-1] != '\\') {
						$str[$i] = '"';
						$inQuote = false;
						$inTimes++;
					//}
				}
			} elseif ($str[$i] == '"') {
				if ($inQuote) {
					$str[$i] = "'";
					//$str = substr($str, 0, $i) . '\"' . substr($str, $i+1);
					//$i++;
					//$len++;
				} else {
					if ($inDoubleQuote) {
						$inDoubleQuote = false;
						$dqTimes = 0;
					} else {
						$inDoubleQuote = true;
						$dqTimes++;						
					}
				}
			}
			$i++;
		}

		return $str;
	}

	public function convertFuncArg($str)
	{
		return $this->convertQuote(preg_replace_callback('/(\w+)\s+\$(\w+)/', function($matches){
			if (in_array($matches[2], $this->ignoreVars)) {
				return $matches[1] . ' $' . $matches[2]; // $this
			} elseif (in_array($matches[2], $this->keywords)) {
				return $matches[1] . ' ' . $this->varPrefix . $matches[2];
			} else {
				return $matches[1] . ' ' . $matches[2];
			}
		}, preg_replace_callback('/(\(|,\s*)\$(\w+)/', function($matches){
			if (in_array($matches[2], $this->ignoreVars)) {
				return $matches[1] . 'var $' . $matches[2]; // $this
			} elseif (in_array($matches[2], $this->keywords)) {
				return $matches[1] . ' var ' . $this->varPrefix . $matches[2];
			} else {
				return $matches[1] . ' var ' . $matches[2];
			}
		}, $str)));
	}

	/**
	 * 针对多行
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function convertArray($str)
	{
		if (preg_match_all('/\barray\(/', $str, $matches, PREG_OFFSET_CAPTURE)) {
			$offset = 0;
			//print_r($matches);//exit();
			$strLen = strlen($str);
			foreach ($matches[0] as $k => $mstr) {
				$i = $mstr[1] + strlen($mstr[0]) + $offset;$l = 0;$r = 0;
				while($i < $strLen) {
					if ($str[$i] == '(') {
						$l++;
					} elseif ($str[$i] == ')') {
						$r++;
					}

					if ($r-$l === 1) {
						$str[$i] = ']';
						break;
					}
					$i++;
				}
				if ($i >= $strLen) {
					echo $this->currentFile, "\n";
					exit();
				}

				$newExpr = '[';
				$lenMstr = strlen($mstr[0]);
				$str = substr($str, 0, $mstr[1]+$offset) . $newExpr . substr($str, $offset+$mstr[1]+$lenMstr);
				$offset += strlen($newExpr) - $lenMstr;

				$strLen = strlen($str);
			}
		}

		return str_replace("=>", ":", $str);
	}

	/**
	 * 转换for
	 * @param  [type] $lines [description]
	 * @return [type]        [description]
	 */
	public function convertFor($lines)
	{
		$totalLines = count($lines);
		for($i = 0; $i < $totalLines; $i++) {
			$hasFor = preg_match('/(\s*)\bfor\s*\(([^;]+);([^;]+);(.+)/', $lines[$i], $matches, PREG_OFFSET_CAPTURE);
			if ($hasFor) {
				//print_r($matches);
				$initLine = $matches[1][0] . trim($matches[2][0]) . "\n";
				$lines[$i] = $matches[1][0] . 'while (' . trim($matches[3][0]) . ')';
				if (preg_match('/\{\s*$/', $matches[4][0])) {
					$lines[$i] .= (' {' . "\n");
					$matches[4][0] = rtrim($matches[4][0], "\r\n {");
				}
				$matches[4][0] = rtrim($matches[4][0]);
				$incExpr = $matches[1][0] . '    ' . substr(ltrim($matches[4][0]), 0, -1) . ";\n";

				array_splice($lines, $i, 0, $initLine);
				$i++;
				$totalLines++;

				for($j = $i; $j < $totalLines; $j++) {
					if(strpos($lines[$j], $matches[1][0] . '}') === 0) {
						array_splice($lines, $j, 0, $incExpr);
						$totalLines++;
						break;
					}
				}
				//print_r($matches);
			}
		}
		
		return $lines;
	}

	public function convertMagic($str)
	{
		$rules = [
			'/\b__LINE__\b/' => $this->currentLine,
			'/\b__FILE__\b/' => $this->currentFile,
			'/\b__METHOD__\b/' => $this->currentMethod,
			'/\b__CLASS__\b/' => $this->currentClass . '::' . $this->currentMethod
		];

		foreach ($rules as $pattern => $replacement) {
			$str = preg_replace($pattern, '"' . $replacement . '"', $str);
		}

		return $str;
	}

	public function convertNamespace($str)
	{
		if (preg_match('#\byii(\\\\[0-9a-zA-Z_]+)+\b#', $str, $matches, PREG_OFFSET_CAPTURE)) {
			return substr($str, 0, $matches[0][1]) . implode("\\", array_map(function($v){return ucfirst($v);}, explode("\\",  $matches[0][0]))) . substr($str, $matches[0][1]+strlen($matches[0][0]));
		}
		return $str;
	}

	/**
	 * convert static::method or new static()
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function convertStatic($str)
	{
		return preg_replace('/\bnew\s+static\(/', 'new ' . $this->currentClass . '(', preg_replace('/\bstatic::(\w+)/', "self::$1", $str));
	}

	/**
	 * convert Yii::app->a = b to let app = Yii::app; let app->a = b;
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function convertStaticPointerSet($str)
	{
		if(preg_match('/([a-zA-Z_][a-zA-Z0-9_]*+)::([a-zA-Z_][a-zA-Z0-9_]*+)\->([^=]+)=([^=])/', $str, $matches)) {
			$this->staticLetLines[] = '    let ' . $matches[2] . ' = ' . $matches[1] . '::' . $matches[2] . ";\n";
			print_r($this->staticLetLines);
			return preg_replace('/([a-zA-Z_][a-zA-Z0-9_]*+)::([a-zA-Z_][a-zA-Z0-9_]*+)\->([^=]+)=([^=])/', "$2->$3 = $4", $str);
		}

		return $str;
	}

	/**
	 * catch (Exception $e) => catch Exception, $e
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function convertTryCatch($str)
	{
		if (preg_match('/\bcatch\s*\((\\\?([a-zA-Z_][a-zA-Z0-9_]*+\\\)*[a-zA-Z_][a-zA-Z0-9_]*+)\s+(\$[a-zA-Z_][a-zA-Z0-9_]*+)\s*\)/', $str, $matches, PREG_OFFSET_CAPTURE)) {
			$this->lineFlag = 'catch';
			$newCatch = 'catch ' . $matches[1][0] . ', ' . $matches[3][0];
			return substr($str, 0, $matches[0][1]) . $newCatch . substr($str, $matches[0][1]+strlen($matches[0][0]));
		}

		return $str;
	}
	/**
	 * 转换一行
	 * @param  [type] $line [description]
	 * @return [type]       [description]
	 */
	public function handleLine($line)
	{
		$convertors = [
			'convertAssigns',
			'convertIsType',
			'convertEmpty',
			'convertIsset',
			'convertUnset',
			'convertQuote',
			'convertVars',
			'convertMagic',
			'convertNamespace',
			'convertStatic',
		];

		$line = $this->convertForeach($line);
		if ($this->lineFlag == 'foreach') {
			$this->lineFlag = '';
			return $this->convertStatic($this->convertVars($this->convertQuote($line)));
		}

		$line = $this->convertTryCatch($line);
		if ($this->lineFlag == 'catch') {
			$this->lineFlag = '';
			return $this->convertVars($line);
		}

		foreach ($convertors as $convertor) {
			$line = $this->{$convertor}($line);
		}
		$this->lineFlag = '';
		$line = $this->convertStaticPointerSet($line);

		return $line;
	}
	public function handleOther($str)
	{
		return $this->convertMagic($this->convertVars($this->convertQuote($str)));
	}
	/**
	 * 转换一个函数
	 * @param  [type] $lines [description]
	 * @return [type]        [description]
	 */
	public function handleFunc($lines, $spaceStr)
	{
		$args = $this->getArgs($lines[0]);
		$funcStr = implode('', $lines);
		$vars = $this->getVars($funcStr);

		$keywords = ['$this', '$_GET', '$_POST', '$_SERVER', '$_FILES', '$_COOKIE', '$_SESSION'];

		$toAnounceVars = array_merge(array_diff($vars, array_merge($args, $keywords)));
		$funcBegin = 0;
		if (count($toAnounceVars) > 0) {
			$announceLine = $spaceStr . '    var ' . implode(', ', $toAnounceVars) . ';' . "\n";
			//$funcBegin = 0;
			while (true) {
				if (preg_match('/\)\s*\{/', $lines[$funcBegin]) || preg_match('/\s*\{/', $lines[$funcBegin])) {
					break;
				}
				$funcBegin++;
			}
			array_splice($lines, $funcBegin+1, 0, $announceLine);
		}

		$lines = $this->convertFor($lines);

		$totalLines = count($lines);
		$lines[0] = $this->convertMagic($this->convertFuncArg($lines[0]));

		$this->currentFuncLines = $lines;
		for ($i = 1; $i < $totalLines; $i++) {
			$this->currentLine = $this->currentLine + 1;
			$lines[$i] = $this->handleLine($this->currentFuncLines[$i]);
		}

		// for static let expression
		if (!empty($this->staticLetLines)) {
			$this->staticLetLines = array_unique($this->staticLetLines);

			$i = 2;
			foreach ($this->staticLetLines as $letLine) {
				array_splice($lines, $funcBegin + $i, 0, $spaceStr . $letLine);
				$i++;
			}
		}

		$funcStr = implode('', $lines);

		if (!empty($this->staticLetLines)) {
			$funcStr = preg_replace('/\b([a-zA-Z_][a-zA-Z0-9_]*+)::([a-zA-Z_][a-zA-Z0-9_]*+)\->/', "$2->", $funcStr);
		}

		$this->staticLetLines = [];	
		$this->currentStaticVars = [];

		return $funcStr;
	}

	public function handleFile($filein)
	{
		$firstFuncStart = 0;
		$classLineStart = 0;
		$foundFunction = false;
		$otherPropertyDef = [];

		$this->currentFile = basename($filein);
		$this->currentClass = pathinfo($this->currentFile, PATHINFO_FILENAME);

		echo "handling File ", $filein, "\n";
		$lines = file($filein);

	    $resultLines = [];
	    $totalLines = count($lines);
	    for($i = 1; $i < $totalLines;) {
	        if (preg_match('/^\s*\/\*/', $lines[$i])) {
	            //$resultLines[] = $lines[$i];
	            $i++;
	            while ($i<$totalLines) {
	                //$resultLines[] = $lines[$i];
	                if (preg_match('/\*\/\s*$/', $lines[$i++])) {
	                    break;
	                }
	            }
	        } else {
	            if (preg_match('/(\s*)(public|private|protected)?\s+(static\s+)?function\s+(?P<methodname>\w+)\(/', $lines[$i], $matches)) {
	            	$func = [];
	            	if (!preg_match('/;$/', rtrim($lines[$i]))) {
		                $func[] = $lines[$i];
		                $j = $i + 1;
		                while ($j < $totalLines) {
		                    $func[] = $lines[$j];
		                    if (strpos($lines[$j++], $matches[1] . '}') === 0) {
		                        break;
		                    } else {
		                    }
		                }
		                if ($j >= $totalLines) {
		                	echo $filein, ' Line: ', $j, ' TotalLines: ', $totalLines, "\n";
		                	//exit("End");
		                }
		                $i = $j;

		                $this->currentMethod = $matches['methodname'];
		                $this->currentLine = $i;
		                $resultLines[] = $this->handleFunc($func, $matches[1]);
	            	} else {
	            		$resultLines[] = $this->convertFuncArg($lines[$i]);
	            		$i++;
	            	}
	            	
	            	if (!$foundFunction) {
	            		$firstFuncStart = count($resultLines);
	            		$foundFunction = true;
	            	}
	            } else {
	            	if (strpos($lines[$i], "namespace ") === 0) {
	            		$namespaceLine = substr(rtrim($lines[$i], ";\r\n "), 10);
	            		$namespaceLine = implode("\\", array_map(function($v){return ucfirst($v);}, explode("\\", $namespaceLine)));

	            		$resultLines[] = "namespace " . $namespaceLine . ";\n";
	            	} elseif (strpos($lines[$i], "use ") === 0) {
	            		$useLine = substr(rtrim($lines[$i], ";\r\n "), 4);
	            		$useLine = implode("\\", array_map(function($v){return ucfirst($v);}, explode("\\", $useLine)));

	            		$resultLines[] = "use " . $useLine . ";\n";
	            	} elseif (preg_match('/^(abstract )?class (?P<classname>\w+)/', $lines[$i], $matches)) {
	            		$resultLines[] = $lines[$i];
	            		//$this->currentClass = $matches['classname'];
	            		$classLineStart = count($resultLines);
	            	} elseif ($foundFunction && preg_match('/(\s*)(public|private|protected)?\s+(static\s+)?\$\w+/', $lines[$i])) {
	            		$otherPropertyDef[] = str_replace('$', '', $lines[$i]);
	            	} else {
	            		$resultLines[] = $this->handleOther($lines[$i]);
	            	}
	                
	                $i++;
	            }
	        }
	    }

	    if (!empty($otherPropertyDef)) {
	    	foreach ($otherPropertyDef as $propertyDef) {
	    		array_splice($resultLines, $firstFuncStart-1, 0, $propertyDef);
	    	}
	    }
	    return $this->convertArray(implode('', $resultLines));
	}

	public function handleDir($dir, $outDir) {
		$dh = opendir($dir);

		while ($file = readdir($dh)) {
			if (pathinfo($file, PATHINFO_EXTENSION) == "php") {
				if (!file_exists($outDir)) {
					mkdir($outDir);
				}
				$outFile = $outDir . DIRECTORY_SEPARATOR . pathinfo($file, PATHINFO_FILENAME) . ".zep";
				file_put_contents($outFile, $this->handleFile($dir . DIRECTORY_SEPARATOR . $file));
				echo $dir . DIRECTORY_SEPARATOR . $file , " finished!", "\n";
			} else if (is_dir($dir . DIRECTORY_SEPARATOR . $file) && $file != "." && $file != "..") {
				//closedir($dh);
				//echo "in dir ", $file, "\n";
				$this->handleDir($dir . DIRECTORY_SEPARATOR . $file, $outDir . DIRECTORY_SEPARATOR . $file);
			}
			
		}

		closedir($dh);
	}
}
