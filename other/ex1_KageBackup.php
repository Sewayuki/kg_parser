<?php
//
//Проект на основе библиотеки KageParser
//Сохраняем кагу на чёрный день 
//Сохраняется в базу данных, так что не забываем прописать параметры коннекта 
//
require_once 'KageParser.php';

$backup = new KageBackup();
$backup->backup();

class KageBackup
{
    private $kage_parser, $pdo, $lastTopicId, $lastUserId, $lastPostId;

    public function __construct()
    {
        //создаём парсер
        $this->kage_parser = new KageParser();

        //параметры коннекта к базе. Юзер, пароль, данные о базе и массив опций
        $user_db = "postgres";
        $pass_db = "pp0099oo";
        $dsn = "pgsql:host=127.0.0.1;port=5432;dbname=yii2basic"; //pgsql:host=127.0.0.1;port=5000;dbname=somebase
        $opt = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ); //массив опций. Режим вывода ошибок в виде исключений. FETCH_MODE по умолчанию

        //работалка с базой
        $this->pdo = new PDO($dsn, $user_db, $pass_db, $opt);

        //получаем последние айдишники, чтобы по 100500 раз их не запрашивать
        $this->lastTopicId = $this->lastTopicId();
        $this->lastUserId = $this->lastUserId();
        $this->lastPostId = $this->lastPostId();
    }

    public function backup()
    {
        //массив форумов, которые будем сканить
        $forums = array('5', '3', '13', '9', '2', '14', '15');

        //начинаем перебирать форумы
        foreach ($forums as $forum_id) {
            //получаем список темок с первой страницы форума
            $topics = $this->kage_parser->forum($forum_id);

            //начинаем перебирать темки
            foreach ($topics as $topic) {
                //получаем массив сообщений с последних пяти страниц
                $posts = $this->kage_parser->topic($topic['topicId'], 5);

                //добавляем темку в базу
                $this->addTopicToBase($topic['topicId'], $topic['topicTitle'], $forum_id);
                echo "Topic № " . $topic['topicId'] . "\n";

                //работаем с каждым сообщением по отдельности
                foreach ($posts as $post) {

                    $pid = $post['postId'];
                    $text = $post['postBody'];
                    $uid = $post['userId'];
                    $uname = $post['userName'];
                    $postDate = $post['postDate'];
                    $topic_id = $topic['topicId'];

                    //записываем в базу сообщения
                    $this->addPostToBase($pid, $text, $uid, $uname, $postDate, $topic_id);

                    //обрабатываем юзера
                    $this->addUserToBase($uid, $uname);
                }
            }
        }
    }

    private function addPostToBase($pid, $text, $uid, $uname, $postDate, $topic_id)
    {
        //добавлялка сообщения в базу
        //ищем сообщение в базе
        //если есть, то обновляем
        //если нет, то добавляем
        
        //обращаемся к базе и получаем текст для сравнения
        $sth = $this->pdo->prepare("SELECT new_text FROM kage_posts WHERE pid=?");
        $sth->execute(array($pid));
        $post_text = $sth->fetch(PDO::FETCH_ASSOC);
        //echo $pid . ' -> '. $uname . "\n";
        if (strcmp($post_text['new_text'], $text) != 0 && strlen($post_text['new_text']) > 1) {

            //исправление записи в базе, если текст сообщений не совпал
            $vth = $this->pdo->prepare("UPDATE kage_posts SET new_text = :text WHERE pid=:pid");
            $vth->execute(array(
                'text' => $text,
                'pid' => $pid
            ));
            return false;
        }
        if (strlen($post_text['new_text'])<2) {
            ++$this->lastPostId;
            $date_now = time();
            $last_id = $this->lastPostId;
            $vth = $this->pdo->prepare("INSERT INTO kage_posts (id, uid, uname, post_text, date, imported_at, status, pid, new_text, topic_id) VALUES ('$last_id', :uid, :uname, :post_text, :post_date, :date_now, 'imported', :pid, :post_text, :topic_id)");
            $vth->execute(array(
                'uid' => $uid,
                'uname' => $uname,
                'post_text' => $text,
                'post_date' => $postDate,
                'date_now' => $date_now,
                'pid' => $pid,
                'topic_id' => $topic_id
            ));


            return true;
        }
        return false;
    }

    private function addTopicToBase($tid, $topicTitle, $forum){
        //добавлялка темки в базу 
        //проверяем по айди, если есть, сверяем название
        //если нет, добавляем в базу

        //$date_now = time();
        ++$this->lastTopicId;
        $last_id = $this->lastTopicId;

        $sth = $this->pdo->prepare("SELECT tname FROM kage_topics WHERE tid=? ORDER BY id DESC LIMIT 1");
        $sth->execute(array($tid));

        //через цикл не совсем грамотно, но тем не менее удобнее, 
        //т.к. если нет совпадений мы автоматом переходим к добавлению
        //реализацию без цикла см. функцией выше
        foreach ($sth as $row) {
            $tna = $row['tname'];
            if (strcmp($tna, $topicTitle) != 0) {
                #исправление записи в базе
                $vthv = $this->pdo->prepare("UPDATE kage_topics SET tname = :tname WHERE tid=:tid");
                $vthv->execute(array(
                    'tname'=>$topicTitle,
                    'tid'=>$tid
                    ));
            }
            return true;
        }
        $vth = $this->pdo->prepare("INSERT INTO kage_topics (id, tid, tname, status, forum) VALUES ('$last_id', :tid, :tname, 'imported', :forum)");
        $vth->execute(array(
                    'tid'=>$tid,
                    'tname'=>$topicTitle,
                    'forum'=>$forum
                    ));
    }

    private function addUserToBase($uid, $uname){
        //добавлялка юзера в базу
        //проверяем по ид, есть он или нет в базе
        //если есть, обновляем ник
        //если нет, добавляем
        ++$this->lastUserId;
        $last_id = $this->lastUserId;

        if ($uid !== true) {
            return false;
        }
        
        $sth = $this->pdo->prepare("SELECT uname FROM kage_users WHERE uid=?");
        $sth->execute(array($uid));

        //опять же, цикл удобнее
        foreach ($sth as $row) {
            $tna = $row['uname'];
            if (strcmp($tna, $uname) != 0) {
                //исправление записи в базе
                $vthh = $this->pdo->prepare("UPDATE kage_users SET othernames = :uname WHERE uid=:uid");
                $vthh->execute(array(
                    'uid'=>$uid,
                    'uname'=>$uname
                    ));
            }
            return true;
        }
        $vth = $this->pdo->prepare("INSERT INTO kage_users (id, uid, uname, status, othernames) VALUES ('$last_id', :uid, :uname, 'imported', :uname)");
        $vth->execute(array(
                    'uid'=>$uid,
                    'uname'=>$uname
                    ));


    }

    private function lastTopicId()
    {
        //получаем айди последней записи топика
        //если в базе не будет записей, то всё к хренам сломается ^_^
        $data = $this->pdo->query('SELECT id FROM kage_topics ORDER BY id DESC LIMIT 1')->fetchAll(PDO::FETCH_COLUMN);
        return end($data);
    }

    private function lastUserId()
    {
        //получаем айди последней записи юзера
        //
        $data = $this->pdo->query('SELECT id FROM kage_users ORDER BY id DESC LIMIT 1')->fetchAll(PDO::FETCH_COLUMN);
        return end($data);
    }

    private function lastPostId()
    {
        //получаем айди последней записи сообщения
        //
        $data = $this->pdo->query('SELECT id FROM kage_posts ORDER BY id DESC LIMIT 1')->fetchAll(PDO::FETCH_COLUMN);
        return end($data);
    }
}
