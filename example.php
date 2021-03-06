<?php
require_once 'KageParser.php';

///~~~~ПРИМЕР 1~~~~//

$forum_parser = new KageParser();

//указываем номер форума, с которого хотим получить темы
$forum = '5';

//получаем массив тем
$topics = $forum_parser->forum($forum);

//использование
foreach ($topics as $topic) {
	// работаем с каждой темкой по отдельности
	// вывести название темки
	echo $topic['topicTitle'] . "\n";

}


//~~~~ПРИМЕР 2~~~~//*/

$archive_parser = new KageParser();

//айди тайтла
$id = '5191';
$translations = $archive_parser->base($id);

foreach ($translations as $translate) {

	//Пример вывода 10566 -> ТВ 1-10
	//все ключи вывода см. в readme
    echo $translate['translateId'] . ' -> ' . $t1 . "\n";
	//работаем с массивом переводчиков
	foreach ($translate['staff'] as $subber) {
		echo $subber['role'] . ' -> ' . $subber['nickname'] . "\n";
		//Пример вывода Переводчик -> Sewayuki
	}
	echo "\n\n";
}

