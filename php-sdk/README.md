# Verstka PHP SDK

## Start

### Database Preparation
Articles created in **Verstka**, like regular materials, are HTML code.
To store articles you can use the current table containing materials.
To do this, you must add the following fields:
* `is_vms: boolean` - a flag by which you can determine whether an article is made using ** Verstka **
* `vms_source_desktop: text` - body of the article
* `vms_source_mobile: text` - body of the article for mobile

### Preparing the folder structure
The HTML code of the article will contain links to images stored on your server.
To do this, create a directory, for example, `/images/verstka/`.

### Preparing Routing
Register the URL, which, when opened, it will create and edit materials. <br>
If `MATERIAL_ID` of all types of materials that you plan to edit using **Verstka** pass-through, then such a URL may, for example, have the form `/verstka_edit/[MATERIAL_ID]`.

At this address, you must place the code that connects and initializes the SDK.

```
<?php
  include __DIR__.'/vendor/vms/class.VMSsdk.php';
  $verstka = new \devnow\VMSsdk($apikey, $secret, $call_back_url, $call_back_function, $temp_dir_abs, $web_root_abs, $static_host_name);
php?>
```
* The values of `$apikey` and `$secret` are given to the client upon integration.
* `$call_back_url` - the URL for saving materials (at this URL you must place an instance of the class as described above)
* `$call_back_function` - the function to save material to the database and move images from the temporary directory to the image folder on your site
* `$temp_dir_abs` - absolute path on the server's file system for storing temporary files when working with the editor
* `$web_root_abs` is the absolute path on the server's file system where the root folder of the site is located
* `$static_host_name` - optionally host on which the layout will look for images with relative paths when opening an article for editing. It is advisable to use URLs with absolute paths (replace '/ vms_images /' in html articles when saving to 'https://static.yourserver.com/path/to/your/article/images/' for example)


This code performs:
* download images to the temporary files directory `$ temp_dir_abs`
* passing the callback function `$ call_back_function`, which will be called when saving material

## Creating and editing materials

After creating an instance of a class by calling $ verstka = new \devnow\VMSsdk(...) as shown above, you need to follow your authorization procedure. This must be done after The VMSsdk class constructor automatically checks the headings of the current request for signs of callback from Verstka.io about saving the article. If so, he will independently verify the validity of the request and download the images and call your save function.

### Local Authorization Bundle

When trying to open a URL to edit a material, local authorization must: <br>
* check that the current user has the necessary rights
* define `user_id`, which will be passed to` $ call_back_function`

### Database Request
After that, it is necessary to request the current HTML article from the database. <br>
For example, a query might look like this:
```
select vms_source_desktop from t_materials where is_vms = true and material_id = [MATERIAL_ID]
```

### Getting an edit link
Next, a request is made to the server ** Verstka ** to create a session. <br>
We request the URL of this session:

```
<?php
  $result = $verstka->edit($outgoing['vms_source_desktop'], $params['material_id'], $auth['user_id'], $custom_fields);
  
  if (!empty($result['edit_url'])) {
    header('Location: ' . $result['edit_url']);
    echo '<a href="' . $result['edit_url'] . '" target="_blank">Edit ' . $result['edit_url'] . '</a>';
  } else {
    print_r($result);
  }
  
  die;
php?>
```

The array `$ custom_fields` may contain additional parameters that you want to use in the future, for example:

`$ custom_fileds ['edit_link']` - absolute URL for editing the current article

`$ custom_fileds ['fonts.css']` - relative path on your server where css is stored for connecting your fonts to the layout editor, the format of such a file is described below;

## Saving an article

This is stored in the body of the function `$ call_back_function` <br>
At the right moment, this function will be called automatically with the following arguments: <br>
* `$ body` - source code of the saved article
* `$ material_id` - material identifier
* `$ user_id` - current user ID
* `$ images` - an array of images in the format [{name: the absolute path to the file in the $ temp_dir_abs folder}]
* `$ custom_fields` - an array with additional parameters passed to the` $ verstka-> edit` method when the edit link is received.

The function `$ call_back_function` may, for example, have the following form:
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

## Use your own fonts

You need to build a css file which certain comments and fonts sewn into base64 and then they will automatically appear in the Layout.

At the top of the css file, you need to specify in the comments the default font that will be displayed when creating a new text object.
```
/* default_font_family: 'formular'; */
/* default_font_weight: 400; */
/* default_font_size: 16px; */
/* default_line_height: 24px; */
```

Further, for each `@ font-face` it is necessary to register comments with the name of the font and its style.
```
   /* font_name: 'Formular'; */
   /* font_style_name: 'Light'; */
```

Final css file:
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

## Connection via composer

### composer.json

```
{
  "repositories": [
    {
      "type": "git",
      "url": "https://github.com/verstka/verstka.io",
      "path": "/PHP-SDK"
    }
  ],
  "require": {
    "verstka/php-sdk": "dev-master"
  }
}
```
