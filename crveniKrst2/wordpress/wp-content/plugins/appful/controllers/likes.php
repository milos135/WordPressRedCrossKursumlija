<?php

/*
  Controller name: Benutzer
  Controller description: Dieses Modul wird zum liken von Beiträgen benötigt
 */


class Appful_API_Likes_Controller {

	function vote() {
		global $appful_api;
		nocache_headers();

		if (isset($_REQUEST["post_id"]) && isset($_REQUEST["like"])) {
			$key = $_REQUEST["like"] == 1 ? "post-likes" : "post-dislikes";

			$likes = get_post_meta($_REQUEST["post_id"], $key, true) ?: 0;
			add_post_meta($_REQUEST['post_id'], $key, $likes + 1, true ) or
			update_post_meta($_REQUEST['postId'], $key, $likes + 1 );
			
			$appful_api->response->respond(array("payload" => array("post-likes" => get_post_meta($_REQUEST["post_id"], "post-likes", true) ?: 0, "post-dislikes" => get_post_meta($_REQUEST["post_id"], "post-dislikes", true) ?: 0)));
			die();
		} else {
			$appful_api->error("Please include all required arguments (post_id, like).");
			die();
		}
	}


}


?>
