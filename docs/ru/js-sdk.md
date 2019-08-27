# Verstka JavaScript-SDK

  * [Интеграция с CMS](#Интеграция-с-cms)
    * [Подключение SDK](#Подключение-sdk)
    * [Добавление в форму](#Добавление-в-форму)
    
  * [Взаимодействие CMS с редактором](#Взаимодействие-cms-с-редактором)
  
    * [События is_active и mobile_mode](#События-is_active-и-mobile_mode)
    
      * [Сохранение с помощью POST-запроса](#Сохранение-с-помощью-post-запроса)
      * [Сохранение с помощью вызова JS-функции](#Сохранение-с-помощью-вызова-js-функции)
      
    * [События material_desktop и material_mobile](#События-material_desktop-и-material_mobile)
    
      * [Сохранение с помощью POST-запроса](#Сохранение-с-помощью-post-запроса-1)
      * [Сохранение с помощью вызова JS-функции](#Сохранение-с-помощью-вызова-js-функции-1)
      
  * [Отображение статей](#Отображение-статей)
    * [Параметры options](#Параметры-options)

## Интеграция с CMS
Статьи созданные в **Verstka** представляют из себя HTML-код и файлы изображений.<br>
JavaScript-SDK взаимодействует с CMS посредством POST-запросов и/или вызова локальных JavaScript-функций.

### Подключение SDK
Подключите скрипт:
```
<script src="//go.verstka.io/sdk/v2.js"></script>
```
SDK будет автоматически инициализирован, когда страница загрузится.

### Добавление в форму
Чтобы создать панель для редактирования материала, добавьте в форму CMS следующие элементы:
```
<xmp name="verstka" hidden>
	{
		'api_key': [api_key],
		'material_id': [material_id],
		'images_source': [images_source],
		'materials': {
			'desktop': [desktop],
			'mobile': [mobile]
		},
		'actions': [actions]
		'name': [name],
		'user_id': [user_id],
		'is_active': [is_active],
		'mobile_mode': [mobile_mode]
	}
</xmp>

<textarea hidden>[material_desktop]</textarea>
<textarea hidden>[material_mobile]</textarea>
```

Элемент `xmp` содержит настройки для данного материала.
* `api_key` – уникальный ключ, выдаваемый при интеграции.
* `material_id` – уникальный идентификатор материала.
* `actions` – обработчики событий, о которых будет сказано [ниже](#Взаимодействие-cms-с-редактором-verstka).
* `images_source` – hostname, на котором будут храниться файлы изображений. По умолчанию, совпадает с hostname CMS. Изображения в материале имеют относительный путь и будут ссылаться на данный hostname.
* `name` – произвольное имя панели управления. Рекомендуем указывать для удобства отладки.
* `user_id` – уникальный идентификатор текущего пользователя. Указывайте, если хотите знать, какие пользователи редактировали материал.
* `is_active` – флаг, сообщающий о том, включена ли **Verstka** для данной страницы. Флаг используется в том случае, если CMS использует другие редакторы помимо **Verstka**.
* `mobile_mode` – способ отображения мобильной версии. `0` - отображать десктопную версию, `1` – автоматически конвертировать десктопную версию в мобильную, `2` – отображать отдельный материал в мобильной версии.
* `materials` – селекоторы элементов `textarea`, которые содержат HTML статьи.

Элементы `textarea` содержат HTML статьи.
* `material_desktop` - HTML десктопной версии.
* `material_mobile` - HTML мобильной версии. Указывается только если вы хотите отображать отдельный материал в мобильной версии, чему соответсвует значение `mobile_mode: 2`.

Если все параметры указаны верно, то после инициализации SDK в форме появится панель &laquo;**Verstka**&raquo;.<br>

## Взаимодействие CMS с редактором
Все взаимодействия между CMS и редактором происходят по правилам, описанным в поле `actions`.<br>
Редактор генерирует события. `actions` содержит инструкции о том, как ваша CMS будет обрабатывать то или иное событие.
Каждая такая инструкция – экшн – работает в одном из двух режимов. Экшн либо отсылает POST-запрос по заданному URL, либо вызывает JS-функцию из глобальной области видимости:
```
`actions`: {
	'<имя_события>': {
		'ajax': {
			'url': '<URL_для_обработки_запроса>',
			'data': { <произвольный_JSON> }
		}
	}
}
```
или
```
`actions`: {
	'<имя_события>': {
		'method': '<имя_функции>'
	}
}
```

Редактор генерирует четыре события.

### События `is_active` и `mobile_mode`
`is_active` соответствует одноименному полю в настройках, то есть, сообщает активна ли **Verstka** для данной страницы.<br>
`mobile_mode` также соответствует одноименному полю в настройках, то есть, определяет вариант отображения мобильной версии.<br>Ниже описаны варианты обработки этих событий.<br>

#### Сохранение с помощью POST-запроса
Изменение `mobile_mode` вызовет отправку POST-запроса на адрес `/url-for-saving-mobile-mode`. Запрос будет содержать данные, переданные в `data` и поле `my_field_name`, значение которого будет `0`, `1` или `2`:
```
"actions": {
	"is_active": {
		"ajax": {
			"url": "/url-for-saving-mobile-mode",
			"data": { ... },
			"value_as": "my_field_name"
		}
	}
}
```
Ответ на этот запрос должен иметь следующий вид:
```
{
	"status": 1, // 1 в случае успеха, и 0 в обратном случае.
}
```

#### Сохранение с помощью вызова JS-функции
Изменение `is_active` вызовет JS-функцию `myMethodForSavingIsActive` из глобальной области видимости:
```
"actions": {
	"is_active": {
		"method": "myMethodForSavingIsActive"
	}
}
```
В указаную функцию будут переданы аргументы:
* `event_name` – название события, в данном случае, `is_active`;
* `current_value` – текущее значение, в данном случае, `0` или `1`;
* `prev_value` – предыдущее значение;
* `callback` – коллбэк, который нужно вызвать как `callback({status: 1})`, если ваша функция успешно сработала, и `callback({status: 0})` в противном случае.
Функция должна иметь следующий вид:
```
function myMethodForSavingIsActive( event_name, current_value, prev_value, callback ) {

	//...код, с помощью которого сохраняется значение поля "is_active".
	
	if (/* если метод успешно сработал */) {
		callback({status: 1});
	} else {
		callback({status: 0});
	}
}
```

### События `material_desktop` и `material_mobile`
Эти события сигнализируют о том, что материал был сохранен.<br>
Ниже описаны варианты обработки этих событий.<br>

#### Сохранение с помощью POST-запроса
Сохранение материала вызовет отправку POST-запроса на адрес `/url-for-saving-desktop-material`. В данных запроса будут содержаться данные из `data`, а также содержимое статьи **в формате `multipart/form-data`**:
```
"actions": {
	"is_active": {
		"ajax": {
			"url": "/url-for-saving-desktop-material",
			"data": { ... }
		}
	}
}
```
Ответ на этот запрос должен иметь следующий вид:
```
{
	"status": 1, // 1 в случае успеха, и 0 в обратном случае.
	"content": "..." // HTML сохраненного материала.
}
```
Пример обработки этого запроса на PHP. Описанная функция выделяет `$html` - HTML статьи, `$custom_fields_json` - JSON с дополнительными данными и `$images` – массив с изображениями. ВАЖНО! Не переименовывайте файлы изображений!
```
function saveDesktopMaterial() {
	$html = '';
	$custom_fields_json = '';
	$images = [];

	foreach ($_FILES as $file_name => $file_data) {

		if ($file_name == 'index_html') {
			$html = file_get_contents($file_data['tmp_name']);
			continue;
		}
		
		if ($file_name == 'custom_fields_json') {
			$custom_fields_json = file_get_contents($file_data['tmp_name']);
			continue;
		}

		$images[$file_name] = $file_data;
	}
	
	$html = str_replace('/vms_images/', '/relative_path_to_uploads/' . $_POST['material_id'] . '/', $html);
	
        // ...
	// Код, сохраняющий содержимое статьи.
	// ...
	
	return encode_json( [
		"status" => 1,
		"content" => $html
	] );
}
```

#### Сохранение с помощью вызова JS-функции
Экшн в формате JS-функции `myMethodForSavingMobileMaterial`:
```
"actions": {
	"is_active": {
		"method": "myMethodForSavingMobileMaterial"
	}
}
```
В указаную функцию будут переданы аргументы:
* `event_name` – название события, в данном случае, `material_mobile`;
* `current_value` – содержимое статьи в формате `FormData`;
* `prev_value` – всегда null;
* `callback` – коллбэк, который нужно вызвать как `callback({status: 1, content: "<HTML_ сохраненного материала>"})`, если ваша функция успешно сработала, и `callback({status: 0})` в противном случае.
Функция должна иметь следующий вид:
```
function myMethodForSavingMobileMaterial( event_name, current_value, prev_value, callback ) {
	
	var html_file,
	    custom_fields_file,
	    images_files = [];
	    
	for(var pair of formData.entries()) {
		var file_name = pair[0],
		    file = pair[1];
		
		switch (file_name) {
			case 'index.html':
				html_file = file;
				break;
				
			case 'custom_fields.json':
				custom_fields_file = file;
				break;
				
			default:
				images_files.push(file);
		}
	}

	// ...
	// Код, сохраняющий содержимое статьи.
	// ...
	
	if (/* если метод успешно сработал */) {
		callback({status: 1, content: html});
	} else {
		callback({status: 0});
	}
}
```

## Отображение статей
Вывод HTML-кода статьи должен сопровождаться подключением скрипта:

```
<script type = "text/javascript">
	window.onVMSAPIReady = function( api ) {
		api.Article.enable( {
			<options>
    		} );
  	};
</script>
<script src="//go.verstka.io/api.js" async type="text/javascript"></script>
```

### Параметры `options`
Все параметры являются необязательными
* `display_mode` – переключает между режимами отображения статьи (`desktop` или `mobile`). Default: `desktop`;
* `auto_mobile_detect` – автоматическое определение мобильных устройств по User Agent. Default: `true`;
* `mobile_max_width` – ширина окна браузера, при которой происходит переключение между мобильной и десктопной версией статьи;
* `observe_selector` – селекторы DOM-элементов, которые потенциально могут изменить положение статьи. Например, здесь указывается селектор баннера, расхлапывающегося над статьей.
