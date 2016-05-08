<?php
require_once 'kg_parser.php';

$kage_parser = new KageParser();

$forum = '5'; #указываем номер форума, с которого хотим получить темы
$topics = $kage_parser->forum($forum); #получаем массив тем

#использование
foreach ($topics as $topic) {
	# работаем с каждой темкой по отдельности
	echo $topic['topicTitle'] . "\n"; #вывести название темки

	#другие примеры 
	#echo $topic['topicId'] . "\n"; #вывести айди темки
	#echo $topic['authorId'] . "\n"; #вывести айди автора 
	#echo $topic['authorName'] . "\n"; #вывести ник автора
	#echo $topic['lastMsg'] . "\n"; #вывести дату последнего сообщения
}