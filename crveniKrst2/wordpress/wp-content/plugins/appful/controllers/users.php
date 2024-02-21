<?php

/*
  Controller name: Benutzer
  Controller description: Dieses Modul wird zum erstellen von Benutzerkonten benötigt.
 */


class Appful_API_Users_Controller {

	function create_user() {
		global $appful_api;
		nocache_headers();

		if (isset($_REQUEST["email"]) && (isset($_REQUEST["username"]) || (isset($_REQUEST["first_name"]) && isset($_REQUEST["last_name"])))) {
			if (email_exists($_REQUEST["email"]) == false) {
				$username = isset($_REQUEST["username"]) ? $_REQUEST["username"] : $_REQUEST["first_name"] . "." . $_REQUEST["last_name"];
				$startUsername = $username;
				$i = 1;
				while (username_exists($username)) {
					$username = $startUsername . "." . $i++;
				}

				$random_password = wp_generate_password(8, false);
				$user = wp_create_user($username, $random_password, $_REQUEST["email"]);

				if (!is_wp_error($user)) {
					if (isset($_REQUEST["first_name"])) update_user_meta($user, 'first_name', $_REQUEST["first_name"]);
					if (isset($_REQUEST["last_name"])) update_user_meta($user, 'last_name', $_REQUEST["last_name"]);
					if (isset($_REQUEST["url"])) update_user_meta($user, 'user_url', $_REQUEST["url"]);

					if (isset($_REQUEST["avatar_url"])) {
						include_once ABSPATH . 'wp-admin/includes/plugin.php';
						if (is_plugin_active('wp-user-avatar/wp-user-avatar.php') || is_plugin_active_for_network('wp-user-avatar/wp-user-avatar.php')) {
							$url = $_REQUEST["avatar_url"];
							$tmp = download_url($url);
							if (!is_wp_error($tmp)) {
								$file_array = array();

								preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $url, $matches);
								$file_array['name'] = "user_". $user . "_" . time() . "." . $matches[1];
								$file_array['tmp_name'] = $tmp;

								if (is_wp_error($tmp)) {
									@unlink($file_array['tmp_name']);
									$file_array['tmp_name'] = '';
								}

								$attach_id = media_handle_sideload($file_array, 0);
								if ( is_wp_error($attach_id) ) {
									@unlink($file_array['tmp_name']);
								} else {
									global $wpdb;
									update_user_meta($user, $wpdb->get_blog_prefix() . 'user_avatar', $attach_id);
								}
							}
						}
					}

					$appful_api->response->respond(array("payload" => $user));
					die();
				} else {
					$appful_api->error($user->get_error_code());
					die();
				}
			} else {
				$appful_api->response->respond(array("error" => "already_registered"));
				die();
			}
		} else {
			$appful_api->error("Please include all required arguments (username, email).");
			die();
		}
	}

	function login() {
		global $appful_api;
		nocache_headers();

		$username = $_REQUEST["username"];
		$password = $_REQUEST["password"];

		if(isset($username) && isset($password)) {
			$creds = array(
		        'user_login'    => $username,
		        'user_password' => $password,
		        'remember'      => false
		    );

			$user = wp_signon($creds, false);
			if (is_wp_error($user)) {
				$appful_api->error($user->get_error_code());
		    } else {
                $payload = array("user" => $user->data);
                if(function_exists('wc_memberships_get_user_active_memberships')) {
                    foreach(wc_memberships_get_user_active_memberships($user->ID) as $membership) {
                        $payload['memberships'][] = array('plan_id' => $membership->plan_id, 'end_date' => strtotime($membership->get_end_date()));
                    }
                }
                
                $appful_api->response->respond(array("payload" => $payload));
		    }
		} else {
			$appful_api->error("Please include all required arguments.");
		}
	}
    
    function get_memberships() {
        global $appful_api;
        
        if(!function_exists('wc_memberships_get_user_active_memberships')) return;
        
        $memberships = array();
        $users = is_array($_REQUEST['users']) ? $_REQUEST['users'] : json_decode($_REQUEST['users'], true);
        foreach($users as $user) {
            foreach(wc_memberships_get_user_active_memberships($user) as $membership) {
                $memberships[$user][] = array('plan_id' => $membership->plan_id, 'end_date' => strtotime($membership->get_end_date()));
            }
        }

        $appful_api->response->respond(array("payload" => $memberships));
    }

}


?>
