<?php
/**
 * Created by PhpStorm.
 * User: errogaht
 * Date: 08.06.15
 * Time: 15:05
 */
ini_set("display_errors", 1);
require_once "composer/vendor/autoload.php";
$GLOBALS['config'] = json_decode(file_get_contents('config.json'));


if (!empty($GLOBALS['config']->items)) {
    foreach ($GLOBALS['config']->items as $item) {
        if (empty($item->id) || empty($item->url) || empty($item->period)) {
            return false;
        }

        /** @var bool если тут true то нужно получать скриншот */
        $isNeedToGetPicture = false;

        /* получаем время последнего получения картинки */
        if ($updated = getTaskUpdated($item->id)) {
            if ($updated + $item->period < time() ) {
                /* похоже что пора опять получать картинку */
                $isNeedToGetPicture = true;
            }

        } else {
            $isNeedToGetPicture = true; /* нужно получить так как в БД вообще нет записи о времени */
        }

        if ($isNeedToGetPicture) {
            /* нужно получить скриншот */
            checkDir();
            createScreenshot($item);
            setUpdatedRecord($item);
            k('screenshot created for task ' . $item->id);
        } else {
            k('no screenshots created');
        }
    }

}

/**
 * Сохраняет в базе данные о времени последнего
 * получения скриншота
 * @param $item
 */
function setUpdatedRecord($item)
{
    if(!empty($GLOBALS['config']->databaseFile)) {
        $data = getDBdata();
        $data[$item->id] = time();
        file_put_contents($GLOBALS['config']->databaseFile, serialize($data));
    }
}

/**
 * Создаёт скриншот
 * @param $item
 */
function createScreenshot($item)
{
    $dir = getDirForScreenshot($item);
    $imageName = getImageName($item);
    $img = $dir . '/' . $imageName;
    file_put_contents($img, file_get_contents(getApiUrl($item)));
}

/**
 * Возвращает URL
 * который нам даст нужную нам картинку
 *
 * @param $item
 * @return string
 */
function getApiUrl($item) {
    return $GLOBALS['config']->serviceUrl .
    $item->res . '/' .
    $item->size . '/' .
    $item->format . '?' .
    $item->url;
}

/**
 * Возвращает имя файла для скриншота
 * @param $item
 * @return string
 */
function getImageName($item)
{
    $date = date('d.m.Y-H:i:s', time());
    $siteName = preg_replace('/[^a-zA-Z0-9-]/', '', $item->url);
    return $siteName . '-' . $item->id . '-' . $item->res . '-' .  $date . '.' . $item->format;
}


/**
 * Возвращает путь до папки куда сохранять скриншот
 * Если папки нет то создаёт
 * @param $item
 * @return string
 */
function getDirForScreenshot($item)
{
    $siteName = preg_replace('/[^a-zA-Z0-9-]/', '', $item->url);
    $dirName = $siteName . '-' . $item->id;
    $fullDirname = $GLOBALS['config']->shotsDir . '/' . $dirName;
    if (!is_dir($fullDirname)) {
        mkdir($fullDirname);
    }
    return $fullDirname;
}

/**
 * Проверяет есть ли папка для скриншотов
 * если нету то создаёт её
 */
function checkDir() {
    if(isset($GLOBALS['config']->shotsDir)) {
        if (!is_dir($GLOBALS['config']->shotsDir)) {
            mkdir($GLOBALS['config']->shotsDir);
        }
    }
}

/**
 * Возвращает время последнего обновления задания
 * @param $taskId
 * @return bool
 */
function getTaskUpdated($taskId)
{
    if ($data = getDBdata()) {
        if (isset($data[$taskId])) {
            return $data[$taskId];
        }
    }
    return false;
}

/**
 * Возвращает данные нашей мини - базы данных
 * @return mixed
 */
function getDBdata()
{
    if(!empty($GLOBALS['config']->databaseFile) && file_exists($GLOBALS['config']->databaseFile)) {
        $content = file_get_contents($GLOBALS['config']->databaseFile);
        if (!empty($content)) {
            return unserialize($content);
        }

    }
    return false;
}