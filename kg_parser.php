<?php

/*forum()
*/
class KageParser
{
	protected $kg_url;

	function __construct(){
		$this->kg_url = "http://www.fansubs.ru/";
	}

	protected function getAnimeInfo($aid){
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

	public function base($aid){
		$archive_url = $this->kg_url . 'base.php?id=' . $aid;
		$archive_pattern = '#<td class="row3" width="290"><b>(?P<series>.+?)<\/b><\/td>.+?base\.php\?cntr=(?P<translateId>\d+?)"><f.+?>(?P<format>.+?)<\/fo.+?row3">(?<date>.+?)<\/td>.+?row1">(?P<staff>.+?)<table width="100%">#s';
		$staff_pattern = '#<td align="center" valign="middle">(?P<role>.*?)(:\s)*<a href="base\.php\?au=(?P<subberId>\d+)"><b>(?P<nickname>.+?)<\/b><\/a>.+?img src=gif\/(?P<avatar>.+?)\swidth#s';

		$html = file_get_contents($archive_url);
		$html = iconv('windows-1251', 'UTF-8', $html);
		preg_match_all($archive_pattern, $html, $regar);
		$x = 0;
		foreach ($regar['translateId'] as $key) {
			preg_match_all($staff_pattern, $regar['staff'][$x], $regsub);
			$y = 0;
			$staffInfo = array();
			foreach ($regsub['subberId'] as $skey) {
				$staffInfo[] = array(
					'subberId' => $regsub['subberId'][$y],
					'nickname' => $regsub['nickname'][$y],
					'role' => $regsub['role'][$y],
					'avatar' => $regsub['avatar'][$y]
					);
				++$y;
			}
			$translations[] = array(
				'translateId' => $regar['translateId'][$x],
				'series' => $regar['series'][$x],
				'format' => $regar['format'][$x],
				'date' => $regar['date'][$x],
				'staff' => $staffInfo,
				);
			++$x;
		}
		return $translations;
	}

	public function isTopicDead($tid){
		$topic_url = $this->kg_url . 'forum/viewtopic.php?t=' . $tid;
		$html = file_get_contents($topic_url);
		$html = iconv('windows-1251', 'UTF-8', $html);
		$not_pattern = '<td align="center"><span class="gen">Темы, которую вы запросили, не существует.</span></td>';
		if (strpos($html, $not_pattern) !== false) {
                return true;
            }
	}

	public function isArchiveDead($aid, $mode = 1){
		if($mode == 1){
		$archive_url =  $this->kg_url . 'base.php?id=' . $aid;
		$not_pattern = '<td align="center"><b>Нет данных на anime';
	}elseif($mode == 2){
		$archive_url =  $this->kg_url . 'base.php?au=' . $aid;
		$not_pattern = '<td align="center"><b>Нет данных на author';
	}
		$html = file_get_contents($archive_url);
		$html = iconv('windows-1251', 'UTF-8', $html);
		if (strpos($html, $not_pattern) !== false) {
                return true;
            }
	}
}