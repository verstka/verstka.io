<?php
	/*
	Plugin Name: Verstka
	Plugin URI: http://verstka.io
	Description: Powerfull design tool & WYSIWYG
	Version: 1.0
	Author: devnow
	Author URI: http://devnow.ru
	*/

	/*  Copyright 2016  devnow  (email: hello@devnow.ru)
		Free use is limited to 200,000 article views created by verstka.io per month,
		to obtain a license the company please contact our team via email hello@verstka.io.

	In case of violation of this agreement you lose the ability to edit your articles via verstka.io
	all maked by verstka.io content remain yours but in this case you will be able to edit them only manually.
	*/

	/*
		вставляет нужные поля в базу
	*/

	function wp_maybe_add_column ($table_name, $column_name, $create_ddl)
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
	function verstka_init ()
	{
		global $wpdb;
		$table_name = $wpdb->posts;
		$column_name_isvms = "post_isvms";
        $column_name_vms_mobile_mode = "vms_mobile_mode";
        $column_name_vms_content = "post_vms_content";
        $column_name_vms_content_mobile = "post_vms_content_mobile";

		$sqlQuery_add_isvms = "ALTER TABLE $table_name ADD COLUMN post_isvms BOOLEAN NOT NULL DEFAULT 0 AFTER post_date_gmt";
        $sqlQuery_add_vms_mobile_mode = "ALTER TABLE $table_name ADD COLUMN vms_mobile_mode smallint NOT NULL DEFAULT 0 AFTER post_isvms";
		$sqlQuery_add_vms_content = "ALTER TABLE $table_name ADD COLUMN post_vms_content longtext AFTER post_content";
        $sqlQuery_add_vms_content_mobile = "ALTER TABLE $table_name ADD COLUMN post_vms_content_mobile longtext AFTER post_vms_content";

		if (wp_maybe_add_column($table_name, $column_name_isvms, $sqlQuery_add_isvms)
            && wp_maybe_add_column($table_name, $column_name_vms_mobile_mode, $sqlQuery_add_vms_mobile_mode)
				&& wp_maybe_add_column($table_name, $column_name_vms_content, $sqlQuery_add_vms_content)
                    && wp_maybe_add_column($table_name, $column_name_vms_content_mobile, $sqlQuery_add_vms_content_mobile)
		) {
			//колонка добавлена или уже существует :)
		} else {
			die ('Error adding VMS cloumns into db');
		}
	}

	/*
		Сохраняет галочку is_vms
	*/

	add_action('save_post', 'save_isvms_post_state');
	function save_isvms_post_state ($post_id)
	{
		global $wpdb, $post; //post old state & db

		$is_vms = $_REQUEST['is_vms'];

		if (!empty($is_vms)) {
			$data = array(
					'post_isvms' => ($is_vms == 'true') ? 1 : 0
			);

			$wpdb->update($wpdb->posts, $data, array('id' => $post_id));
		}
	}

	/*
		Обрабатывает аякс на сохранение VMS-статьи
	*/

	add_action('wp_ajax_save_vms_article', 'save_vms_article');
	function save_vms_article ()
	{
		$ds = DIRECTORY_SEPARATOR;
		$uload_dir_rel = wp_upload_dir();
		$uload_dir_rel = parse_url($uload_dir_rel['baseurl']);
		$uload_dir_rel = $uload_dir_rel['path'];
		$uload_dir_abs = (substr(ABSPATH, -1) == "/" ? substr(ABSPATH, 0, -1) : ABSPATH) . $ds . 'wp-content' . $ds . 'uploads' . $ds . 'vms' . $ds;

		$uploaded = array();

		@mkdir($uload_dir_abs . $_POST['material_id'], 0777, true);

		foreach ($_FILES as $file_name => $file_data) {

			if ($file_name == 'index_html') {
				$source = file_get_contents($file_data['tmp_name']);
				continue;
			}
			if ($file_name == 'custom_fields_json') {
				$custom_fields = file_get_contents($file_data['tmp_name']);
				continue;
			}

			$file_name = strrev(implode(strrev('.'), explode(strrev('_'), strrev($file_name), 2)));

			$upd = move_uploaded_file($file_data['tmp_name'], $uload_dir_abs . $_POST['material_id'] . $ds . $file_name);
			$uploaded[$file_name] = $upd;
		}


		$source = str_replace('/vms_images/', $uload_dir_rel . '/vms/' . $_POST['material_id'] . '/', $source);

		$data = array(
				'post_isvms' => 1,
				'post_vms_content' => $source
		);
		global $wpdb;
		$rew = $wpdb->update($wpdb->posts, $data, array('id' => $_POST['material_id']));
		if (function_exists('wp_cache_clear_cache')) {
			wp_cache_clear_cache();
		}
		
		echo json_encode(array(
            'status' => 1,
			'content' => $source
		), JSON_NUMERIC_CHECK);

//		echo formJSON(1, 'saved', array('body' => $source));
		wp_die();
	}

	/*
		Обрабатывает аякс на сохранение VMS-статьи (мобильная версия)
	*/

	add_action('wp_ajax_save_vms_article_mobile', 'save_vms_article_mobile');
	function save_vms_article_mobile ()
	{
		$ds = DIRECTORY_SEPARATOR;
		$uload_dir_rel = wp_upload_dir();
		$uload_dir_rel = parse_url($uload_dir_rel['baseurl']);
		$uload_dir_rel = $uload_dir_rel['path'];
		$uload_dir_abs = (substr(ABSPATH, -1) == "/" ? substr(ABSPATH, 0, -1) : ABSPATH) . $ds . 'wp-content' . $ds . 'uploads' . $ds . 'vms' . $ds;

		$uploaded = array();

		@mkdir($uload_dir_abs . $_POST['material_id'] . '_m', 0777, true);

		foreach ($_FILES as $file_name => $file_data) {

			if ($file_name == 'index_html') {
				$source = file_get_contents($file_data['tmp_name']);
				continue;
			}
			if ($file_name == 'custom_fields_json') {
				$custom_fields = file_get_contents($file_data['tmp_name']);
				continue;
			}

			$file_name = strrev(implode(strrev('.'), explode(strrev('_'), strrev($file_name), 2)));

			$upd = move_uploaded_file($file_data['tmp_name'], $uload_dir_abs . $_POST['material_id'] . '_m' . $ds . $file_name);
			$uploaded[$file_name] = $upd;
		}


		$source = str_replace('/vms_images/', $uload_dir_rel . '/vms/' . $_POST['material_id'] . '_m' . '/', $source);

		$data = array(
				'post_isvms' => 1,
				'post_vms_content_mobile' => $source
		);
		global $wpdb;
		$rew = $wpdb->update($wpdb->posts, $data, array('id' => $_POST['material_id']));
		if (function_exists('wp_cache_clear_cache')) {
			wp_cache_clear_cache();
		}

		echo json_encode(array(
            'status' => 1,
			'content' => $source
		), JSON_NUMERIC_CHECK);

//		echo formJSON(1, 'saved', array('body' => $source));
		wp_die();
	}

    add_action('wp_ajax_save_vms_article_mode', 'save_vms_article_mode');
    function save_vms_article_mode ()
	{
		global $wpdb, $post; //post old state & db
		
		$vms_mobile_mode = (string) $_REQUEST['vms_mobile_mode'];
        $post_id = (integer) $_REQUEST['material_id'];

		if (!empty($vms_mobile_mode) || $vms_mobile_mode === '0') {
			$data = array(
					'vms_mobile_mode' => intval($vms_mobile_mode)
			);

			$wpdb->update($wpdb->posts, $data, array('id' => $post_id));
			
            echo json_encode(array(
                'status' => 1
            ), JSON_NUMERIC_CHECK);
		} else {
            echo json_encode(array(
                'status' => 0
            ), JSON_NUMERIC_CHECK);
        }
        wp_die();
	}

//	function formJSON ($res_code, $res_msg, $data = array())
//	{
//		return json_encode(array(
//				'rc' => $res_code,
//				'rm' => $res_msg,
//				'data' => $data
//		), JSON_NUMERIC_CHECK);
//	}

	/*
		Вставляет скрипты для подключения SDK
	*/

	add_action('admin_print_footer_scripts', 'my_admin_print_footer_scripts', 99);
	function my_admin_print_footer_scripts ()
	{
		$post = get_post();
		if (is_object($post)) {
			$vms_content = $post->post_vms_content;
			$is_dev_mode = get_option( 'dev_mode' ) ? true : false;
			
			?>
			<script>
				
				window.VerstkaSDK_beforeInit = function(util) {
					
					var settings = {
						'api_key': '<?php echo get_option( 'api_key' ); ?>',
						'images_source': '<?php echo get_option( 'images_source' ); ?>',
						'name': 'wp_<?php echo $post->ID; ?>',
						'user_id': '<?php echo $post->post_author; ?>',
						'material_id': '<?php echo $post->ID; ?>',
						'is_active': <?php echo $post->post_isvms ? 1 : 0; ?> == 1,
						'mobile_mode': '<?php echo $post->vms_mobile_mode; ?>', //0=none, 1=auto, 2=custom
						'materials': {
							'desktop': '#verstka_desktop',
							'mobile': '#verstka_mobile'
						},
						'actions': {
							'material_desktop': {
								'ajax': {
									'url': window.ajaxurl,
									'data': {
										'action': 'save_vms_article'
									}
								}
							},
							'material_mobile': {
								'ajax': {
									'url': window.ajaxurl,
									'data': {
										'action': 'save_vms_article_mobile'
									}
								}
							},
							'mobile_mode': {
								'ajax': {
									'url': window.ajaxurl,
									'data': {
										'action': 'save_vms_article_mode'
									},
									'value_as': 'vms_mobile_mode'
								}
							},
							'is_active': {
								'method': 'verstkaActionHandler'
							}
						}
					};
					
					var xmp = util.parseHtml('<xmp name="verstka" hidden>' + JSON.stringify(settings) + '</xmp>');
					
					var textarea_desktop = util.parseHtml('<textarea id="verstka_desktop" hidden><?php echo urlencode( $post->post_vms_content); ?></textarea>');
					
					var textarea_mobile = util.parseHtml('<textarea id="verstka_mobile" hidden><?php echo urlencode( $post->post_vms_content_mobile); ?></textarea>');
					
					var post_title;
					
					var is_vms_input = util.parseHtml( '<input type="text" name="is_vms" hidden/>' );
					
					if (document.getElementById('post-body-content') !== null) {
					
						post_title = document.getElementById('titlediv');
						
						util.after( post_title, xmp );
						util.after( post_title, textarea_desktop );
						util.after( post_title, textarea_mobile );
						util.after( post_title, is_vms_input );
						
						window.verstka_interface = {
							elements: {
								xmp: xmp,
								textarea_desktop: textarea_desktop,
								textarea_mobile: textarea_mobile,
								is_vms_input: is_vms_input,
								default_editor: util.find('#postdivrich')
							}
						};
						
					}
					
				};
				
				window.verstkaActionHandler = function(action, current, prev, callback) {
				
					switch (action) {
						case 'is_active':
							verstka_interface.elements.default_editor.style.display = current ? 'none' : 'block';
							verstka_interface.elements.is_vms_input.value = current ? 'true' : 'false';
							
							if ( !current ) {
								// Fixes default editor
								window.dispatchEvent( new Event( 'resize' ) );
							}
							
							callback( { status: 1 } );
							
							break;
					}
				
				};
				
			</script>
			
			<script src = "//<?php echo $is_dev_mode ? 'dev' : 'go'; ?>.verstka.io/sdk/v2.js"></script>
			<?php
		}
	}

    add_filter('the_content', 'apply_vms_content', 1);
    function apply_vms_content ($content)
    {
        $post = get_post();

        if ( ($post->post_isvms == 1) && ($content == $post->post_content) ) {
            if (wp_is_mobile() && ($post->vms_mobile_mode == 2) && !empty($post->post_vms_content_mobile)) {
                   $content = $post->post_vms_content_mobile;
               } else {
                   $content = $post->post_vms_content;
               }
           }
           
        remove_filter( 'the_content', 'wpautop' );
        remove_filter( 'the_excerpt', 'wpautop' );
        remove_filter( 'the_content', 'wptexturize' );

        return $content;
    }

	add_action('wp_head', 'add_this_script_footer');
	function add_this_script_footer ()
	{ 
		$is_dev_mode = get_option( 'dev_mode' ) ? true : false;
		
		$post = get_post();
		
		?>
			
			<?php if ($post->vms_mobile_mode == 0 || $post->vms_mobile_mode == 2) {  // Mobile: off || custom ?>
			
				<script type = "text/javascript">
					window.onVMSAPIReady = function (api) {
						api.Article.enable({
							display_mode: 'desktop'
						});
					};
				</script>
				
			<?php } ?>
			
			<?php if ($post->vms_mobile_mode == 1) {  // Mobile: auto ?>
			
				<script type = "text/javascript">
					window.onVMSAPIReady = function (api) {
						api.Article.enable({
							auto_mobile_detect: true,
							mobile_max_width: 767
						});
					};
				</script>
				
			<?php } ?>
			
			<script src="//<?php echo $is_dev_mode ? 'dev' : 'go'; ?>.verstka.io/api.js" async type="text/javascript"></script>
			
			<style>
				[data-vms-version="1"] {
					visibility: hidden;
				}
				[data-vms-version="1"].body--inited {
					visibility: visible;
				}
			</style>
		
		<?php
		
	}

//	add_filter('manage_edit-post_columns', 'add_is_vms_column', 4);
//	function add_is_vms_column ($columns)
//	{
//		$result = array();
//		foreach ($columns as $name => $value) {
//			if ($name == 'title') {
//				$result['is_vms'] = 'VMS';
//			}
//			$result[$name] = $value;
//		}
//		return $result;
//	}

	add_filter('manage_post_posts_custom_column', 'fill_is_vms_column', 5, 2); // wp-admin/includes/class-wp-posts-list-table.php
	function fill_is_vms_column ($column_name, $post_id)
	{
		if ($column_name != 'is_vms')
			return;
		$post = get_post($post_id);
		if ($post->post_isvms == 1) {
			echo '&#9733';
		} else {
			echo '&#9734';
		}
	}

	add_filter('manage_edit-post_sortable_columns', 'add_is_vms_sortable_column');
	function add_is_vms_sortable_column ($sortable_columns)
	{
		$sortable_columns['is_vms'] = 'is_vms_is_vms';
		return $sortable_columns;
	}
	
	add_action('pre_get_posts', 'add_column_is_vms_request');
	function add_column_is_vms_request($query) {
	 if (!is_admin())
	  return;
	 if ($query->get('orderby') == 'is_vms_is_vms') {
	  $query->set('orderby', 'post_isvms');
	 }
	}

	add_action('admin_head', 'add_is_vms_column_css');
	function add_is_vms_column_css ()
	{
		echo '<style type="text/css">.column-is_vms{width:3%;}</style>';
	}
	
	/*===============*/
	/* settings page */
	/*===============*/
	
	function addOption( $name, $value ) {
		if ( get_option($name) ) {
			update_option($name, $value);
		} else {
			$deprecated=' ';
			$autoload='no';
			add_option($name, $value, $deprecated, $autoload);
		}
	}
	
	add_action('admin_menu', 'mt_add_pages');
	function mt_add_pages() {
	    add_menu_page('Verstka', 'Verstka', 'edit_pages', __FILE__, 'verstka_settings_page');
	}

	add_action( 'admin_enqueue_scripts', 'wpdocs_register_verstka_styles' );
	function wpdocs_register_verstka_styles() {
	    wp_register_style( 'verstka_settings_styles', plugins_url ( 'styles/settings.css', __FILE__ ) );
	    wp_enqueue_style( 'verstka_settings_styles' );
	}
	
	function verstka_settings_page()
	{
		$hidden_field_name = 'verstka_settings_hidden';
		$settings_names = array(
			'email',
			'api_key',
			'images_source',
			'dev_mode'
		);
		
		if ( $_POST[ $hidden_field_name ] == 'true' ) {
			if ( $_POST[ 'confirm' ] ) {
				foreach ( $settings_names as $name ) {
					if ( !empty( $_POST[ $name ] ) ) {
						update_option( $name, $_POST[ $name ] );
					}
				}
			} else if ( $_POST[ 'reset' ] ) {
				foreach ( $settings_names as $name ) {
					delete_option( $name );
				}
			} else if ( $_POST[ 'dev_mode_off' ] ) {
				update_option( 'dev_mode', 'off' );
			} else if ( $_POST[ 'dev_mode_on' ] ) {
				update_option( 'dev_mode', 'on' );
			}
//			if ( $_POST[ 'submit' ] != 'Reset' ) {
//				foreach ( $settings_names as $name ) {
//					if ( !empty( $_POST[ $name ] ) ) {
//						update_option( $name, $_POST[ $name ] );
//					}
//				}
//			} else {
//				foreach ( $settings_names as $name ) {
//					delete_option( $name );
//				}
//			}
		}
		
		$settings = array();
		foreach ( $settings_names as $name ) {
			$settings[ $name ] = get_option( $name );
		}
		?>
		
		<form name="verstka_settings" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER[ 'REQUEST_URI' ] ); ?>">
			<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="true">
			
			<?php if ( !$settings[ 'email' ] && false ) { ?>
			
				<div class="verstka_settings__section verstka_settings__section--step-1">
					<p class="verstka_settings__label">Where do we send an activation link?</p>
					<input class="verstka_settings__input" type="text" name="email" value="<?php echo get_option( 'admin_email' ); ?>">
					<input type="submit" name="activate" value="Get activation link"/>
				</div>
				
			<?php } elseif ( !$settings[ 'api_key' ] ) { ?>
				<div class="verstka_settings__section verstka_settings__section--step-2">
					<p class="verstka_settings__label">
						<span>API key:</span>
						<input class="verstka_settings__input" type="text" name="api_key" value="197b094891a44993b6be96edbcdb9dbc">
					</p>
					
					<p class="verstka_settings__label">
						<span>Images source:</span>
						<input class="verstka_settings__input" type="text" name="images_source" value="//<?php echo $_SERVER[ 'HTTP_HOST' ]; ?>">
					</p>
					
					<input type="submit" name="confirm" value="Confirm"/>
				</div>
				
			<?php } else { ?>
				
				<div class="verstka_settings__section verstka_settings__section--step-2">
					<p class="verstka_settings__label">
						API key: <b><?php echo $settings[ 'api_key' ]; ?></b><br>
						Images source: <b><?php echo $settings[ 'images_source' ]; ?></b>
					</p>
					<input type="submit" name="reset" value="Reset"/>
					
					<?php if ( $settings[ 'dev_mode' ] == 'on' ) { ?>
						<input type="submit" name="dev_mode_off" value="Turn off developer mode"/>
					<?php } else { ?>
						<input type="submit" name="dev_mode_on" value="Turn on developer mode"/>
					<?php } ?>
					<input type="text" hidden="hidden" name="dev_mode" value="<?php echo ( $settings[ 'dev_mode' ] == 'on' ) ? 'on' : 'off' ?>">
				</div>
			
			<?php } ?>
		</form>
	
		<?php
	}
?>
