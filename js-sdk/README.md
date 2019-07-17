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


# Verstka JavaScript-SDK

	* [Integration with CMS](#Integration-with-cms)
		* [Connect SDK](#connect-sdk)
		* [Add to form](#Add-on-form)
    
	* [CMS interaction with editor](#Interaction-cms-with-editor)
  
	* [Events is_active and mobile_mode](#Events-is_active-and-mobile_mode)
    
		* [Saving using a POST request](#Saving-using-post-request)
		* [Saving using a JS function call](#Saving-using-calling-js-functions)
      
	* [Material_desktop and material_mobile events](#Events-material_desktop-and-material_mobile)
    
		* [Saving using a POST request](#Saving-using-post-request-1)
		* [Saving using a JS function call](#Saving-using-calling-js-function-1)
      
	* [Display articles](#Display-articles)
		* [Options options](#options-options)

## CMS Integration
Articles created in **Verstka** are HTML code and image files. <br>
JavaScript-SDK interacts with CMS by means of POST requests and/or calling local JavaScript functions.

### SDK connection
Connect the script:
```
<script src="//go.verstka.io/sdk/v2.js"></script>
```
The SDK will be automatically initialized when the page loads.

### Adding to the form
To create a panel for editing the material, add the following elements to the CMS form:
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

The element `xmp` contains settings for this material.
* `api_key` is a unique key issued during integration.
* `material_id` - unique identifier of the material.
* `actions` - event handlers that will be discussed [below] (# Interaction-cms-with-editor-verstka).
* `images_source` - the hostname on which image files will be stored. The default is the same as the CMS hostname. Images in the material have a relative path and will refer to the given hostname.
* `name` - arbitrary name of the control panel. We recommend to specify for ease of debugging.
* `user_id` - unique identifier of the current user. Specify if you want to know which users edited the material.
* `is_active` - a flag indicating whether ** Verstka ** is enabled for this page. The flag is used if CMS uses other editors besides ** Verstka **.
* `mobile_mode` - a way to display the mobile version. `0` - display the desktop version,` 1` - automatically convert the desktop version to the mobile version, `2` - display a separate material in the mobile version.
* `materials` - selectors of` textarea` elements that contain HTML articles.

The `textarea` elements contain HTML articles.
* `material_desktop` - HTML desktop version.
* `material_mobile` - HTML mobile version. It is specified only if you want to display a separate material in the mobile version, which corresponds to the value `mobile_mode: 2`.

If all the parameters are specified correctly, then after initializing the SDK, a panel will appear in the form of the & laquo; **Verstka**&raquo;. <br>

## CMS interaction with editor
All interactions between the CMS and the editor occur according to the rules described in the `actions` field. <br>
The editor generates events. `actions` contains instructions on how your CMS will handle this or that event.
Each such instruction - action - works in one of two modes. The action either sends a POST request to the specified URL, or calls the JS function from the global scope:
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
or
```
`actions`: {
	'<имя_события>': {
		'method': '<имя_функции>'
	}
}
```
The editor generates four events.

### Events is_active` and `mobile_mode`
`is_active` corresponds to the field of the same name in the settings, that is, whether it says ** Verstka ** is active for this page. <br>
`mobile_mode` also corresponds to the field of the same name in the settings, that is, it defines the option of displaying the mobile version. The options for handling these events are described below.

#### Saving using a POST request
Changing `mobile_mode` will cause a POST request to be sent to` / url-for-saving-mobile-mode`. The request will contain data passed to `data` and the field` my_field_name`, whose value will be `0`,` 1` or `2`:
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
The response to this request should be as follows:
```
{
	"status": 1, // 1 в случае успеха, и 0 в обратном случае.
}
```

#### Saving using a JS function call
Changing `is_active` will call the JS function` myMethodForSavingIsActive` from the global scope:
```
"actions": {
	"is_active": {
		"method": "myMethodForSavingIsActive"
	}
}
```
Arguments will be passed to the specified function:
* `event_name` - the name of the event, in this case,` is_active`;
* `current_value` - the current value, in this case,` 0` or `1`;
* `prev_value` - previous value;
* `callback` is the callback that you want to call as` callback ({status: 1}) `if your function worked successfully, and` callback ({status: 0}) `otherwise.
The function should have the following form:
```
function myMethodForSavingIsActive( event_name, current_value, prev_value, callback ) {

	//...code with which the value of the "is_active" field is saved.
	
	if (/* if the method worked successfully */) {
		callback({status: 1});
	} else {
		callback({status: 0});
	}
}
```

### Events of `material_desktop` and` material_mobile`
These events signal that the material has been saved. <br>
The options for handling these events are described below. <br>

#### Saving using a POST request
Saving the material will cause a POST request to be sent to the address `/url-for-saving-desktop-material`. The request data will contain data from `data`, as well as the contents of the article **in the format` multipart/form-data`**:
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
The response to this request should be as follows:
```
{
	"status": 1, // 1 if successful, and 0 otherwise.
	"content": "..." // HTML of saved material.
}
```
An example of processing this request for PHP. The described function selects `$html` - HTML articles,` $custom_fields_json` - JSON with additional data and `$images` - an array with images.
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
	// The code that stores the contents of the article.
	// ...
	
	return encode_json( [
		"status" => 1,
		"content" => $html
	] );
}
```

#### Saving using a JS function call
Action in JS format `myMethodForSavingMobileMaterial`:
```
"actions": {
	"is_active": {
		"method": "myMethodForSavingMobileMaterial"
	}
}
```
Arguments will be passed to the specified function:
* `event_name` - the name of the event, in this case,`material_mobile`;
* `current_value` - the contents of the article in the format` FormData`;
* `prev_value` is always null;
* `callback` - a callback that you want to call as` callback ({status: 1, content: "<HTML_ saved material>"}) `, if your function has worked successfully, and` callback ({status: 0}) `in otherwise.
The function should have the following form:
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
	// The code that stores the contents of the article.
	// ...
	
	if (/* if the method worked successfully */) {
		callback({status: 1, content: html});
	} else {
		callback({status: 0});
	}
}
```

## Displaying Articles
The HTML code of the article should be accompanied by the connection of the script:

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

### Options `options`
All parameters are optional.
* `display_mode` - switches between article display modes (` desktop` or `mobile`). Default: `desktop`;
* `auto_mobile_detect` - automatic detection of mobile devices by User Agent. Default: `true`;
* `mobile_max_width` - the width of the browser window, at which the switching between mobile and desktop version of the article takes place;
* `observe_selector` - selectors of DOM elements that can potentially change the position of the article. For example, here is indicated the selector of a banner rapping over an article.
