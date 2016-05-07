<?php

//ParsePosts("http://www.fansubs.ru/forum/viewtopic.php?t=3633", "3633");
$forums = array('5','3','13','7','9','2','14','15');
$topic_pattern = '#viewtopic.php\?t=\d+#';

foreach ($forums as $forum) {
	$start = 0;
	echo "Scan forum $forum \n";
	while(TRUE){
		$handle = file_get_contents('http://www.fansubs.ru/forum/viewforum.php?f='.$forum."&topicdays=0&start=$start");
		preg_match_all($topic_pattern, $handle, $regt);
		$topics = array_unique($regt[0]);
		if(count($topics)<4){
			break;
		}else{
			foreach ($topics as $top) {

			$msgstart = 0;
			preg_match('#\d+#', $top, $regmsgtop);
			$tid = $regmsgtop[0];
			Topic($tid, $forum);
			while(TRUE){
				$html = file_get_contents('http://www.fansubs.ru/forum/'.$top.'&postdays=0&postorder=asc&start='.$msgstart);
				$html = iconv('windows-1251', 'UTF-8', $html);
				if (strpos($html, '<span class="gen">В этой теме нет сообщений</span>') !== false) {
					break;
        }
				ParsePosts('http://www.fansubs.ru/forum/'.$top.'&postdays=0&postorder=asc&start='.$msgstart,$tid);
        echo $top . "--msg-offset->" . $msgstart."\n";
        $msgstart += 15;
			}
			}
		}
		$start += 50;
	}
}

function User($uid){
	if (strcmp($uid, '0') == 0) {
					return FALSE;
        }
        echo "Checking user $uid \n";
	$html = file_get_contents('http://www.fansubs.ru/forum/profile.php?mode=viewprofile&u='.$uid);
	$html = iconv('windows-1251', 'UTF-8', $html);
	$uname_pattern = '#Профиль пользователя.+</th>#';
	preg_match($uname_pattern, $html, $regus);
	$uname = $regus[0];
	$uname = preg_replace("#</th>#", "", $uname);
	$uname = preg_replace("#Профиль пользователя #", "", $uname);
	preg_match('#/avatars/[a-zA-Z0-9.]+"#', $html, $regav);
	$avatar = $regav[0];
	$avatar = preg_replace("#/avatars/#", "", $avatar);
	$avatar = preg_replace('#"#', "", $avatar);
	$pdop = new PDO('');
	$data = $pdop->query('SELECT id FROM kage_users ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $last_id = intval(end($data));
    $last_id += 1;
    $sth = $pdop->prepare("SELECT uname FROM kage_users WHERE uid=?");
    $sth->execute(array($uid));
    foreach ($sth as $row) {
        $tna = $row['uname'];
        if (strcmp($tna, $uname) != 0) {
            #исправление записи в базе
            $pdop->exec("UPDATE kage_users SET othernames = '$uname' WHERE uid='$uid'");
        }
        return TRUE;
        echo "1";
    }
    $pdop->exec("INSERT INTO kage_users (id, uid, uname, status, avatar, othernames) VALUES ('$last_id', '$uid', '$uname', 'imported', '$avatar', '$uname')");

}

function Topic($tid, $forum){
	echo "Add topic $tid \n";
	$tname_pattern = '#highlight=.+</a>#';
	$snd_pattern = '#>.+</a>#';
	$html = file_get_contents('http://www.fansubs.ru/forum/viewtopic.php?t='.$tid);
	$html = iconv('windows-1251', 'UTF-8', $html);
	preg_match($tname_pattern, $html, $regtn);
	preg_match($snd_pattern, $regtn[0], $regten);
	$tname = substr($regten[0], 1);
	$tname = preg_replace("#</a>#", "", $tname);
	$pdo = new PDO('');
	$data = $pdo->query('SELECT id FROM kage_topics ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $last_id = end($data);
    $last_id += 1;
    $sth = $pdo->prepare("SELECT tname FROM kage_topics WHERE tid=?");
    $sth->execute(array($tid));
    foreach ($sth as $row) {
        $tna = $row['tname'];
        if (strcmp($tna, $tname) != 0) {
            #исправление записи в базе
            $pdo->exec("UPDATE kage_topics SET tname = '$tname' WHERE tid='$tid'");
        }
        return TRUE;
    }
    $pdo->exec("INSERT INTO kage_topics (id, tid, tname, status, forum) VALUES ('$last_id', '$tid', '$tname', 'imported', '$forum')");
}

function CheckPost($pid, $text)
{
    /*Проверяем, есть ли пост в базе. Если нет, возвращаем TRUE (хах, логика)
    /*Если есть, то проверяем последнюю версию текста поста, если
    /*свпадает с текущей, то просто возвращаем FALSE.
    /*если не совпадает, то исправляем последнюю версию
    */
    $pdo = new PDO('');
    $sth = $pdo->prepare("SELECT new_text FROM kage_posts WHERE pid=?");
    $sth->execute(array($pid));
    foreach ($sth as $row) {
        $post_text = $row['new_text'];
        if (strcmp($post_text, $text) != 0) {
            #исправление записи в базе
            $upd_text = $row['post_text'] . "" . $text;
            $pdo->exec("UPDATE kage_posts SET new_text = '$text' WHERE pid='$pid'");
        }
        return FALSE;
    }
    return TRUE;
}

function ParsePosts($link, $topic_id)
{
    $last_id = LastIdPosts();
    $handle = file_get_contents($link);
    $handle = iconv('windows-1251', 'UTF-8', $handle);
    $posts = explode("<a name=\"", $handle);

    $post_pattern = '#<td colspan="2"><span class="postbody">[\S\s]+</span><span class="postbody">#';
    $date_pattern = '#Добавлено:[\s\S]{15,30}\s(am|pm)#';

    $i = 0;
    foreach ($posts as $post) {
        if ($i < 2) {
            ++$i;
            continue;
        }
        $post = "<a name=\"" . $post;
        preg_match("/<a name=\"[0-9]+/", $post, $regs);
        $pid = $regs[0];
        $pid = preg_replace("/<a name=\"/", "", $pid);
        preg_match($post_pattern, $post, $regsp);
        $post_text = preg_replace("/<td colspan=\"2\">/", "", $regsp[0]);
        DesignRepair($post_text);
        preg_match($date_pattern, $post, $regsd);
        $post_date = $regsd[0];
        if (strpos($post, 'profile.php?mode=viewprofile') !== false) {
            preg_match("#</a><b>.+</b></span>#", $post, $regu);
            $uname = preg_replace("#</a><b>#", "", $regu[0]);
            $uname = preg_replace("#</b></span>#", "", $uname);
            preg_match("/&amp;u=[0-9]+/", $post, $regu);
            $uid = preg_replace("/&amp;u=/", "", $regu[0]);
        } else {
            $uid = "0";
            preg_match("#</a><b>.+</b></span>#", $post, $regu);
            $uname = preg_replace("#</a><b>#", "", $regu[0]);
            $uname = preg_replace("#</b></span>#", "", $uname);
        }
        $date_now = time();
        $status = "imported";
        echo "User $uid \n";
        User($uid);
        if (CheckPost($pid, $post_text)) {
            ++$last_id;
            $pdo = new PDO('');
            $pdo->exec("INSERT INTO kage_posts (id, uid, uname, post_text, date, imported_at, status, pid, new_text, topic_id) VALUES ('$last_id', '$uid', '$uname', '$post_text', '$post_date', '$date_now', '$status', '$pid', '$post_text', '$topic_id')");
        }

    }
}

function LastIdPosts()
{
	$pdo = new PDO('');
	$data = $pdo->query('SELECT id FROM kage_posts ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    return end($data);
}

function DesignRepair(&$text){
	#code
	$tags = array('div','span','table','td','tr');
	foreach ($tags as $tag) {
		$count_open = substr_count($text, '<'.$tag);
		$count_close = substr_count($text, '</'.$tag);
		while($count_open > $count_close){
			++$count_close;
			$text .= "</$tag>";
		}
		while($count_open < $count_close){
			++$count_open;
			$text = "<$tag>" . $text;
		}
	}
}

function WriteToTxt($file, $text)
{
    // Открываем файл для получения существующего содержимого
    $current = file_get_contents($file);
    // Добавляем нового человека в файл
    $current .= $text;
    // Пишем содержимое обратно в файл
    file_put_contents($file, $current);
}