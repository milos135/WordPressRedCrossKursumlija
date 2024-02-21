<?php
/*
Plugin Name: appful
Plugin URI: https://appful.io/
Description: appful is one of the world's most remarkable & beautiful app service for Blogger and Online magazines. It is designed to create outstanding real native iOS and Android Apps based on the content of your Wordpress site or YouTube channel. appful is surprisingly simple to use and not a single line of code is required.
Version: 1.0.124
Author: appful GmbH
Author URI: https://appful.io/
*/

error_reporting(0);

if (isset($_REQUEST["appful_session_id"])) {
	$_GET["session_id"] = $_POST["session_id"] = $_REQUEST["session_id"] = $_REQUEST["appful_session_id"];
}

$appful_disable = false;
if (!isset($_REQUEST["appful_off"]) && isset($_REQUEST["appful_disable"])) {
	$session_id = get_option("appful_session_id", "");
	$ipAuth = false;
	if (isset($_SERVER['HTTP_CLIENT_IP']) || isset($_SERVER['REMOTE_ADDR'])) {
		$ipAuth = (isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : $_SERVER['REMOTE_ADDR']) == "144.76.106.246";
	}

	if ((strlen($session_id) > 0 && isset($_REQUEST["session_id"])) || (strlen($session_id) == 0 && $ipAuth)) {
		$tempAuth = ($_REQUEST["session_id"] == $session_id && strlen($session_id) > 0) || (strlen($session_id) == 0 && $ipAuth);
		if ($tempAuth) {
			delete_option("appful_disable");
			unlink(dirname(__FILE__) . '/disable');
			if ($_REQUEST["appful_disable"] == 1) {
				add_option("appful_disable", true);
				file_put_contents(dirname(__FILE__) . '/disable', '1');
				$appful_disable = true;
			}

			if ($wp_rewrite) {
				if (!$appful_disable && $appful_api && function_exists('appful_api_rewrites')) {
					add_filter('rewrite_rules_array', 'appful_api_rewrites');
				}
				$wp_rewrite->flush_rules();
			}
		}
	}
}


if (!get_option("appful_disable", false) && !file_exists(dirname(__FILE__) . '/disable') && !$appful_disable && !isset($_REQUEST["appful_off"])) {
	$dir = appful_api_dir();

	ini_set("allow_url_fopen", 1);
	@set_time_limit(300);

	if (isset($_REQUEST["error_reporting"])) {
		ini_set('display_startup_errors', 1);
		ini_set('display_errors', 1);
		error_reporting(-1);
	}

	if (isset($_REQUEST["time_limit"])) {
		$limit = (int)$_REQUEST["time_limit"];
		if ($limit > 0) set_time_limit($limit);
	}

	@include_once "$dir/singletons/api.php";
	@include_once "$dir/singletons/taxonomy.php";
	@include_once "$dir/singletons/query.php";
	@include_once "$dir/singletons/introspector.php";
	@include_once "$dir/singletons/response.php";
	@include_once "$dir/models/post.php";
	@include_once "$dir/models/comment.php";
	@include_once "$dir/models/category.php";
	@include_once "$dir/models/tag.php";
	@include_once "$dir/models/author.php";
	@include_once "$dir/models/attachment.php";
	@include_once "$dir/models/widget.php";
	@include_once "$dir/plugins/wpml.php";

	add_action('widgets_init',
		create_function('', 'return register_widget("Appful_Widget");')
	);

	//add_action('publish_post', 'on_post');
	//add_action('wp_trash_post', 'on_post');
	//add_action('untrash_post', 'appful_update_post');

	add_action("comment_post", "appful_on_comment");
	add_action('transition_post_status', 'appful_on_post_transition', 99, 3);
	add_action('transition_comment_status', 'appful_on_comment_transition', 10, 3);

	// Add initialization and activation hooks
	add_action('init', 'appful_api_init');
	add_action('admin_init', 'appful_api_admin_init');

	register_activation_hook(__FILE__, 'appful_api_activation');
	register_deactivation_hook(__FILE__, 'appful_api_deactivation');
	register_uninstall_hook(__FILE__, "appful_api_uninstall");

	add_action('admin_notices', 'appful_admin_notice');
}


function appful_api_init() {
	global $appful_api;
	if (phpversion() < 5) {
		add_action('admin_notices', 'appful_api_php_version_warning');
		return;
	}
	if (!class_exists('Appful_API')) {
		add_action('admin_notices', 'appful_api_class_warning');
		return;
	}

	add_filter('rewrite_rules_array', 'appful_api_rewrites');

	$appful_api = new Appful_API();
}


function appful_api_admin_init() {
	if (get_option('appful_do_redirect', 0) == 1) {
		delete_option('appful_do_redirect');
		wp_redirect("admin.php?page=appful");
	}

	appful_taxonomy_init();
}


function appful_api_php_version_warning() {
	echo "<div id=\"json-api-warning\" class=\"updated fade\"><p>appful benötigt PHP 5.0 oder höher.</p></div>";
}


function appful_api_class_warning() {
	echo "<div id=\"json-api-warning\" class=\"updated fade\"><p>Oops, Appful_API class not found. If you've defined a Appful_API_DIR constant, double check that the path is correct.</p></div>";
}


function appful_api_activation() {
	// Add the rewrite rule on activation
	global $wp_rewrite;
	add_filter('rewrite_rules_array', 'appful_api_rewrites');
	$wp_rewrite->flush_rules();

	$appful_api = new Appful_API();
	if (get_option("appful_been_installed", 0) == 0 || strlen(get_option("appful_session_id")) == 0) {
		$appful_api->save_option("appful_been_installed", 1);
		add_option('appful_do_redirect', 1);
	} else {
		$appful_api->register();
	}
}


function appful_api_deactivation() {
	// Remove the rewrite rule on deactivation
	global $wp_rewrite;
	$wp_rewrite->flush_rules();

	global $appful_api;
	if (strlen(get_option("appful_blog_id")) > 0) {
		$appful_api->request("register", array("disable" => 1));
	}
}


function appful_api_uninstall() {
	global $appful_api;
	if ($appful_api) {
		if (strlen(get_option("appful_blog_id")) > 0) {
			$appful_api->request("register", array("uninstall" => 1));
		}
	}
}


function appful_api_rewrites($wp_rules) {
	$base = "appful-api";
	if (empty($base)) {
		return $wp_rules;
	}
	$appful_api_rules = array(
		"$base\$" => 'index.php?jsn=info',
		"$base/(.+)\$" => 'index.php?jsn=$matches[1]'
	);

	if (get_option("appful_universallinks_ios", false)) {
		$appful_api_rules['^apple-app-site-association'] = 'index.php?jsn=apple-app-site-association';
		$appful_api_rules['^.well-known/apple-app-site-association'] = 'index.php?jsn=apple-app-site-association';
	}

	if (get_option("appful_universallinks_android", false)) {
		$appful_api_rules['^assetlinks.json'] = 'index.php?jsn=assetlinks.json';
		$appful_api_rules['^.well-known/assetlinks.json'] = 'index.php?jsn=assetlinks.json';
	}

	return array_merge($appful_api_rules, $wp_rules);
}


function appful_api_dir() {
	if (defined('Appful_API_DIR') && file_exists(Appful_API_DIR)) {
		return Appful_API_DIR;
	} else {
		return dirname(__FILE__);
	}
}


function appful_on_post($post_id, $push = FALSE, $new_status = NULL, $old_status = NULL) {
	global $appful_api;
	if (!$appful_api) return;

	$post = get_post($post_id);
	if($post->post_type == "wc_user_membership") {
		$appful_api->request('updateMembership', array('user_id' => $post->post_author));
		return;
	}

	$params = array("post_id" => $post_id, "push" => $push && $post->post_type != "page" ? 1:0);
	if ($new_status) $params["new_status"] = $new_status;
	if ($old_status) $params["old_status"] = $old_status;
	$params["modified"] = strtotime($post->post_modified_gmt);
	$params["status"] = $post->post_status;
	$params["type"] = $post->post_type;

	$post_types = $appful_api->post_types();

	if (in_array($post->post_type, $post_types) && !(($old_status == $new_status && $old_status && $new_status && $new_status != "publish") || $new_status == "in-bearbeitung" || isset($_REQUEST["push"]))) {
		return $appful_api->request("cache", $params);
	}
}


function appful_on_post_transition($new_status, $old_status, $post) {
	if ($new_status != "auto-draft") {
		global $appful_api;
		appful_on_post($post->ID, $new_status == "publish" && $new_status != $old_status && $appful_api->getPushStatus($post->ID), $new_status, $old_status);
	}
}


function appful_on_comment_transition($new_status, $old_status, $comment) {
	appful_on_post($comment->comment_post_ID, false);
}


function appful_on_comment($comment_id) {
	appful_on_post(get_comment($comment_id)->comment_post_ID, false);
}


function appful_admin_notice() {
	global $pagenow, $appful_api;
	if (get_option("appful_invalid_session") && ($pagenow != "options-general.php" && $_GET["page"] != "appful")) {
		echo '<div class="error">
             <p>'. $appful_api->localize("hint_not_connected") .' <a href="options-general.php?page=appful">'. $appful_api->localize("connect") .'</a></p>
         </div>';
	}

	if (!in_array('curl', get_loaded_extensions()) && !ini_get('allow_url_fopen')) {
		echo '<div class="error">
             <p>'. $appful_api->localize("fopen_error") .'</p>
         </div>';
	}
}


?>
