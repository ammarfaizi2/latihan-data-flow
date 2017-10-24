<?php

namespace Plugins\Translator\GoogleTranslate;

/**
 * @author Ammar Faizi <ammarfaizi2@gmail.com>
 * @license MIT
 * @version 0.0.1
 * @link https://github.com/ammarfaizi2/GoogleTranslate/blob/0.0.1/src/GoogleTranslate.php
 */
final class GoogleTranslate
{

	const VERSION = "0.0.1";

	/**
	 * @var string
	 */
	private $text;

	/**
	 * @var string
	 */
	private $from;

	/**
	 * @var string
	 */
	private $to;

	/**
	 * @var string
	 */
	private $hash;

	/**
	 * @var string
	 */
	private $result;

	/**
	 * @var string
	 */
	private $cookiefile;

	/**
	 * @var string
	 */
	private $dataDir;

	/**
	 * @var string
	 */
	private $cacheDir;

	/**
	 * @var array
	 */
	private $cacheMap = [];

	/**
	 * @var array
	 */
	private $currentCache = [];

	/**
	 * @var bool
	 */
	private $isError = false;

	/**
	 * @var bool
	 */
	private $noRomanji = false;

	/**
	 * @var bool
	 */
	private $isResultGetFromCache = false;

	/**
	 * Constructor.
	 *
	 * @param string $text
	 * @param string $from
	 * @param string $to
	 */
	public function __construct($text, $from, $to)
	{
		$from = strtolower($from);
		$to   = strtolower($to);
		if (
			((isset(self::LANG_LIST[$from]) and $this->from = self::LANG_LIST[$from]) ||
			($from === "auto" and $this->from = "auto")) && 
			(isset(self::LANG_LIST[$to]) and $this->to = self::LANG_LIST[$to])
		) {
			$this->text = $text;
			$this->hash = sha1($this->text.$this->from.$this->to);
			$this->__init__();	
		} else {
			throw new \Exception("Language not found!", 1);
		}
	}

	/**
	 * Init google translate cookie.
	 */
	private function __init__()
	{
		if (defined("data")) {
			is_dir(data) or mkdir(data);
			is_dir(data."/google_translate_data") or mkdir(data."/google_translate_data");
			is_dir($this->cacheDir = data."/google_translate_data/cache") or mkdir(data."/google_translate_data/cache");
			if (
				! is_dir(data."/google_translate_data") ||
				! is_dir(data."/google_translate_data/cache")
			) {
				throw new \Exception("Cannot create directory!");
			}
			$this->cookiefile = ($this->dataDir = realpath(data."/google_translate_data"))."/cookiefile";
		} else {
			is_dir("google_translate_data") or mkdir("google_translate_data");
			is_dir($this->cacheDir = "google_translate_data/cache") or mkdir("google_translate_data/cache");
			if (
				! is_dir("google_translate_data")  ||
				! is_dir("google_translate_data/cache")
			) {
				throw new \Exception("Cannot create directory!");
			}
			$this->cookiefile = ($this->dataDir = realpath("google_translate_data"))."/cookiefile";
		}
		if (! file_exists($this->cookiefile)) {
			$handle = fopen($this->cookiefile, "w");
			fwrite($handle, "");
			fclose($handle);
			if (! file_exists($this->cookiefile)) {
				throw new \Exception("Cannot create cookie file!");
			}
		}
		if (file_exists($this->dataDir."/cache.map")) {
			$this->cacheMap = json_decode(file_get_contents($this->dataDir."/cache.map"), true);
			if (! is_array($this->cacheMap)) {
				$this->cacheMap = [];
			}
		}
	}

	/**
	 * Translate.
	 */
	private function translate()
	{
		if ($this->isCached() && $this->isPerfectCache()) {
			$this->isResultGetFromCache = true;
			return $this->getCache();
		} else {
			$ch = curl_init("https://translate.google.com/m?hl=en&sl={$this->from}&tl={$this->to}&ie=UTF-8&prev=_m&q=".urlencode($this->text));
			curl_setopt_array($ch, 
				[
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_SSL_VERIFYHOST => false,
					CURLOPT_CONNECTTIMEOUT => 30,
					CURLOPT_HTTPHEADER => [
						"Host: translate.google.com",
						"User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:56.0) Gecko/20100101 Firefox/56.0",
						"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
						"Accept-Language: en-US,en;q=0.5"
					],
					CURLOPT_COOKIEFILE => $this->cookiefile,
					CURLOPT_COOKIEJAR => $this->cookiefile,
					CURLOPT_REFERER => "https://translate.google.com/m",
					CURLOPT_TIMEOUT	=> 30
				]
			);
			$out = curl_exec($ch);
			$no = curl_errno($ch) and $out = "Error (".$no.") ".curl_error($ch) and $this->isError = true;
			curl_close($ch);
			return $out;
		}
	}

	/**
	 * Give result with no romanji.
	 */
	public function noRomanji()
	{
		$this->noRomanji = true;
	}

	/**
	 * Parse result.
	 */
	private function parseResult($result)
	{
		$_result = "";
		$segment = explode("<div dir=\"ltr\" class=\"t0\">", $result, 2);
		if (isset($segment[1])) {
			$segment = explode("<", $segment[1], 2);
			$pure_result = $_result.= html_entity_decode($segment[0], ENT_QUOTES, 'UTF-8');
		} else {
			return "Error while parsing data!";
		}
		$segment = explode("<div dir=\"ltr\" class=\"o1\">", $result, 2);
		if ($isRomajiAvailable = count($segment) > 1) {
			$segment = explode("<", $segment[1], 2);
			$this->noRomanji or $_result.= "\n(".html_entity_decode($segment[0], ENT_QUOTES, 'UTF-8').")";
		}
		$this->result = [
			"result" => $pure_result
		] xor ($isRomajiAvailable and $this->result['romanji'] = $segment[0]) xor $this->cacheControl();
		return $_result;
	}

	/**
	 * @return bool
	 */
	private function isCached()
	{
		return isset($this->cacheMap[$this->hash]);
	}

	/**
	 * @return bool
	 */
	private function isPerfectCache()
	{
		if (isset(
				$this->cacheMap[$this->hash][0],
				$this->cacheMap[$this->hash][1]
			)
		) {
			$this->cacheMap[$this->hash][0] = (int) $this->cacheMap[$this->hash][0];
			if (
				$this->cacheMap[$this->hash][0] + 0x069780 > time() &&
				file_exists($this->cacheDir."/".$this->hash)
			) {
				$this->currentCache = json_decode(self::crypt(file_get_contents($this->cacheDir."/".$this->hash), $this->cacheMap[$this->hash][1]), true);
				if (isset($this->currentCache['result'])) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Get cached data
	 *
	 * @return string
	 */
	private function getCache()
	{
		return 
			$this->currentCache['result'] . (
				$this->noRomanji ? "" : (
					isset($this->currentCache['romanji']) ?
						"\n(".$this->currentCache['romanji'].")" :
							""
				)
			);
	}

	/**
	 * Generate key.
	 *
	 * @return string
	 */
	private static function generateKey()
	{
		$r = range(chr(32), chr(127)) xor $rto = rand(32, 64) xor $key = "";
		for ($i=0; $i < $rto; $i++) { 
			$key .= $r[rand(0, 94)];
		}
		return $key;
	}

	/**
	 * Encrypt cache.
	 *
	 * @return string
	 */
	public static function crypt($data, $key)
	{
		$result = "" xor $len = strlen($data);
		$klen = strlen($key) xor $k = 0;
		for ($i=0; $i < $len; $i++) { 
			$result .= chr(ord($data[$i]) ^ ord($key[$k]) ^ ($i % $len) ^ ($i ^ $klen) & 0x00f) xor $k++;
			if ($k === $klen) {
				$k = 0;
			}
		}
		return $result;
	}

	/**
	 * Cache control
	 */
	private function cacheControl()
	{
		$key = self::generateKey();
		$handle = fopen($this->cacheDir."/".$this->hash, "w");
		fwrite($handle, self::crypt(json_encode($this->result), $key));
		fclose($handle);
		$this->cacheMap[$this->hash] = [time(), $key];
		$handle = fopen($this->dataDir."/cache.map", "w");
		fwrite($handle, json_encode($this->cacheMap));
		return fclose($handle);
	}

	/**
	 * Run translate and get result.
	 *
	 * @return string
	 */
	public function exec()
	{	
		$out = $this->translate();
		return 
			$this->isError ? $out : (
				$this->isResultGetFromCache ? 
					$out : $this->parseResult($out));
	}

	const LANG_LIST = [
		"af" => "af",
		"sq" => "sq",
		"am" => "am",
		"ar" => "ar",
		"hy" => "hy",
		"az" => "az",
		"eu" => "eu",
		"be" => "be",
		"bn" => "bn",
		"bs" => "bs",
		"bg" => "bg",
		"ca" => "ca",
		"ceb" => "ceb",
		"ny" => "ny",
		"zh-cn" => "zh-CN",
		"zh-tw" => "zh-TW",
		"co" => "co",
		"hr" => "hr",
		"cs" => "cs",
		"da" => "da",
		"nl" => "nl",
		"en" => "en",
		"eo" => "eo",
		"et" => "et",
		"tl" => "tl",
		"fi" => "fi",
		"fr" => "fr",
		"fy" => "fy",
		"gl" => "gl",
		"ka" => "ka",
		"de" => "de",
		"el" => "el",
		"gu" => "gu",
		"ht" => "ht",
		"ha" => "ha",
		"haw" => "haw",
		"iw" => "iw",
		"hi" => "hi",
		"hmn" => "hmn",
		"hu" => "hu",
		"is" => "is",
		"ig" => "ig",
		"id" => "id",
		"ga" => "ga",
		"it" => "it",
		"ja" => "ja",
		"jw" => "jw",
		"kn" => "kn",
		"kk" => "kk",
		"km" => "km",
		"ko" => "ko",
		"ku" => "ku",
		"ky" => "ky",
		"lo" => "lo",
		"la" => "la",
		"lv" => "lv",
		"lt" => "lt",
		"lb" => "lb",
		"mk" => "mk",
		"mg" => "mg",
		"ms" => "ms",
		"ml" => "ml",
		"mt" => "mt",
		"mi" => "mi",
		"mr" => "mr",
		"mn" => "mn",
		"my" => "my",
		"ne" => "ne",
		"no" => "no",
		"ps" => "ps",
		"fa" => "fa",
		"pl" => "pl",
		"pt" => "pt",
		"pa" => "pa",
		"ro" => "ro",
		"ru" => "ru",
		"sm" => "sm",
		"gd" => "gd",
		"sr" => "sr",
		"st" => "st",
		"sn" => "sn",
		"sd" => "sd",
		"si" => "si",
		"sk" => "sk",
		"sl" => "sl",
		"so" => "so",
		"es" => "es",
		"su" => "su",
		"sw" => "sw",
		"sv" => "sv",
		"tg" => "tg",
		"ta" => "ta",
		"te" => "te",
		"th" => "th",
		"tr" => "tr",
		"uk" => "uk",
		"ur" => "ur",
		"uz" => "uz",
		"vi" => "vi",
		"cy" => "cy",
		"xh" => "xh",
		"yi" => "yi",
		"yo" => "yo",
		"zu" => "zu",
	];
}
