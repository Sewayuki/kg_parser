<?php

/*
*/
class KageParser
{
	protected $kg_url;

	function __construct(){
		$this->kg_url = "http://www.fansubs.ru/";
	}

	public function getAnimeInfo($aid){
		$html = file_get_contents($this->kg_url . 'base.php?id=' . $aid);
		$wa_pattern = '#http://www\.world-art\.ru/animation/animation\.php\?id=\d+#';
		$kg_title_pattern = '#<title>(.)+</title>#';
		preg_match($wa_pattern, $html, $regwa);
		preg_match($kg_title_pattern, $html, $regkt);
		$kage_title = $regkt[1];
		$wa_link = $regwa[0];
	}
}