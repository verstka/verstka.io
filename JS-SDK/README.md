# Verstka JavaScript SDK

## Интеграция с CMS
Статьи созданные в **Verstka**, как и обычные материалы, представляют из себя HTML-код и файлы изображений.
JavaScript SDK взаимодействует с сервером посредством multipart/form-data.

### Подключение SDK
Подключите скрипт:
```
<script src="//go.verstka.io/sdk.js"></script>
```
Данный код будет автоматически инициализирован, когда страница загрузится.

### Форма
В форму CMS необходимо добавить следующий элемент:
```
<textarea
  name="[name]"
  value="[content]"
  data-verstka_api_key="[api_key]"
  data-images_source="[images_source]"
  data-user_id="[user_id]"
  data-material_id="[material_id]"
  data-save_url="[save_url]"
  data-save_data="[save_data]"
  data-save_handler="[save_handler]"
  data-state_change_handler="[state_change_handler]"
  data-is_active="[is_active]"
  data-back_button_text="[back_button_text]"
></textarea>
```

В этом элементе нужно указать обязательные параметры:
* `content` – HTML-код статьи. Равен пустой строке, если статья еще не создана
* `verstka_api_key` – API-key, выдаваемый при подключении
* `user_id` – идентификатор пользователя
* `material_id` – идентификатор материала
* `save_url` – URL на вашей стороне, на который будут посланы данные при сохранении статьи

и необязательные:
* `name` – произвольное имя данного элемента
* `images_source` – URL на вашей стороне, относительно которого будут запрашиваться файлы изображений (по умолчанию hostname CMS)
* `save_handler` – обработчик, который будет вызван при сохранении статьи (альтернатива [`save_url`])
* `save_data` – дополнительные данные, которые будут посланы во время сохранения (например, "foo=1&some=2")
* `is_active` – текущее состояние режима **Verstka**
* `back_button_text` – надпись на кнопке отключения режима **Verstka**
* `state_change_handler` - обработчик, вызываемый при переключении режима **Verstka**

Элемент `textarea` является служебным, и может быть скрыт от пользователя.<br>
Если все параметры указаны верно, то при инициализации SDK в форме появится кнопка &laquo;**Edit with Verstka**&raquo; (сразу после элемента `textarea`).<br>
Данная кнопка открывает в новой вкладке редактор **Verstka**.

## Взаимодействие CMS с редактором **Verstka**
Все взаимодействия между CMS и редактором происходят автоматически, согласно параметрам, указанным в элементе `textarea`.

### Создание и редактирование статей
Создание и редактирование статей осуществляется посредством кнопки &laquo;**Edit with Verstka**&raquo;, которая открывает редактор **Verstka** в новой вкладке.

### Сохранение статей
После того, как статья будет сохранена в редакторе **Verstka**, SDK осуществит POST-запрос по адресу `save_url`, передав HTML-код статьи, идентификатор пользователя, идентификатор материала и файлы изображений в формате multipart/form-data.<br>
Ответ от сервера должен прийти в формате JSON:<br>
```
{
  content: [html]
}
```
При этом, поле `content` должно содержать обновленный HTML статьи.

## Отображение статей
Вывод HTML-кода статьи должен сопровождаться подключением скрипта:

```
<script type = "text/javascript">
  window.onVMSAPIReady = function( api ) {
    api.Article.enable( {
      [options]
    } );
  };
</script>
<script src="//go.verstka.io/api.js" async type="text/javascript"></script>
```

## Параметры `options`
Все параметры являются не обязательными
* `display_mode` – включает режим отображение статьи [`desktop` | `mobile`]
* `auto_mobile_detect` – автоматическое определение мобидьных устройство по User Agent, default: `true`
* `mobile_max_width` – ширина окна браузера, при которой происходит переключение между мобильной и десктопной версией статьи
* `observe_selector` – влючает DOM Observer для селектор и реинициализурет статью при изменении DOM объекта
