<?php

/*
  Controller name: Kommentare
  Controller description: Dieses Modul wird für das Senden von Kommentaren aus der App benötigt.
 */

class Appful_API_Respond_Controller {

	function submit_comment() {
		global $appful_api;
		nocache_headers();

		if (empty($_REQUEST['post_id'])) {
			$appful_api->error("No post specified. Include 'post_id' var in your request.");
			die();
		} else if (((empty($_REQUEST['author_name']) || empty($_REQUEST['email'])) && empty($_REQUEST['user_id'])) || empty($_REQUEST['content'])) {
				$appful_api->error("Please include all required arguments (name, email, content).");
				die();
			} else if (empty($_REQUEST['user_id']) && !is_email($_REQUEST['email'])) {
				$appful_api->error("Please enter a valid email address.");
				die();
			}

		$pending = new Appful_API_Comment();

		$submit = $pending->handle_submission();
		return $submit;
	}

	function rate_comment() {
		global $appful_api;

		if(!function_exists("ZakiLikeDislike_Ajax") || !$appful_api->hasZakiCommentLike()) $appful_api->error("Zaki not installed");

		$table = ZakiLikeDislike::getTableName();
		if(isset($_REQUEST["post_id"])) $_POST["postid"] = $_REQUEST["post_id"];
		if(isset($_REQUEST["ratetype"])) $_POST['ratetype'] = $_REQUEST["ratetype"];
		$_SERVER['REMOTE_ADDR'] = $_REQUEST["ip"];

		ob_start();
		ZakiLikeDislike_Ajax();
		$result = ob_get_clean();
		$appful_api->response->respond(array("payload" => $result));
	}
}


?>
