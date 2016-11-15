SDK: [JavaScript](https://github.com/verstka/verstka.io/JS-SDK), [PHP](https://github.com/verstka/verstka.io/JS-SDK)

#Verstka PHP SDK

##Начало

###Подготовка базы данных
Статьи созданные в **Verstka**, как и обычные материалы, представляют из себя HTML-код.
Для хранения статей можно использовать текущую таблицу, содержащую материалы.
Для этого в нее необходимо добавить следующие поля:
*  `is_vms:boolean` – флаг, по которому можно определить сделана ли статья с помощью **Verstka**
*  `vms_source:text` – тело статьи

###Подготовка структуры папок
В HTML-коде статьи будут содержаться ссылки на изображения, хранящиеся на вашем сервере.
Для этого создайте директорию, например, `/images/verstka/`.

###Подготовка роутинга
Пропишите URL, при открытии которого будет происходить создание и редактирование материалов.<br>
Если `MATERIAL_ID` всех типов материалов которые планируется редактировать с помощью **Verstka** сквозные, то такой URL может, например, иметь вид `/verstka_edit/[MATERIAL_ID]`.

По данному адресу необходимо разместить код, который подключает и инициализирует SDK.

```
<?php
  include __DIR__.'/vendor/vms/class.VMSsdk.php';
  $verstka = new \devnow\VMSsdk($apikey, $secret, $call_back_url, $call_back_function, $temp_dir_abs, $web_root_abs);
php?>
```
* Значения `$apikey` и `$secret` выдаются клиенту при интеграции.
* `$call_back_url` – URL для сохранения материалов (по этому URL необходимо разместить создание экземпляра класса как описано выше)
* `$call_back_function` – функция для сохранения материала в БД и перемещения изображений из временной директории в папку изображений на вашем сайте
* `$temp_dir_abs` – абсолютный путь на файловой системе сервера для хранения временных файлов при работе с редактором
* `$web_root_abs` – абсолютный путь на файловой системе сервера где расположена корневая папка сайта

Данный код осуществляет:
* скачивание изображений в директорию временных файлов `$temp_dir_abs`
* передачу функции обратного вызова `$call_back_function`, которая будет вызвана при сохранении материала

##Создание и редактирование материалов

После создания экземпляра класса вызовом $verstka = new \devnow\VMSsdk(...) как показано выше нужно выполнить вашу процедру авторизации. Это необходимо сделать после т.к. конструктор класса VMSsdk автоматически проверяет загаловки текущего запроса на признаки калбека от Verstka.io о сохранении статьи. Если так и есть он самостоятельно проверит валидность запроса и скачает изображения и вызовет вашу функцию сохранения.

###Связка с местной авторизацией

При попытке открыть URL для редактирования материала местная авторизация должна:<br>
* проверить наличие необходимых прав у текущего пользователя
* определить `user_id`, который будет передан в `$call_back_function`

###Запрос к БД
После этого необходимо запросить из БД текущий HTML статьи.<br>
Например, запрос может выглядеть так:
```
select vms_content from t_materials where is_vms = true and material_id = [MATERIAL_ID]
```

###Получение ссылки на редактирование
Далее осуществляется запроск серверу **Verstka** для создания сессии.<br>
Запрашиваем URL этой сессии:

```
<?php
  $result = $verstka->edit($outgoing['vms_content'], $params['material_id'], $auth['user_id'], $custom_fields);
  
  if (!empty($result['edit_url'])) {
    header('Location: ' . $result['edit_url']);
    echo '<a href="' . $result['edit_url'] . '" target="_blank">Edit ' . $result['edit_url'] . '</a>';
  } else {
    print_r($result);
  }
  
  die;
php?>
```

Массив `$custom_fields` может содержать дополнительные параметры, которые вы захотите использовать в будущем, например:

`$custom_fileds['edit_link']` – абсолютный URL для редактирования текущей статьи

`$custom_fileds['fonts.css']` – относительный путь на вашем сервере где хранится css для подключения ваших шрифтов к редактору верстка, формат такого файла описан ниже;

##Сохранение статьи

Сохраниение осуществляется в теле функции `$call_back_function`<br>
В нужный момент эта функция вызовется автоматически со следующими аргументами:<br>
* `$body` – исходный код сохраняемой статьи
* `$material_id` – идентификатор материала
* `$user_id` – идентификатор текущего пользователя
* `$images` – массив изображений в формате [{имя:абсолютный путь до файла в папке $temp_dir_abs}]
* `$custom_fields` – массив с дополнительными параметрами переданный в метод `$verstka->edit` при получении ссылки на редактирования.

Функция `$call_back_function` может, например, иметь следующий вид:
```
<?php
function save_article ($body, $material_id, $user_id, $images, $custom_fields) {
  $material_images_dir_relative = '/images/verstka/' . $material_id . '/';
  $material_images_dir_absolute = $_SERVER['DOCUMENT_ROOT'] . $material_images_dir_relative;
  $this->delete($material_images_dir_absolute);
  @mkdir($material_images_dir_absolute, 0777, true);

  $body = str_replace('/vms_images/', $material_images_dir_relative, $body);

  $db = \Resources::getDB();

  $affected = $db->prepare('UPDATE t_materials SET vms_content = :v_vms_content WHERE user_id = :v_user_id and material_id = :v_material_id')
    ->bind(':v_user_id', $user_id)
    ->bind(':v_material_id', $material_id)
    ->bind(':v_vms_content', $body)->execute()->affectedRows();

  if ($affected != 1) {
    $db->rollback();
    return false;
  } else {
    foreach ($images as $image_name => $image_file) {
      rename($image_file, $material_images_dir_absolute . $image_name);
    }
    \Resources::getCacheManager()->flush($material_id);
    return true;
  }
}
php?>
```

##Использование собственных шрифтов

Нужно собрать css файл с определенными комментариями и зашитыми в base64 шрифтами и тогда они автоматически появятся в Верстке.

Вверху css файла нужно в комментах указать дефолтный шрифт, который будет выставляться при создании нового текстового объекта.
```
/* default_font_family: 'formular'; */
/* default_font_weight: 400; */
/* default_font_size: 16px; */
/* default_line_height: 24px; */
```

Далее, для каждого `@font-face` нужно прописать комментарии с названием шрифта и его начертанием
```
  /* font_name: 'Formular'; */
  /* font_style_name: 'Light'; */
```

Итоговый css файл:
```
/* default_font_family: 'formular'; */
/* default_font_weight: 400; */
/* default_font_size: 16px; */
/* default_line_height: 24px; */

@font-face {
  /* font_name: 'Formular'; */
  /* font_style_name: 'Light'; */
   font-family: 'formular';
   src: url(data:application/font-woff2;charset=utf-8;base64,KJHGKJHGJHG) format('woff2'),
        url(data:application/font-woff;charset=utf-8;base64,KJHGKJHGJHG) format('woff');
   font-weight: 300;
   font-style: normal;
}

@font-face {
  /* font_name: 'Formular'; */
  /* font_style_name: 'Regular; */
   font-family: 'formular';
   src: url(data:application/font-woff2;charset=utf-8;base64,KJHGKJHGJHG) format('woff2'),
        url(data:application/font-woff;charset=utf-8;base64,KJHGKJHGJHG) format('woff');
   font-weight: 400;
   font-style: normal;
}
```


