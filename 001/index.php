<?php
/**
 * ПРЕДИСЛОВИЕ
 * Это метод из контроллера в одном из проектов.
 * Писался достаточно долго, притерпивал уйму изменений
 * и изначально вовсе был отдельным сервисом.
 * ---
 * По возможности заранее прокомментировал то, что
 * смог вспомнить или понять сам(давно к нему не возвращался)
 *
 * Само собой, код вырван из контекста :)
 */



/**
 * @param $args
 * @return bool
 */
public function getLinks($args)
{
    // Подтягиваю необходимые для работы метода модели(все они по факту наборы методов для запросов в базу)
    $anchorModel = new Anchor();
    $requestModel = new Request();
    $linkModel = new Link();
    $taskModel = new Task();
    $commentModel = new Comment();
    $siteModel = new Site();

    // Получаю список сайтов
    $sites = $siteModel->init();

    // Проверяю наличие сводобной ссылки для задания в базе
    if ($linkModel->search(['task_id' => $args['task_id'], 'type' => $args['type']])) {

        // Раз ссылка в базе уже есть, получаю ее и возвращаю ее ID для закрепления за заданием
        $link = $linkModel->search(['task_id' => $args['task_id'], 'type' => $args['type']]);

        return $link[0]['id'];

    }
    // Если свободной ссылки нет, пробую найти уже привязанную ссылку
    else if ($linkModel->search(['task_id' => $args['task_id'], 'type' => $args['type'], 'site_id' => $args['site_id']])) {

        $link = $linkModel->search(['task_id' => $args['task_id'], 'type' => $args['type'], 'site_id' => $args['site_id']]);
        // Если такая есть, повторно принудительно назначаю ей ID задания
        $linkModel->edit(['task_id' => $args['task_id']], $link[0]['id']);

        return $link[0]['id'];

    }

    $date = date('Y-m-d');

    // Массив для сбора всех анкоров
    $libAnchor = [];

    // Получаю список всех анкоров
    $anchorLib = $anchorModel->search([
        'type' => $args['type'],
    ]);

    // Получаю захардкоженные словари
    $libMarkup = $anchorModel->getAssociateKey();
    $libWord = $anchorModel->getAssociateWord();
    $libRef = $anchorModel->getWPtoDEdict();
    // Один из словарей разворачиваю аналогично array_flip()
    $libMarkupFlip = $anchorModel->getAssociateKey('flip');

    // Если анкоров нужного type не оказалось(исчерпаны)
    if (count($anchorLib) < 1) {
        // Всем анкорам в таблице с указанным type присваю used = 0(не использованы)
        $anchorModel->setUsedZero($args['type']);

        // По идее, здесь надо было получить список анкоров по новой, но я упустил это из виду, на момент написания
        foreach ($anchorLib as $key => $value) {
            if ($value['type'] == 'main') {
                $libAnchor['main'][] = $value['anchor'];
            } else {
                $libAnchor['tax'][$value['category']][] = $value['anchor'];
            }
        }
    }
    // Если анкоры нашлись, перебираю их в нужный вид
    else {
        foreach ($anchorLib as $key => $value) {
            if ($value['type'] == 'main') {
                $libAnchor['main'][] = $value['anchor'];
            } else {
                $libAnchor['tax'][$value['category']][] = $value['anchor'];
            }
        }
    }

    $getTaxDon = [];
    $getTaxSlug = '';
    $getTaxAllAcc = [];
    $getTaxAcc = [];
    $acceptorCategory = '';

    if (isset($args['term_id']) && $args['term_id'] > 0) {

        // Делаю запросы на внешние ресурсы для получения списков разделов
        $getTaxDonor = json_decode($requestModel->getContent("{$sites[$args['site_id']]}/?key=REQUEST_KEY&getTaxAll"), true);
        $getTaxAcceptor = json_decode($requestModel->getContent("{$args['acceptor']}/?key=REQUEST_KEY&getTaxAll"), true);

        foreach ($getTaxDonor as $id => $tax) {
            if ($tax['id'] == $args['term_id']) {
                if (isset($libMarkup[$tax['link']])) {
                    $getTaxDon = $libMarkup[$tax['link']];
                    $getTaxSlug = $tax['slug'];
                }
            }
        }

        $getTaxAcc = [];
        foreach ($getTaxAcceptor as $tax) {
            $getTaxAllAcc[] = $tax['slug'];
        }

        // Получаем нужную ссылку блять | не, не получаем, все ебала какая-то
        if (in_array($libRef[$getTaxDon], $getTaxAllAcc)) {
            $getTaxAcc = $getTaxAllAcc[$libMarkup[$getTaxDon]];
        } else if (isset($libRef[$getTaxDon])) {
            $getTaxAcc['slug'] = $libMarkupFlip[$getTaxDon];
        } else {
            $getTaxAcc['slug'] = $libMarkup[$getTaxDon];
        }

        // Выгребаем нужный вариант из словаря по ключу донора
        foreach ($libRef[$getTaxDon] as $item) {
            if (in_array($item, $getTaxAllAcc)) {
                $acceptorCategory = $item;
            }
        }

        if(empty($acceptorCategory)) {
            $acceptorCategory = trim($getTaxSlug, '/');
        }
    }

    // Получаю min и max для того что бы потом рандомно выбрать одно значение с анкором
    $anchorMin = 0;
    $anchorMax = 1;
    if ($args['type'] == 'main') {
        $countKey = array_keys($libAnchor['main']);
        // Минимал очка
        $anchorMin = min($countKey);
        // Максимал очка
        $anchorMax = max($countKey);
    } else {
        if (!empty($getTaxAcc)) {
            $countKey = array_keys($libAnchor['tax'][$libMarkup[$getTaxAcc['slug']]]);
            // Минимал очка
            $anchorMin = min($countKey);
            // Максимал очка
            $anchorMax = max($countKey);
        }
    }

    // Получаем рандомное число между min\max
    $anchorCats = mt_rand($anchorMin, $anchorMax);

    // Выбираем на основе рандома, анкор
    if ($args['type'] == 'main') {
        $anchorName = $libAnchor[$args['type']][$anchorCats];
    } else {
        $anchorName = $libAnchor[$args['type']][$libMarkup[$getTaxAcc['slug']]][$anchorCats];
    }

    // Устанавливаю имя города
    $url = "{$args['acceptor']}/?key=REQUEST_KEY&siteAbout";
    $city = json_decode($requestModel->getContent($url), true);

    // Подменяем код на имя города в анкоре
    if(!empty($city)) {
        $anchorName = str_replace('#CITY#', $city['name'], $anchorName);
    }

    // Получаем последний SHORT номер
    $short = $linkModel->getLastCol('short', $args['type']);
    if (!isset($short['short'])) {
        $short = 1;
    } else {
        $short = $short['short'] + 1;
    }

    if ($args['type'] == 'main') {
        $link = $args['acceptor'];
    } else {
        $link = "{$args['acceptor']}/{$acceptorCategory}/";
    }

    // Если это комментарий, избавимся от анкора, согласно документу
    if($args['task_type'] == 'comment') {
        $anchorName = '';
    }

    // АУФ!
    $auff = [
        'short' => $short,
        'site_id' => $args['site_id'],
        'acceptor' => $link,
        'name' => $anchorName,
        'type' => $args['type'],
        'blank' => 1,
        'date' => $date,
    ];

    if ($args['type'] == 'tax') {
        $auff['parent_name'] = $libWord[$libMarkup[$getTaxAcc['slug']]];
    }

    $query = $linkModel->add($auff);

    if ($query) {
        // LOCK BY parent_id
        $linkModel->edit(['task_id' => $args['task_id']], $query);

        // Обрабатываю текст задания и подменяю маску на ссылку(задания бывают двух видов, текст и комментарии)
        $task = $taskModel->getById($args['task_id']);
        if ($task['task_type'] == 'comment') {
            $comments = $commentModel->getCommentsByTaskId($args['task_id']);
            $count = 0;
            foreach ($comments as $comment) {
                $shortType = strtoupper($args['type']);
                $comments[$count]['comment'] = preg_replace('/\#\w+\_\d+\#/iu', "#{$shortType}_{$short}#", $comment['comment']);
                $commentModel->edit($comments[$count], $comments[$count]['id']);
                $count++;
            }
        } else {
            $shortType = strtoupper($args['type']);
            $task['text'] = preg_replace('/\#\w+\_\d+\#/iu', "#{$shortType}_{$short}#", $task['text']);
            $taskModel->edit($task, $task['id']);
        }

        return $query;
    } else {
        return false;
    }
}