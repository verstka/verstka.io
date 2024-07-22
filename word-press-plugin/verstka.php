<?php
/*
Plugin Name: Verstka
Plugin URI: http://verstka.org
Description: Powerfull design tool & WYSIWYG
Version: 2.0.4
Author: devnow
Author URI: http://devnow.ru
*/

/*  Copyright 2016  devnow  (email: hello@devnow.ru)
    Free use is limited to 200,000 article views created by verstka.org per month,
    to obtain a license the company please contact our team via email hello@verstka.io.

In case of violation of this agreement you lose the ability to edit your articles via verstka.org
all maked by verstka.org content remain yours but in this case you will be able to edit them only manually.
*/

/*
    вставляет нужные поля в базу
*/
function wp_maybe_add_column($table_name, $column_name, $create_ddl)
{
    global $wpdb;
    foreach ($wpdb->get_col("DESC $table_name", 0) as $column) {
        if ($column == $column_name) {
            return true;
        }
    }

    // Didn't find it try to create it.
    $wpdb->query($create_ddl);

    // We cannot directly tell that whether this succeeded!
    foreach ($wpdb->get_col("DESC $table_name", 0) as $column) {
        if ($column == $column_name) {
            return true;
        }
    }

    return false;
}

add_action('init', 'verstka_init');
function verstka_init()
{
    global $wpdb;
    $table_name = $wpdb->posts;
    $column_name_isvms = 'post_isvms';
    $column_name_vms_mobile_mode = 'vms_mobile_mode';
    $column_name_vms_content = 'post_vms_content';
    $column_name_vms_content_mobile = 'post_vms_content_mobile';

    $sqlQuery_add_isvms = "ALTER TABLE $table_name ADD COLUMN post_isvms BOOLEAN NOT NULL DEFAULT 0 AFTER post_date_gmt";
    $sqlQuery_add_vms_content = "ALTER TABLE $table_name ADD COLUMN post_vms_content longtext AFTER post_content";
    $sqlQuery_add_vms_content_mobile = "ALTER TABLE $table_name ADD COLUMN post_vms_content_mobile longtext AFTER post_vms_content";

    if (wp_maybe_add_column($table_name, $column_name_isvms, $sqlQuery_add_isvms)
        && wp_maybe_add_column($table_name, $column_name_vms_content, $sqlQuery_add_vms_content)
        && wp_maybe_add_column($table_name, $column_name_vms_content_mobile, $sqlQuery_add_vms_content_mobile)
    ) {
        //колонка добавлена или уже существует :)
    } else {
        die('Error adding VMS cloumns into db');
    }
}

/*
    Сохраняет галочку is_vms
*/
add_action('save_post', 'save_isvms_post_state');
function save_isvms_post_state($post_id)
{
    global $wpdb, $post; //post old state & db
    $is_vms = $_REQUEST['is_vms'] ?? null;

    if (!empty($is_vms)) {
        $data = [
            'post_isvms' => ($is_vms == 'true') ? 1 : 0
        ];

        $wpdb->update($wpdb->posts, $data, ['id' => $post_id]);
    }
}

/*
    Обрабатывает аякс на сохранение VMS-статьи (десктопная версия)
*/
add_action('wp_ajax_save_vms_article', 'save_vms_article');
function save_vms_article()
{
    return save_vms_article_backend();
}

/*
    Обрабатывает аякс на сохранение VMS-статьи (мобильная версия)
*/
add_action('wp_ajax_save_vms_article_mobile', 'save_vms_article_mobile');
function save_vms_article_mobile()
{
    return save_vms_article_backend(true);
}

/*
    Обрабатывает аякс на сохранение VMS-статьи
*/
function save_vms_article_backend($is_mobile = false)
{
    $uploadDirRel = rtrim(get_option('images_dir') ?? '', '/');
    if (empty($uploadDirRel)) {
        $uploadDirAbs = wp_upload_dir()['basedir'] . '/vms';
        $uploadDirRel = parse_url(wp_upload_dir()['baseurl'], PHP_URL_PATH) . '/vms';
    } else {
        $uploadDirAbs = sprintf('%s/%s', rtrim(ABSPATH, '/'), ltrim($uploadDirRel, '/'));
    }

    $uploaded = [];
    $uploadMaterialPathAdding = sprintf(($is_mobile ? '%sm' : '%s'), $_POST['material_id']);
    $uploadDirAbs = sprintf('%s/%s', $uploadDirAbs, $uploadMaterialPathAdding);
    $uploadDirRel = sprintf('%s/%s', $uploadDirRel, $uploadMaterialPathAdding);
    @mkdir($uploadDirAbs, 0777, true);

    foreach ($_FILES as $file_name => $file_data) {
        if ($file_name == 'index_html') {
            $source = file_get_contents($file_data['tmp_name']);
            continue;
        }
        if ($file_name == 'custom_fields_json') {
            $custom_fields = file_get_contents($file_data['tmp_name']);
            continue;
        }

        $file_name = str_replace(['_large', '_small'], ['-large', '-small'], $file_name);
        $file_name = strrev(implode(strrev('.'), explode(strrev('_'), strrev($file_name), 2)));
        $file_name = str_replace(['-large', '-small'], ['_large', '_small'], $file_name);

        $upd = move_uploaded_file($file_data['tmp_name'], sprintf('%s/%s', $uploadDirAbs, $file_name));
        $uploaded[$file_name] = $upd;
    }

    $source = str_replace('/vms_images/', sprintf('%s/', $uploadDirRel), $source);

    $data = [
        'post_isvms' => 1,
    ];

    if ($is_mobile) {
        $data['post_vms_content_mobile'] = $source;
    } else {
        $data['post_vms_content'] = $source;
    }

    global $wpdb;
    $rew = $wpdb->update($wpdb->posts, $data, ['id' => $_POST['material_id']]);
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }

    echo json_encode([
        'status' => 1,
        'content' => $source
    ], JSON_NUMERIC_CHECK);

    wp_die();
}


/*
    Вставляет скрипты для подключения SDK
*/
add_action('admin_print_footer_scripts', 'my_admin_print_footer_scripts', 99);
function my_admin_print_footer_scripts()
{
    $post = get_post();
    if (is_object($post)) {
        $vms_content = $post->post_vms_content;

        $is_dev_mode = get_option('dev_mode') == 'on' ? true : false; ?>
        <script>
          var settings = {
              'api_key': '<?php echo get_option('api_key'); ?>',
              'images_source': '<?php echo get_option('images_source'); ?>',
              'name': 'wp_<?php echo $post->ID; ?>',
              'user_id': '<?php echo $post->post_author; ?>',
              'material_id': '<?php echo $post->ID; ?>',
              'is_active': <?php echo $post->post_isvms ? 1 : 0; ?> == 1,
            'mobile_mode'
          :
          2, // custom
            'materials'
          :
          {
            'desktop'
          :
            '#verstka_desktop',
              'mobile'
          :
            '#verstka_mobile'
          }
          ,
          'actions'
          :
          {
            'material_desktop'
          :
            {
              'ajax'
            :
              {
                'url'
              :
                window.ajaxurl,
                  'data'
              :
                {
                  'action'
                :
                  'save_vms_article'
                }
              }
            }
          ,
            'material_mobile'
          :
            {
              'ajax'
            :
              {
                'url'
              :
                window.ajaxurl,
                  'data'
              :
                {
                  'action'
                :
                  'save_vms_article_mobile'
                }
              }
            }
          ,
            'is_active'
          :
            {
              'method'
            :
              'verstkaActionHandler'
            }
          }
          }
          ;

          var xmp = parseHtml('<xmp name="verstka" hidden>' + JSON.stringify(settings) + '</xmp>');

          var textarea_desktop = parseHtml('<textarea id="verstka_desktop" hidden><?php echo urlencode($post->post_vms_content); ?></textarea>');

          var textarea_mobile = parseHtml('<textarea id="verstka_mobile" hidden><?php echo urlencode($post->post_vms_content_mobile); ?></textarea>');

          var post_title;

          var is_vms_input = parseHtml('<input type="text" name="is_vms" hidden/>');

          var isOldEditor = false;
          var isNewEditor = false;

          /** Old editor */
          waitFor('#post-body-content', () => {
            const post_title = document.getElementById('titlediv');

            insertAfter(post_title, xmp);
            insertAfter(post_title, textarea_desktop);
            insertAfter(post_title, textarea_mobile);
            insertAfter(post_title, is_vms_input);

            window.verstka_interface = {
              elements: {
                xmp: xmp,
                textarea_desktop: textarea_desktop,
                textarea_mobile: textarea_mobile,
                is_vms_input: is_vms_input,
              }
            };

            showOldEditor(!settings.is_active);

            isOldEditor = true;

            VerstkaSDK.start();
          })


          /** New editor */
          waitFor('.edit-post-header__settings', () => {
            const settingsElement = document.querySelector('.edit-post-header__settings');
            const headerElement = document.querySelector('.edit-post-header');

            headerElement.insertBefore(xmp, settingsElement);
            headerElement.insertBefore(textarea_desktop, settingsElement);
            headerElement.insertBefore(textarea_mobile, settingsElement);
            headerElement.insertBefore(is_vms_input, settingsElement);

            window.verstka_interface = {
              elements: {
                xmp: xmp,
                textarea_desktop: textarea_desktop,
                textarea_mobile: textarea_mobile,
                is_vms_input: is_vms_input,
              }
            };

            showNewEditor(!settings.is_active);

            isNewEditor = true;

            VerstkaSDK.start();
          })

          function showNewEditor(state) {
            const editor = document.querySelector('.edit-post-visual-editor');
            const toolbar = document.querySelector('.edit-post-header-toolbar');
            const value = state == true ? 'block' : 'none';

            editor.style.display = value;
            toolbar.style.display = value;
          }

          function showOldEditor(state) {
            const editor = document.querySelector('#postdivrich');
            const value = state == true ? 'block' : 'none';

            editor.style.display = value;

            if (state) {
              window.dispatchEvent(new Event('resize'));
            }
          }

          function waitFor(selector, callaback, timeout = 200, cancelTimeout = 5000) {
            const start = Date.now()

            setTimeout(() => {
              if (Date.now() - start >= cancelTimeout) {
                return
              }

              const element = document.querySelector(selector)

              if (element) {
                callaback(element)
              } else {
                waitFor(selector, callaback, timeout)
              }
            }, timeout)
          }

          function parseHtml(html) {
            var tmp = document.implementation.createHTMLDocument('');

            tmp.body.innerHTML = html;

            return tmp.body.children[0];
          }

          function insertAfter(target_el, el) {
            if (el.previousElementSibling !== target_el) {
              target_el.parentElement.insertBefore(el, target_el.nextSibling);
            }
          }

          window.verstkaActionHandler = function (action, current, prev, callback) {

            switch (action) {
              case 'is_active':
                verstka_interface.elements.is_vms_input.value = current ? 'true' : 'false';

                if (isOldEditor) {
                  showOldEditor(!current)
                }

                if (isNewEditor) {
                  showNewEditor(!current)
                }

                callback({status: 1});

                break;
            }

          };

        </script>

        <script src="//<?php echo $is_dev_mode ? 'dev' : 'go'; ?>.verstka.org/sdk/v3.js?<?php echo rand(1000, 9999); ?>"></script>
        <?php
    }
}

/*
    Подменяет содержимое статьи на нужную версию контента
*/
add_filter('the_content', 'apply_vms_content_after', 9999);
function apply_vms_content_after($content)
{
    $post = get_post();

	if ($post->post_isvms != 1) { // it's not an Verstka article
		return $content;
	}

	if (post_password_required($post)) { // in case of post password protected
		return $content;
	}

    $mobile = empty($post->post_vms_content_mobile) ? $post->post_vms_content : $post->post_vms_content_mobile;

    $desktop = base64_encode($post->post_vms_content);
    $mobile = base64_encode($mobile);
	
    $content = "<div class=\"verstka-article\">{$post->post_vms_content}</div>
		<script type=\"text/javascript\" id=\"verstka-init\">
		window.onVMSAPIReady = function (api) {
			api.Article.enable({
				display_mode: 'desktop'
			});

			document.querySelectorAll('article')[0].classList.add('shown');
		};

		function decodeHtml(base64) {
		    return decodeURIComponent(atob(base64).split('').map(function(c) {return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);}).join(''))
	      	}

		var htmls = {
			desktop: decodeHtml(`{$desktop}`),
			mobile: decodeHtml(`{$mobile}`),
		};
		var isMobile = false;
		var prev = null;

		function switchHtml(html) {
			var article = document.querySelector('.verstka-article')

			if (window.VMS_API) {
				window.VMS_API.Article.disable()
			}

			article.innerHTML = html;

			if (window.VMS_API) {
				window.VMS_API.Article.enable({display_mode: 'desktop'})
			}
		}

		function onResize() {
			var w = document.documentElement.clientWidth;

			isMobile = w < 768;

			if (prev !== isMobile) {
				prev = isMobile
				switchHtml(htmls[isMobile ? 'mobile' : 'desktop'])
			}
		}

		onResize()

		window.onresize = onResize;

	</script>

	";

    return $content;
}

/*
    Активирует отображение анимаций
*/
add_action('wp_head', 'add_this_script_footer');
function add_this_script_footer()
{
    $is_dev_mode = get_option('dev_mode') == 'on' ? true : false; ?>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="//<?php echo $is_dev_mode ? 'dev' : 'go'; ?>.verstka.org/api.js" async type="text/javascript"></script>

    <?php
}

/*
    Добавляет в список статей колонку Ѵ признак что это cтатья из verstka
*/
add_filter('manage_edit-post_columns', 'add_is_vms_column', 4);
function add_is_vms_column($columns)
{
    $result = [];
    foreach ($columns as $name => $value) {
        if ($name == 'title') {
            $result['is_vms'] = 'Ѵ';
        }
        $result[$name] = $value;
    }

    return $result;
}

/*
   Отображает закрашенную звездочку в колонке Ѵ если это статья из verstka
*/
add_filter('manage_post_posts_custom_column', 'fill_is_vms_column', 5, 2); // wp-admin/includes/class-wp-posts-list-table.php
function fill_is_vms_column($column_name, $post_id)
{
    if ($column_name != 'is_vms') {
        return;
    }
    $post = get_post($post_id);
    if ($post->post_isvms == 1) {
        echo '&#9733';
    } else {
        echo '&#9734';
    }
}

/*
   Позволяет сортировать статьи в списке по признаку Ѵ
*/
add_filter('manage_edit-post_sortable_columns', 'add_is_vms_sortable_column');
function add_is_vms_sortable_column($sortable_columns)
{
    $sortable_columns['is_vms'] = 'is_vms_is_vms';

    return $sortable_columns;
}

add_action('pre_get_posts', 'add_column_is_vms_request');
function add_column_is_vms_request($query)
{
    if (!is_admin()) {
        return;
    }
    if ($query->get('orderby') == 'is_vms_is_vms') {
        $query->set('orderby', 'post_isvms');
    }
}

add_action('admin_head', 'add_is_vms_column_css');
function add_is_vms_column_css()
{
    echo '<style type="text/css">.column-is_vms{width:3%;}</style>';
}

/*===============*/
/* settings page */
/*===============*/

function addOption($name, $value)
{
    if (get_option($name)) {
        update_option($name, $value);
    } else {
        $deprecated = ' ';
        $autoload = 'no';
        add_option($name, $value, $deprecated, $autoload);
    }
}

add_action('admin_menu', 'mt_add_pages');
function mt_add_pages()
{
    add_menu_page('Verstka', 'Verstka', 'edit_pages', __FILE__, 'verstka_settings_page');
}

add_action('admin_enqueue_scripts', 'wpdocs_register_verstka_styles');
function wpdocs_register_verstka_styles()
{
    wp_register_style('verstka_settings_styles', plugins_url('styles/settings.css', __FILE__));
    wp_enqueue_style('verstka_settings_styles');
}

function verstka_settings_page()
{
    $hidden_field_name = 'verstka_settings_hidden';
    $settings_names = [
        'email',
        'api_key',
        'images_source',
        'images_dir',
        'dev_mode'
    ];

    if (isset($_POST[$hidden_field_name]) && $_POST[$hidden_field_name] == 'true') {
        if ($_POST['confirm']) {
            foreach ($settings_names as $name) {
                if (!empty($_POST[$name])) {
                    update_option($name, $_POST[$name]);
                }
            }
        } elseif ($_POST['reset']) {
            foreach ($settings_names as $name) {
                delete_option($name);
            }
        } elseif ($_POST['dev_mode_off']) {
            update_option('dev_mode', 'off');
        } elseif ($_POST['dev_mode_on']) {
            update_option('dev_mode', 'on');
        }
    }

    $settings = [];
    foreach ($settings_names as $name) {
        $settings[$name] = get_option($name);
    } ?>

    <form name="verstka_settings" method="post"
          action="<?php echo str_replace('%7E', '~', $_SERVER['REQUEST_URI']); ?>">
        <input type="hidden" name="<?php echo $hidden_field_name; ?>" value="true">

        <?php if (!$settings['email'] && false) { ?>

            <div class="verstka_settings__section verstka_settings__section--step-1">
                <p class="verstka_settings__label">Where do we send an activation link?</p>
                <input class="verstka_settings__input" type="text" name="email"
                       value="<?php echo get_option('admin_email'); ?>">
                <input type="submit" name="activate" value="Get activation link"/>
            </div>

        <?php } elseif (!$settings['api_key']) { ?>
            <div class="verstka_settings__section verstka_settings__section--step-2">
                <p class="verstka_settings__label">
                    <span>API key:</span>
                    <input class="verstka_settings__input" type="text" name="api_key"
                           value="197b094891a44993b6be96edbcdb9dbc">
                </p>

                <p class="verstka_settings__label">
                    <span>Images source host:</span>
                    <input class="verstka_settings__input" type="text" name="images_source"
                           value="<?php echo $_SERVER['HTTP_HOST']; ?>">
                </p>

                <p class="verstka_settings__label">
                    <span>Images dir:</span>
                    <input class="verstka_settings__input" type="text" name="images_dir"
                           value="<?php echo parse_url(wp_upload_dir()['baseurl'], PHP_URL_PATH) . '/vms'; ?>">
                </p>

                <input type="submit" name="confirm" value="Confirm"/>
            </div>

        <?php } else { ?>

            <div class="verstka_settings__section verstka_settings__section--step-2">
                <p class="verstka_settings__label">
                    API key: <b><?php echo $settings['api_key']; ?></b><br>
                    Images source host: <b><?php echo $settings['images_source']; ?></b><br>
                    Images dir: <b><?php echo $settings['images_dir']; ?></b>
                </p>
                <input type="submit" name="reset" value="Reset"/>

                <?php if ($settings['dev_mode'] == 'on') { ?>
                    <input type="submit" name="dev_mode_off" value="Turn off developer mode"/>
                <?php } else { ?>
                    <input type="submit" name="dev_mode_on" value="Turn on developer mode"/>
                <?php } ?>
                <input type="text" hidden="hidden" name="dev_mode"
                       value="<?php echo ($settings['dev_mode'] == 'on') ? 'on' : 'off' ?>">
            </div>

        <?php } ?>
    </form>

    <?php
}

?>
