# Quick links

[PHP SDK](https://github.com/verstka/php-sdk)<br>
[JavaScript SDK](https://github.com/verstka/verstka-js-sdk)<br>
[🇬🇧English language](https://github.com/verstka/verstka.io/tree/master)



# Verstka API

Верстка предлагает разработчикам удобный способ интеграции редактора на сайт посредством API

Формат любого запроса POST multipart/formData

Формат возвращаемых данных (кроме скачивания файлов) – JSON, и будет иметь следующие поля:

*  `rc` - код результата выполнения (1 - для успешных запросов)
*  `rm` - сообщение в текстовом виде
*  `data` - массив возвращаемых данных

Такого же формата необходимо придерживаться в ответах на запросы verstka при сохранении статьи.

## Редактирование статьи

Для редактирования статьи достаточно отправить POST-запрос на URL https://verstka.org/api/open c параметрами:

*  `material_id` - идентификатор материала (обязательный уникальный не нулевой, при верстке отдельной мобильной версии используйте префикс 'M' в этом поле)
*  `user_id` - идентификатор текущего пользователя
*  `html_body` - html статьи (пустой в случае новой статьи)
*  `api-key` - API-key, выдаваемый при подключении к Verstka SaaS API
*  `callback_url` - URL-адрес, на который придет запрос при сохранении статьи в редакторе
*  `host_name` - с этого хоста редактор попытается скачать изображения статьи (если `html_body` содержит изображения и они имеют относительные адреса)
*  `user_ip` - IP-адрес текущего пользователя (дополнительный уровень безопасности при открытии редактора)
*  `callback_sign` - md5 от сконкатенированных параметров в следующем порядке: secret, api-key, material_id, user_id, callback_url
*  `custom_fields` - массив дополнительных параметров в формате JSON для включения дополнительных функций редактора

Возможные ключи массива `custom_fields`:

*  `mobile` = true - для редактирования мобильной версии статьи
*  `auth_user` и `auth_pw` - если `host_name` закрыт с помощью http-авторизации
*  `fonts.css` - относительный путь до CSS-файла со шрифтами для подключения к редактору (описан ниже)
*  любые дополнительные данные (будут возвращены при сохранении статьи в неизменном виде)

В ответ Verstka API вернет в виде JSON следующие поля данных:

*  `session_id` - уникальный идентификатор сессии редактирования
*  `edit_url` - URL страницы редактора для этой сессии

Также в ответе будут дополнительные поля:

*  `last_save` - время последнего сохранения статьи (в случае если статья недавно редактировалась) *DEPRECATED*
*  `contents` - URL для получения содержимого сессии редактирования (необходим только для интеграции без `callback_url`)
*  `client_folder` - вычисленный относительный URL до статического контента статьи на `host_name` (для debug)

В случае если редактору не удастся скачать некоторые изображения статьи, вернутся следующие дополнительные параметры:

*  `lacking_pictures` - список недостающих изображений
*  `upload_url` - URL для загрузки посредством POST multipart/form-data


# Сохранение статьи

Сохранение статьи доступно в течение 48 часов с последнего взаимодействия (открытия или предыдущего сохранения) этой статьи

При нажатии пользователем кнопки "сохранить" в редакторе будет запрошен `callback_url` и с помощью POST переданы следующие параметры:

*  `material_id` - идентификатор сохраняемого материала
*  `user_id` - идентификатор текущего пользователя
*  `session_id` - уникальный идентификатор сессии редактирования
*  `html_body` - html сохраняемой статьи
*  `download_url` - URL для скачивания статического контента
*  `custom_fields` - JSON с дополнительными полями, переданными при открытии редактора
*  `callback_sign` - цифорвая подпись запроса, генерируемая по следующему алгоритму:

md5 от сконкатенированных параметров в следующем порядке: secret, session_id, user_id, material_id, download_url, где
`secret` - ключ, выдаваемый при подключении к Verstka SaaS API

Изображения статьи доступны по адресу `download_url` (возвращает список) и `download_url`/`name` (возвращает файл),
где `name` - имя файла для скачивания. Вы можете хранить изображения в любой папке на своем веб-сайте, но сохраните исходное имя файла пожалуйста.

## Использование собственных шрифтов

Нужно собрать CSS-файл с определенными комментариями и зашитыми в base64 шрифтами, и тогда они автоматически появятся в Верстке.
Расположение файла по умолчанию /vms_fonts.css

Вверху CSS-файла нужно в комментах указать дефолтный шрифт, который будет выставляться при создании нового текстового объекта.
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

Итоговый CSS-файл:
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

## Отображение статей
Вывод HTML-кода статьи должен сопровождаться подключением скрипта:

```
<link href="//go.verstka.org/critical.css" rel="stylesheet">

<script type="text/javascript">
    window.onVMSAPIReady = function(api) {
        api.Article.enable ({
            display_mode: 'default'
        });
    };
</script>
<script src="//go.verstka.org/api.js" async type="text/javascript"></script>
```

### Параметры `options`
#### Все параметры являются необязательными
* `observe_selector` – селекторы DOM-элементов, которые потенциально могут изменить положение статьи. Например, здесь указывается селектор баннера, расхлапывающегося над статьей.
#### Далее параметры если верстать отдельную мобильную версию невозможно:
* `display_mode` – переключает между режимами отображения статьи, всегда ставьте `default`;


