<?php

	namespace devnow;

	class VMSClient
	{
		public $ds = DIRECTORY_SEPARATOR;
		private $config;
		private $default_vms_url = 'http://verstka.io/1/'; //lfjkghsldfkjgslfdkjg REVIEW CODE HERE CHECKS MUST BE DYNAMIC
		private $messages = array(
				'noConfig' => 'Config in this file (%file_name%) is not available, please restore access to this file, specify the other (as agrument of vms constructor), or click on the link for <a href="%registration_link%" >registration process</a>.',
				'noListener' => 'Url (%my_listener_uri%) must be accessible from the outside to save your articles. We understand the importance of maintaining the security and confidentiality. You can be sure to inspect the code in detail<br>',
				'registerForm' => '
					<!DOCTYPE HTML>
					<html>
						<head>
							<meta charset="utf-8">
							<title>VMS Registration</title>
						</head>
						<body>
							<p>Your web project root folder: "<b>%web_root%</b>" (connect Your system administrator for changes)<br>
							Your local VMS config file: "<b>%vms_config%</b>" (you can change it in code, as agrument of vms constructor)</p>

							<form action="%reg_action_url%" method="post" accept-charset="utf-8">
							<p><b>Check the following settings before registration</b></p>
							<label>Enter Your email to get api_key:</label>
							<input type="text" name="email" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}$" size="40"><br>
							<label>Your local VMS listener uri (must be accessible externally for article save):</label>
							<input type="text" name="listener" size="40" value="%my_listener%">
							%localAuth%
							<input type="hidden" name="server_ip" size="40" value="%server_ip%">
							<input type="hidden" name="office_ip" size="40" value="%client_ip%">
							<p><input type="submit" value="Begin registration"></p>
							</form>
						</body>
					</html>',
				'folderReadonly' => 'make sure You have %folder_path% folder and it is writable for php, then refresh this tab',
				'configForm' => '
			<!DOCTYPE HTML>
			<html>
			 <head>
			  <meta charset="utf-8">
			  <title>VMS Configuration</title>
			 </head>
			 <body>

			 <form action="%reg_action_url%" method="post" accept-charset="utf-8">
			  <p><b>Configure basic params:</b><br>
			  Your web project root folder: "<b>%web_root%</b>" <br>
			  Your local VMS config file: "<b>%vms_config%</b>" <br>
			  Your local VMS listener uri: "<b>%listener%</b>" (must be accessible externally for article save):</p>
			  <label>Enter temp folder for compressed articles:</label>
			  <input type="text" name="temp_storage" size="1024" value="%temp_storage%">
			  <label>And image storage folder:</label>
			  <input type="text" name="abs_img_path" size="1024" value="%abs_img_path%">
			  <input type="hidden" name="api_key" size="40" value="%api_key%">
			  <input type="hidden" name="secret" size="40" value="%secret%">
			  <p><input type="submit" value="Save settings"></p>
			 </form>
			 </body>
			</html>',
				'localAuth' => '<br><br>Enter Your site http auth (VMS listener)<br>
								<label>username:</label>
								<input type="text" name="http_auth" size="40" value="%http_auth%">
								<br><label>password:</label>
								<input type="text" name="http_pass" size="40" value="%http_pass%">	',
				'needCurl' => 'You need curl, or at least allow_url_fopen to make requests to remote systems',
				'notOfficeIp' => 'You must be in Your office to success this kind of operation',
				'checkConfig' => 'Check your VMS config in "%config_file_abs%" %error%',
				'contactAdmin' => 'Please contact your system administrator %error%',
				'errorDuringRequestVms' => 'You\'re lucky we do something new! Error on remote side: %error%',
				'pleaseFollowEmailLink' => 'To proceed, please go to the link of the letter'
		);
		private $vms_ips = array();
		private $config_abs = null;

		function __construct ($config_file_abs)
		{
			$dvu = parse_url($this->default_vms_url);
			$this->vms_ips = gethostbynamel($dvu['host']);

			if ($_REQUEST['mode'] == 'check') {
				if ($_SERVER['SERVER_ADDR'] != $_SERVER['REMOTE_ADDR']) {
					$dvu = parse_url($this->default_vms_url);
					if (!in_array($_SERVER['REMOTE_ADDR'], $this->vms_ips)) {
						header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
					} else {
						die($this->formJSON(1, 'success'));
					}
				} else {
					die($this->formJSON(1, 'success'));
				}
			}

			if (empty($config_file_abs)) {
				$config_file_abs = $_SERVER['DOCUMENT_ROOT'] . '/vms/config.json';
			}

			if (!is_readable($config_file_abs) || ($_GET['register'] == 'config')) {    // All further registration action is possible only when there is no config

				switch ($_GET['register']) {

					case 'email':

						if ($this->checkListener($this->getSelfUrl(), $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {

							if (!empty($_SERVER['PHP_AUTH_PW'])) {
								$localAuth_questions = $this->render('localAuth', array(
										'http_auth' => $_SERVER['PHP_AUTH_USER'],
										'http_pass' => $_SERVER['PHP_AUTH_PW'],
								));
							} else {
								$localAuth_questions = '';
							}

							die($this->render('registerForm', array(
									'web_root' => $_SERVER['DOCUMENT_ROOT'],
									'vms_config' => $config_file_abs,
									'my_listener' => $this->getSelfUrl(),
									'localAuth' => $localAuth_questions,
									'reg_action_url' => trim($this->default_vms_url, '/') . '/signup',
									'server_ip' => $_SERVER['SERVER_ADDR'],
									'client_ip' => $_SERVER['REMOTE_ADDR'],
							)));
						} else {
							die($this->render('noListener'));
						}

					case 'config':

						if (!empty($_REQUEST['api_key']) and !empty($_REQUEST['secret'])) {
							$default_config_json = $this->request(trim($this->default_vms_url, '/') . '/signup', array(
									'login' => $_REQUEST['api_key'],
									'password' => $_REQUEST['secret']));
							$default_config = json_decode($default_config_json, true);
							if ($default_config['rc'] !== 1) {
								die($this->render('errorDuringRequestVms', array(
										'error' => print_r($default_config_json, true))));
							}
							@mkdir(dirname($config_file_abs), 0774, true);
							if (!is_writable(dirname($config_file_abs))) {
								die($this->render('folderReadonly', array('folder_path' => dirname($config_file_abs))));
							} else {

								if (file_put_contents($config_file_abs, $this->prettyPrint($default_config['data']))) {
									header('Location: ' . trim($default_config['static']['my_listener_uri'], '/') . '?register=config');
								} else {
									die('sdfsdf');
								}
							}
						} elseif (is_readable($config_file_abs)) {

							$default_config = json_decode(file_get_contents($config_file_abs), true);

							die($this->render('configForm', array(
									'web_root' => $_SERVER['DOCUMENT_ROOT'],
									'vms_config' => $config_file_abs,
									'reg_action_url' => trim($default_config['static']['my_listener_uri'], '/') . '?register=done',
									'listener' => $default_config['static']['my_listener_uri'],

									'temp_storage' => dirname($config_file_abs) . DIRECTORY_SEPARATOR . 'temp',
									'abs_img_path' => $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . trim($default_config['static']['dirs']['rel_img_path'], DIRECTORY_SEPARATOR),
									'api_key' => $default_config['static']['vms_auth']['api_key'],
									'secret' => $default_config['static']['vms_auth']['secret']
							)));
						} else {
							die($this->render('pleaseFollowEmailLink'));
						}

						break;

					default:
						die($this->render('noConfig', array(
								'file_name' => $config_file_abs,
								'registration_link' => $this->getSelfUrl(array('register' => 'email'))
						)));
				}
			} else {

				$this->config_abs = $config_file_abs;
				$this->config = json_decode(file_get_contents($config_file_abs), true);
			}

			if ($_GET['register'] == 'done') {

				if (($this->config['static']['vms_auth']['api_key'] == $_POST['api_key'])
						&& ($this->config['static']['vms_auth']['secret'] == $_POST['secret'])
						&& (in_array($_SERVER['REMOTE_ADDR'], $this->config['static']['office_ip']))
				) {
					$this->config['static']['dirs']['temp_storage'] = $_POST['temp_storage'];
					$this->config['static']['dirs']['abs_img_path'] = $_POST['abs_img_path'];

					mkdir($this->config['static']['dirs']['temp_storage'], 0774, true);
					if (!is_writable($this->config['static']['dirs']['temp_storage'])) {
						die($this->render('folderReadonly', array('folder_path' => $this->config['static']['dirs']['temp_storage'])));
					}
					@mkdir($this->config['static']['dirs']['abs_img_path'], 0777, true);
					if (!is_writable($this->config['static']['dirs']['abs_img_path'])) {
						die($this->render('folderReadonly', array('folder_path' => $this->config['static']['dirs']['abs_img_path'])));
					}

					$this->config['static']['dirs']['rel_img_path'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $_POST['abs_img_path']);
					file_put_contents($config_file_abs, $this->prettyPrint(json_encode($this->config, JSON_NUMERIC_CHECK)));
					header('Location: ' . $this->config['static']['my_listener_uri']);
				} else {
					header('WWW-Authenticate: Basic realm="' . $this->render('notOfficeIp') . '"');
					header($_SERVER['SERVER_PROTOCOL'] . ' 401 Unauthorized');
				}
			}


			if (empty($this->config['static']['dirs']['temp_storage'])
					|| empty($this->config['static']['dirs']['abs_img_path'])
					|| empty($this->config['static']['vms_auth']['api_key'])
					|| empty($this->config['static']['vms_auth']['secret'])
					|| empty($this->config['static']['my_listener_uri'])
					|| empty($this->config['upgradeable'])
			) {

				if (in_array($_SERVER['REMOTE_ADDR'], $this->config['static']['office_ip'])) {
					die($this->render('checkConfig', array('config_file_abs' => $config_file_abs, 'error' => 'dirs, vms_auth, listener or upgradeable section are wrong')));
				} else {
					die($this->render('contactAdmin', array('error' => '"Wrong VMS config"')));
				}
			}
			if (empty($this->config['static']['office_ip'])) {
				die($this->render('contactAdmin', array('error' => '"Wrong VMS config:empty office ip"')));
			}
			if (!empty($this->config['upgradeable']['vms_listener_uri'])) {
				$uvu = parse_url('http://'.$this->config['upgradeable']['vms_listener_uri']);
				$this->vms_ips = array_merge($this->vms_ips, gethostbynamel($uvu['host']));
			}
		}

		function formJSON ($res_code, $res_msg, $data = array())
		{
			return json_encode(array(
					'rc' => $res_code,
					'rm' => $res_msg,
					'data' => $data
			), JSON_NUMERIC_CHECK);
		}

		function checkListener ($url, $http_user, $http_pw)
		{
			$json = $this->request($url, array(
					'POST' => array(
							'mode' => 'check',
					),
					'login' => $http_user,
					'password' => $http_pw));

			$responce = json_decode($json, true);

			if (empty($responce) || $responce['rc'] != 1) {
				die($this->render('noListener', array(
								'my_listener_uri' => $url)) . print_r($json, true)
				);
			} else {
				return true;
			}
		}

		function request ($url, $params)
		{
			if (function_exists('curl_version')) {

				if (!empty($params['download_to'])) {
					set_time_limit(0);
					$fp = fopen($params['download_to'], 'w+');
				}

				$ch = curl_init();

				if (!empty($params['upload_from'])) {
					set_time_limit(0);
					curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data'));
					$params['POST']['file'] = '@' . $params['upload_from'];
				}

				curl_setopt($ch, CURLOPT_TIMEOUT, 20);
				if (!empty($params['login']) && !empty($params['password'])) {
					curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
					curl_setopt($ch, CURLOPT_USERPWD, $params['login'] . ':' . $params['password']);
				}

				if (!empty($fp)) {
					curl_setopt($ch, CURLOPT_FILE, $fp);
				} else {
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				}

				if (!empty($params['GET'])) {
					curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params['GET']));
				} else {
					curl_setopt($ch, CURLOPT_URL, $url);
				}

				if (!empty($params['POST'])) {
					curl_setopt($ch, CURLOPT_POST, 1);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $params['POST']);
				}

				curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
				curl_setopt($ch, CURLOPT_FAILONERROR, 1);

				$result = curl_exec($ch);

				if (0 == curl_errno($ch)) {
					curl_close($ch);
					if (!empty($fp)) {
						fclose($fp);
					}
					return $result;
				} else {
					$result = array('request' => array('url' => $url, 'params' => $params),
							'error' => array('code' => curl_errno($ch), 'error' => curl_error($ch)));
					curl_close($ch);
					if (!empty($fp)) {
						fclose($fp);
					}
					return $result;
				}
			} else {
				die($this->render('needCurl'));
			}
		}

		function render ($message_name, $params = array())
		{
			return str_replace(array_map(function ($key) { return '%' . $key . '%'; }, array_keys($params)), array_values($params), $this->messages[$message_name]);
		}

		function getSelfUrl ($get_adding = null)
		{
			$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http';
			$url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['DOCUMENT_URI'];
			if (!empty($get_adding)) {
				return $url . '?' . http_build_query($get_adding);
			} else {
				return $url;
			}
		}

		function prettyPrint ($json)
		{
			$result = '';
			$level = 0;
			$in_quotes = false;
			$in_escape = false;
			$ends_line_level = NULL;
			$json_length = strlen($json);

			for ($i = 0; $i < $json_length; $i++) {
				$char = $json[$i];
				$new_line_level = NULL;
				$post = '';
				if ($ends_line_level !== NULL) {
					$new_line_level = $ends_line_level;
					$ends_line_level = NULL;
				}
				if ($in_escape) {
					$in_escape = false;
				} else if ($char === '"') {
					$in_quotes = !$in_quotes;
				} else if (!$in_quotes) {
					switch ($char) {
						case '}':
						case ']':
							$level--;
							$ends_line_level = NULL;
							$new_line_level = $level;
							break;

						case '{':
						case '[':
							$level++;
						case ',':
							$ends_line_level = $level;
							break;

						case ':':
							$post = ' ';
							break;

						case ' ':
						case "\t":
						case "\n":
						case "\r":
							$char = '';
							$ends_line_level = $new_line_level;
							$new_line_level = NULL;
							break;
					}
				} else if ($char === '\\') {
					$in_escape = true;
				}
				if ($new_line_level !== NULL) {
					$result .= "\n" . str_repeat("\t", $new_line_level);
				}
				$result .= $char . $post;
			}

			return $result;
		}

		function get_listener ($callback_function = null)
		{ // Function for save, arguments [$material_id, $html, $user_id, $session_id, $custom_fields]

			if (!empty($callback_function)) {
				if (!is_callable($callback_function) && !(is_string($callback_function) && function_exists($callback_function))) {
					die('if $permanent_callback_function is set it must be a function or function name from global namespace');
				}
			}

			if (!empty($_REQUEST['save'])) {

				$session_file_abs = $this->normalize_path($this->config['static']['dirs']['temp_storage']) . $this->ds . $this->escapeFileName($_REQUEST['save']) . '.zip';
				if (is_readable($session_file_abs) && in_array($_SERVER['REMOTE_ADDR'], $this->vms_ips)) {

					$vms_listener_url = trim($this->config['upgradeable']['vms_listener_uri'], '/') . '/listener';
					copy($session_file_abs, $session_file_abs . '.bak');

					$result = $this->request($vms_listener_url, array(
							'POST' => array(
									'mode' => 'deliver',
									'session' => $_REQUEST['save']
							),
							'download_to' => $session_file_abs,
							'login' => $this->config['vms_auth']['api_key'],
							'password' => $this->config['vms_auth']['secret'],
					));

					if ($result === true) {

						$open = $this->open($session_file_abs);
						file_put_contents($this->normalize_path($this->config['static']['dirs']['temp_storage']) . $this->ds . 'last_save_debug.log', print_r($open, true));

						if ((!empty($open['material_id'])) && (!empty($open['html']))) {

							if (is_readable($session_file_abs . '.bak')) {
								unlink($session_file_abs . '.bak');
							}

							if (!empty($callback_function)) {
								$ufr = call_user_func($callback_function, $open['material_id'], $open['html'], $open['user_id'], $_REQUEST['save'], $open['custom_fields']);
								if ($ufr === true) {
									die($this->formJSON(1, 'success'));
								} else {
									die(print_r($ufr, true));
								}
							} else {
								echo $this->formJSON(1, 'success');
								return array($open['material_id'], $open['html'], $open['user_id'], $_REQUEST['save'], $open['custom_fields']);
							}

						} else {
							unlink($session_file_abs);
							copy($session_file_abs . '.bak', $session_file_abs);
							unlink($session_file_abs . '.bak');
							header($_SERVER['SERVER_PROTOCOL'] . ' 415 Unsupported Media Type');
							die($this->formJSON(0, 'bad data'));
						}

					} else {
						die($this->formJSON(0, 'fail', $result));
					}

					header($_SERVER['SERVER_PROTOCOL'] . ' 401 Unauthorized');
				} else {
					header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
				}
			} else {
				return null; //skip saving processes, run code to authorize and than initialize edit
			}
		}

		public function normalize_path ($path)
		{
			return $this->ds . trim(str_replace(array('/', '\\'), $this->ds, $path), $this->ds);
		}

		function escapeFileName ($filename)
		{
			return str_replace(array('\\', '..', $this->ds), '', $filename);
		}

		function open ($zip_file_abs)
		{
			if (is_file($zip_file_abs) && is_readable($zip_file_abs)) {

				$zip = new \ZipArchive;
				if ($zip->open($zip_file_abs) === TRUE) {

					$archive_params = json_decode($zip->getArchiveComment(), true);
					$html = $zip->getFromName('index.html');

					foreach ($archive_params['images']['actual'] as $key => &$image) {

						$image_abs = $this->normalize_path($this->config['static']['dirs']['abs_img_path']) . $this->ds . $archive_params['material_id'] . $this->ds . basename($image['zip_rel']);
						$image_rel = $this->normalize_path($this->config['static']['dirs']['rel_img_path']) . $this->ds . $archive_params['material_id'] . $this->ds . basename($image['zip_rel']);

						if (empty($image['client_rel']) || !is_readable($image_abs)) {
							$image_ext = pathinfo($image_abs, PATHINFO_EXTENSION);
							$exif = exif_imagetype('zip://' . $zip_file_abs . '#' . $image['zip_rel']);
							$imageinfo = getimagesize('zip://' . $zip_file_abs . '#' . $image['zip_rel']);
							$imageinfo['mime'] = array_map('trim', explode('/', $imageinfo['mime']));
							if (in_array($exif, array(1, 2, 3))
									&& ($imageinfo['mime'][0] == 'image')
									&& ($imageinfo[0] > 0)
									&& ($imageinfo[1] > 0)
									&& in_array($image_ext, array('png', 'jpg', 'jpeg', 'gif', 'svg'))
							) {
								@mkdir(dirname($image_abs), 0777, true);
								copy('zip://' . $zip_file_abs . '#' . $image['zip_rel'], $image_abs);
								$image['client_rel'] = $image_rel;
							}
						}
						$this->writeLog('E before', $html);
						$html = str_replace(trim($image['zip_rel'], '/'), $image_rel, $html);
						$this->writeLog('E after', $html);
					}

					foreach ($archive_params['images']['deleted'] as $image_num => $dimage) {
						if ($this->config['static']['images']['purge_old_images'] === true) {
							$this->writeLog('F before', $html);
							$abs_img = $this->normalize_path($this->config['static']['dirs']['abs_img_path']) . $this->ds . str_replace($this->normalize_path($this->config['static']['dirs']['rel_img_path']) . $this->ds, '', $dimage['client_rel']);
							$this->writeLog('F after', $html);
							unlink($abs_img);
						}
						unset($archive_params['images']['deleted'][$image_num]);
					}

					$this->config['upgradeable'] = $archive_params['upgradeable'];
					file_put_contents($this->config_abs, $this->prettyPrint(json_encode($this->config)));

					$zip->setArchiveComment(json_encode($archive_params));
					
					$zip->close();

					$result = array(
							'custom_fields' => $archive_params['custom_fields'],
							'material_id' => $archive_params['material_id'],
							'user_id' => $archive_params['versions']['current']['user_id'],
							'html' => str_replace($this->config['upgradeable']['vms_activator'], '', $html)
					);

					if (defined('debug')) {
						print_r($result);
						die;
					} else {
						return $result;
					}
				} else {
					return false;
				}
			}
			return null;
		}

		function edit ($material_id, $html, $user_id, $custom_fields = null)
		{

			if ($this->config['static']['max_versions_count'] < 1) {
				$this->config['static']['max_versions_count'] = 1;
			}

			if (empty($html)) {
				$html= $this->config['upgradeable']['default_template'];
			}

			$sign = md5($html);
			$session_id = $this->get_session($material_id, $user_id);

			if (!empty($session_id)) {

				$zip_file_abs = $this->normalize_path($this->config['static']['dirs']['temp_storage']) . $this->ds . $session_id . '.zip';
//				unlink($zip_file_abs); //FORCE CREATE NEW ARCHIVE EVERY TIME YOU EDIT

				$zip = new \ZipArchive();
				if (is_readable($zip_file_abs)) {
					if (is_writable($zip_file_abs)) {
						$opened = $zip->open($zip_file_abs, \ZipArchive::CHECKCONS);
						if ($opened !== true) {
							die($this->render('contactAdmin', array('error' => 'broken zip archive ' . $zip_file_abs)));
						}
						$archive_params = json_decode($zip->getArchiveComment(), true);

						if ($sign != $archive_params['versions']['current']['sign']) { //update file

							$zip->renameName('index.html', $archive_params['versions']['current']['sign'] . '.html');

							if (empty($archive_params['versions'][$archive_params['versions']['current']['sign']])) {
								$archive_params['versions'][$archive_params['versions']['current']['sign']] = $archive_params['versions']['current'];
							} else {
								$archive_params['versions']['current']['udate'] = $archive_params['versions']['current']['cdate'];
								$archive_params['versions']['current']['cdate'] = $archive_params['versions'][$archive_params['versions']['current']['sign']]['cdate'];
								$archive_params['versions'][$archive_params['versions']['current']['sign']] = $archive_params['versions']['current'];
							}
							unset($archive_params['versions'][$archive_params['versions']['current']['sign']]['sign']);
							if (!empty($archive_params['versions'][$sign])) {
								$archive_params['versions']['current'] = array(
										'sign' => $sign,
										'cdate' => gmdate('U'),
										'user_id' => $user_id,
										'udate' => $archive_params['versions'][$sign]['cdate'],
								);
								unset($archive_params['versions'][$sign]);
							} else {
								$archive_params['versions']['current'] = array(
										'sign' => $sign,
										'cdate' => gmdate('U'),
										'user_id' => $user_id,
								);
							}

							$new_images = $this->get_images($html, $this->config['upgradeable']['image_parse']);
							$old_images = print_r($archive_params['images']['actual'], true); //for compatibility with other languages, serialize is not appropriate, json escapes path...

							foreach ($new_images as $image_rel) {


								$this->writeLog('G before', $html);
								$image_abs = str_replace('/' . trim($this->config['static']['dirs']['rel_img_path'], '/'), $this->normalize_path($this->config['static']['dirs']['abs_img_path']), $image_rel);
								$this->writeLog('G after', $html);
								$zip_image_rel = '/images/' . md5($image_abs) . '.' . mb_strtolower(substr(strrchr($image_rel, '.'), 1));

								if (strpos($old_images, $image_rel) === false) {
									if (is_readable($image_abs)) {
										$zip->addFile($image_abs, $zip_image_rel);
										$archive_params['images']['actual'][] = array('client_rel' => $image_rel, 'zip_rel' => $zip_image_rel);
										$this->writeLog('H before', $html);
										$html = str_replace($image_rel, trim($zip_image_rel, '/'), $html);
										$this->writeLog('H after', $html);
									}
								}
							}

							foreach ($archive_params['images']['actual'] as $image) {
								$this->writeLog('J before', $html);
								$html = str_replace($image['client_rel'], trim($image['zip_rel'], '/'), $html);
								$this->writeLog('J after', $html);
							}

							while (count($archive_params['versions']) > $this->config['static']['max_versions_count']) {
								$early_version['cdate'] = $archive_params['versions']['current']['cdate'];
								$early_version['sign'] = $archive_params['versions']['current']['sign'];
								foreach ($archive_params['versions'] as $sign => $data) {
									if ($sign != 'current') {
										if ($data['cdate'] < $early_version['cdate']) {
											$early_version['cdate'] = $data['cdate'];
											$early_version['sign'] = $sign;
										}
									}
								}

								$zip->deleteName($early_version['sign'] . '.html');
								unset($archive_params['versions'][$early_version['sign']]);
							}

							$html_buff = $html . PHP_EOL;
							foreach ($archive_params['versions'] as $sign => $data) {
								if ($sign != 'current') {
									$html_buff .= $zip->getFromName($sign . '.html') . PHP_EOL;
								}
							}

							foreach ($archive_params['images']['actual'] as $key => $image) {
								if (strpos($html_buff, trim($image['zip_rel'], $this->ds)) === false) {
									$zip->deleteName($image['zip_rel']);
									unset($archive_params['images']['actual'][$key]);
								}
							}
							$archive_params['images']['actual'] = array_values($archive_params['images']['actual']);

						} else { //you can add a check to see if the original file is deleted

							$archive_params['custom_fields'] = $custom_fields;

							foreach ($archive_params['images']['actual'] as $image) {
								$this->writeLog('K before', $html);
								$html = str_replace($image['client_rel'], trim($image['zip_rel'], '/'), $html);
								$this->writeLog('K after', $html);
							}
						}
					} else {
						die($this->render('contactAdmin', array('error' => 'insufficient privileges on zip archive ' . $zip_file_abs)));
					}
				} else { //create new archive

					$opened = $zip->open($zip_file_abs, \ZipArchive::CREATE);
					if ($opened !== true) {
						die($this->render('contactAdmin', array('error' => 'can\'t create zip archive ' . $zip_file_abs . ' ' . $zip->getStatusString())));
					}

					$images = $this->get_images($html, $this->config['upgradeable']['image_parse']);
					$actual_images = array();

					foreach ($images as $image_rel) {
						$image_abs = str_replace('/' . trim($this->config['static']['dirs']['rel_img_path'], '/'), $this->normalize_path($this->config['static']['dirs']['abs_img_path']), $image_rel);
						if (is_readable($image_abs)) {
							$zip_image_rel = '/images/' . md5($image_abs) . '.' . mb_strtolower(substr(strrchr($image_rel, '.'), 1));
							$html = str_replace($image_rel, trim($zip_image_rel, '/'), $html);
							$zip->addFile($image_abs, $zip_image_rel);
							$actual_images[] = array('client_rel' => $image_rel, 'zip_rel' => $zip_image_rel);
						}
					}

					$archive_params =
							array(
									'custom_fields' => $custom_fields,
									'material_id' => $material_id,
									'versions' => array(
											'current' => array(
													'sign' => $sign,
													'cdate' => gmdate('U'),
													'user_id' => $user_id,
											)
									),
									'images' => array(
											'actual' => $actual_images,
											'deleted' => array()
									)
							);

				}

				$archive_params['settings'] = array(
						'client_listener_auth' => $this->config['static']['http_auth'],
						'max_versions_count' => $this->config['static']['max_versions_count'],
						'office_ip' => $this->config['static']['office_ip'],
						'my_listener_uri' => $this->config['static']['my_listener_uri'],
						'client_settings' => $this->config['upgradeable']['client_settings'],
						'fonts' => $this->config['static']['fonts']
				);

				$zip->setArchiveComment(json_encode($archive_params));
				$zip->addFromString('index.html', $html . $this->config['upgradeable']['vms_activator']);

//				echo PHP_EOL.PHP_EOL.PHP_EOL.PHP_EOL;
//				for ($i = 0; $i < $zip->numFiles; $i++) {
//					$filename = $zip->getNameIndex($i);
//					echo $filename.PHP_EOL;
//				}

				debug(array($archive_params, $html));

				$result = $zip->close();
				if ($result !== true) {
					die($this->render('contactAdmin', array('error' => 'zip archive ' . $zip_file_abs . ' problem ' . $result . $zip->getStatusString())));
				}

//				die('result:'.PHP_EOL.print_r($archive_params, true).PHP_EOL.$html.PHP_EOL);

				$vms_listener_url = trim($this->config['upgradeable']['vms_listener_uri'], '/') . '/listener';

				$json = $this->request($vms_listener_url, array(
						'POST' => array(
								'mode' => 'upload',
								'session' => $session_id,
								'user_id' => $user_id
						),
						'upload_from' => $zip_file_abs,
						'login' => $this->config['static']['vms_auth']['api_key'],
						'password' => $this->config['static']['vms_auth']['secret'],
				));

				$result = json_decode($json, true);
				if (!is_array($result)) { //not json
					print_r($json);
					die;
				}

				if ($result['rc'] == 1) {
					return 'http://' . $this->config['upgradeable']['vms_listener_uri'] . '/edit/' . $session_id;
				} elseif ($result['rc'] == 5) {
					die('material ' . $material_id . ' is locked by ' . $result['data']['user_id']);
				} else {
					print_r($result);
					die;
				}

			} else {
				$this->render('errorDuringRequestVms', array('error' => 'empty seesion_id'));
			}
		}

		function get_session ($material_id, $user_id)
		{
			$params['POST'] = array(
					'user_id' => $user_id,
					'user_ip' => $_SERVER['REMOTE_ADDR'],
					'material_id' => $material_id,
			);
			$params['login'] = $this->config['static']['vms_auth']['api_key'];
			$params['password'] = $this->config['static']['vms_auth']['secret'];

			$json = $this->request($this->config['upgradeable']['vms_listener_uri'] . '/get_session', $params);

			$result = json_decode($json, true);
			if (!is_array($result)) { //not json
				print_r($json);
				exit;
			}

			if ($result['rc'] == 1) {
				return $result['data']['session_id'];
			} elseif ($result['rc'] == 5) {
				die('material ' . $material_id . ' is locked by ' . $result['data']['user_id']);
			} else {
				print_r($result);
				die();
			}
		}

		function get_images ($html, $config)
		{
			$images = array();
			foreach ($config['regexp'] as $regexp) {
				preg_match_all($regexp, $html, $buff);
				if (!empty($buff[2])) {
					$images = array_merge($images, $buff[2]);
				} else {
					$buff = array();
				}
			}
			$images = array_unique($images);

			foreach ($config['elements'] as $element => $element_config) {
				foreach ($element_config['attributes'] as $attribute) {
					$dom = new \DOMDocument();
					$dom->loadHTML($html);
					$imgs = $dom->getElementsByTagName($element);
					foreach ($imgs as $img) {
						$image = $img->getAttribute($attribute);
						if (!empty($image) && (strpos($image, 'base64') === false) && !in_array($image, $images)) {
							$images[] = $image;
						}
					}
					unset($imgs);
				}
			}

			$result = array();
			foreach ($images as $image) {
				$buff = parse_url($image);
				if (empty($buff['host']) and strpos($image, trim($this->config['static']['dirs']['rel_img_path'], '/')) !== false) {
					$result[] = $buff['path'];
				}
			}

			return $result;
		}

		function writeLog($name, $message) {
//			$log = fopen(__DIR__.'/log.txt', 'a');
//			fprintf($log, "%s %s: %s\n", date('Y-m-d H:i:s'), $name.PHP_EOL, print_r($message, true).PHP_EOL.PHP_EOL.PHP_EOL);
//			fflush($log);
//			fclose($log);
			return null;
		}
	}

?>