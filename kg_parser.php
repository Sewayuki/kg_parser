<?php

/*forum()
*/
class KageParser
{
	protected $kg_url;

	function __construct(){
		$this->kg_url = "http://www.fansubs.ru/";
	}

	public function getAnimeInfo($aid){
		$html = file_get_contents($this->kg_url . 'base.php?id=' . $aid);
		$html = iconv('windows-1251', 'UTF-8', $html);
		$wa_pattern = '#http://www\.world-art\.ru/animation/animation\.php\?id=\d+#';
		$kg_title_pattern = '#<title>(.)+</title>#';
		preg_match($wa_pattern, $html, $regwa);
		preg_match($kg_title_pattern, $html, $regkt);
		$kage_title = $regkt[1];
		$wa_link = $regwa[0];
		#>_>
	}

	public function forum($forum, $pages = 1){
		$forum_url = $this->kg_url . 'forum/viewforum.php?f=' . $forum . '&topicdays=0&start=';
		#$forum_pattern = '#class=.topictitle.><a href=.viewtopic\.php\?t=(?P<topicId>\d+).*?class=.topictitle.>(?P<topicTitle>.*?)<\/a><\/span>.*?viewprofile&amp;u=(?P<authorId>\d+).>(?P<authorName>[a-zA-ZА-Яа-я\d\s]+?)<\/a><\/span>.*?nowrap=.nowrap.><span class=.postdetails.>(?P<lastMsg>.*?)<br \/>#s';
		$forum_pattern = '#class=.topictitle.><a href=.viewtopic\.php\?t=(?P<topicId>\d+).*?class=.topictitle.>(?P<topicTitle>.*?)<\/a><\/span>.*?viewprofile&amp;u=(?P<authorId>\d+)[&;>=a-z\d]*(.>)(?P<authorName>.+?)<\/a><\/span>.*?nowrap=.nowrap.><span class=.postdetails.>(?P<lastMsg>.*?)<br \/>#s';
		$start = 0;
		for ($i=0; $i < $pages; $i++) { 
			$html = file_get_contents($forum_url . $start);
			$html = iconv('windows-1251', 'UTF-8', $html);
			preg_match_all($forum_pattern, $html, $regf);
			$x = 0;
			foreach ($regf['topicId'] as $key) {
				$resp[] = array(
					'topicId' => $regf['topicId'][$x],
					'topicTitle' => $regf['topicTitle'][$x],
					'authorId' => $regf['authorId'][$x],
					'authorName' => $regf['authorName'][$x],
					'lastMsg' => $regf['lastMsg'][$x]
					);
				++$x;
			}
			$start += 50;
		}
		return $resp;
	}
}