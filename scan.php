<?php

/*TODO
/*сквозные переменные
/*обобщение
/*NB!! Закомменчено добавление и обновление пользователя!
/**/

$kage_parser = new KageParser();

#парсить только сообщения с первых страниц форума (быстрый парсинг)
$kage_parser->parseToBase(true);

#полное сканирование
#$kage_parser->parseToBase(false);

class KageParser
{
    protected $pdo, $last_user_id, $last_post_id;

    public function __construct()
    {
		#параметры коннекта к базе. Юзер, пароль, данные о базе и массив опций
        $user_db = "";
        $pass_db = "";
        $dsn = ""; #pgsql:host=127.0.0.1;port=5000;dbname=somebase
        $opt = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ); #массив опций. Режим вывода ошибок в виде исключений. FETCH_MODE по умолчанию

        $this->pdo = new PDO($dsn, $user_db, $pass_db, $opt);
        $this->last_user_id = ""; #доделать...
        $this->last_post_id = $this->lastIdPosts();
    }

    public function parseToBase($fast)
    {
        $forums = array('5', '3', '13', '7', '9', '2', '14', '15');
        $topic_pattern = '#viewtopic.php\?t=(\d+)#';

        foreach ($forums as $forum) {
            $start = 0;
            echo "Scan forum $forum \n";
            while (true) {
                $handle = file_get_contents("http://www.fansubs.ru/forum/viewforum.php?f=$forum&topicdays=0&start=$start");
                preg_match_all($topic_pattern, $handle, $regt);
                $topics = array_unique($regt[1]);
                if (count($topics) < 4) {
                    break;
                } else {
                    foreach ($topics as $tid) {
                        $msgstart = 0;
                        $this->topic($tid, $forum);
                        $top = 'viewtopic.php?t='.$tid;
                        while (true) {
                            $html = file_get_contents('http://www.fansubs.ru/forum/' . $top . '&postdays=0&postorder=asc&start=' . $msgstart);
                            $html = iconv('windows-1251', 'UTF-8', $html);
                            if (strpos($html, '<span class="gen">В этой теме нет сообщений</span>') !== false) {
                                break;
                            }
                            $this->parsePosts('http://www.fansubs.ru/forum/' . $top . '&postdays=0&postorder=asc&start=' . $msgstart, $tid);
                            echo $top . "--msg-offset->" . $msgstart . "\n";
                            $msgstart += 15;
                        }
                    }
                }
                if($fast){
                	break;
                }else{
                	$start += 50;
                }
            }
        }
    }

    private function user($uid)
    {
        if (strcmp($uid, '0') == 0) {
            return false;
        }
        echo "Checking user $uid \n";
        $html = file_get_contents('http://www.fansubs.ru/forum/profile.php?mode=viewprofile&u=' . $uid);
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
        $data = $this->pdo->query('SELECT id FROM kage_users ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $last_id = intval(end($data));
        $last_id += 1;
        $sth = $this->pdo->prepare("SELECT uname FROM kage_users WHERE uid=?");
        $sth->execute(array($uid));
        foreach ($sth as $row) {
            $tna = $row['uname'];
            if (strcmp($tna, $uname) != 0) {
                #исправление записи в базе
                $vthh = $this->pdo->prepare("UPDATE kage_users SET othernames = :uname WHERE uid=:uid");
                $vthh->execute(array(
                	'uid'=>$uid,
                	'uname'=>$uname
                	));
            }
            return true;
            echo "1";
        }
        $vth = $this->pdo->prepare("INSERT INTO kage_users (id, uid, uname, status, avatar, othernames) VALUES ('$last_id', :uid, :uname, 'imported', :avatar, :uname)");
        $vth->execute(array(
                	'uid'=>$uid,
                	'uname'=>$uname,
                	'avatar'=>$avatar,
                	'uname'=>$uname
                	));


    }

    private function topic($tid, $forum)
    {
        echo "Topic $tid \n";
        $tname_pattern = '#highlight=(.+)</a>#';
        $html = file_get_contents('http://www.fansubs.ru/forum/viewtopic.php?t=' . $tid);
        $html = iconv('windows-1251', 'UTF-8', $html);
        preg_match($tname_pattern, $html, $regtn);
        $tname = $regtn[1];
        $data = $this->pdo->query('SELECT id FROM kage_topics ORDER BY id DESC LIMIT 1')->fetchAll(PDO::FETCH_COLUMN);
        $last_id = end($data);
        $last_id += 1;
        $sth = $this->pdo->prepare("SELECT tname FROM kage_topics WHERE tid=? ORDER BY id DESC LIMIT 1");
        $sth->execute(array($tid));
        foreach ($sth as $row) {
            $tna = $row['tname'];
            if (strcmp($tna, $tname) != 0) {
                #исправление записи в базе
                $vthv = $this->pdo->prepare("UPDATE kage_topics SET tname = :tname WHERE tid=:tid");
                $vthv->execute(array(
                	'tname'=>$tname,
                	'tid'=>$tid
                	));
            }
            return true;
        }
        $vth = $this->pdo->prepare("INSERT INTO kage_topics (id, tid, tname, status, forum) VALUES ('$last_id', :tid, :tname, 'imported', :forum)");
        $vth->execute(array(
                	'tid'=>$tid,
                	'tname'=>$tname,
                	'forum'=>$forum
                	));
    }

    private function checkPost($pid, $text)
    {
        /*Проверяем, есть ли пост в базе. Если нет, возвращаем true (хах, логика)
        /*Если есть, то проверяем последнюю версию текста поста, если
        /*свпадает с текущей, то просто возвращаем false.
        /*если не совпадает, то исправляем последнюю версию
        */

        $sth = $this->pdo->prepare("SELECT new_text FROM kage_posts WHERE pid=?");
        $sth->execute(array($pid));
        foreach ($sth as $row) {
            $post_text = $row['new_text'];
            if (strcmp($post_text, $text) != 0) {
                #исправление записи в базе
                $upd_text = $row['post_text'] . "" . $text;
                $vth = $this->pdo->prepare("UPDATE kage_posts SET new_text = :text WHERE pid=:pid");
                $vth->execute(array(
                	'text'=>$text,
                	'pid'=>$pid
                	));
            }
            return false;
        }
        return true;
    }

    private function parsePosts($link, $topic_id)
    {
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
            $this->designRepair($post_text);
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
            #$this->user($uid);
            if ($this->checkPost($pid, $post_text)) {
                ++$this->last_post_id;
                echo $this->last_post_id;
                $last_id = $this->last_post_id;
                #$vth = $this->pdo->prepare("INSERT INTO kage_posts (id, uid, uname, post_text, date, imported_at, status, pid, new_text, topic_id) VALUES ('$last_id', '$uid', '$uname', '$post_text', '$post_date', '$date_now', '$status', '$pid', '$post_text', '$topic_id')");
                $vth = $this->pdo->prepare("INSERT INTO kage_posts (id, uid, uname, post_text, date, imported_at, status, pid, new_text, topic_id) VALUES ('$last_id', :uid, :uname, :post_text, :post_date, :date_now, :status, :pid, :post_text, :topic_id)");
                $vth->execute(array(
                	'uid'=>$uid,
                	'uname'=>$uname,
                	'post_text'=>$post_text,
                	'post_date'=>$post_date,
                	'date_now'=>$date_now,
                	'status'=>$status,
                	'pid'=>$pid,
                	'post_text'=>$post_text,
                	'topic_id'=>$topic_id
                	));
            }

        }
    }

    private function lastIdPosts()
    {

        $data = $this->pdo->query('SELECT id FROM kage_posts ORDER BY id DESC LIMIT 1')->fetchAll(PDO::FETCH_COLUMN);
        return end($data);
    }

    private function designRepair(&$text)
    {
        $tags = array('div', 'span', 'table', 'td', 'tr');
        foreach ($tags as $tag) {
            $count_open = substr_count($text, '<' . $tag);
            $count_close = substr_count($text, '</' . $tag);
            while ($count_open > $count_close) {
                ++$count_close;
                $text .= "</$tag>";
            }
            while ($count_open < $count_close) {
                ++$count_open;
                $text = "<$tag>" . $text;
            }
        }
    }

    private function writeToTxt($file, $text)
    {
        $current = file_get_contents($file);
        $current .= $text;
        file_put_contents($file, $current);
    }
}