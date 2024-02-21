<?php

class Appful_API_Comment {

	var $id;      // Integer
	var $name;    // String
	var $url;     // String
	var $date;    // String
	var $content; // String
	var $parent;  // Integer
	var $avatar;
	var $author;  // Object (only if the user was registered & logged in)
	var $email_hash;

	function Appful_API_Comment($wp_comment = null) {
		if ($wp_comment) {
			$this->import_wp_object($wp_comment);
		}
	}


	function import_wp_object($wp_comment) {
		global $appful_api;

		$date_format = $appful_api->query->date_format;

		$this->id = (int) $wp_comment->comment_ID;
		$this->name = $wp_comment->comment_author;
		$this->url = $wp_comment->comment_author_url;
		$this->date = date($date_format, strtotime($wp_comment->comment_date_gmt));
		$this->content = $wp_comment->comment_content;
		$this->parent = (int) $wp_comment->comment_parent;
		$this->avatar = "http://www.gravatar.com/avatar/" . md5(strtolower(trim($wp_comment->comment_author_email)));
		$this->email_hash = md5(strtolower(trim($wp_comment->comment_author_email)));

		if($appful_api->hasZakiCommentLike()) {
			global $wpdb;
			$table_name = ZakiLikeDislike::getTableName();
			$row = $wpdb->get_row("SELECT * FROM $table_name WHERE comment_id = ". $this->id);
			if($row) $this->custom_fields = array("likes" => (int)$row->rate_like_value, "dislikes" => (int)$row->rate_dislike_value);
		}

		//$this->raw = $wp_comment;

        if (!empty($wp_comment->user_id)) {
            $author = new Appful_API_Author($wp_comment->user_id);
            //$this->author = $author;
            $this->name = $author->name;
            $this->url = $author->url;
            $this->avatar = $author->avatar;
            $this->email_hash = $author->email_hash;
            unset($this->author);
        } else {
            unset($this->author);
        }
	}


	function handle_submission() {
		global $comment, $wpdb, $appful_api;
		add_action('comment_id_not_found', array(&$this, 'comment_id_not_found'));
		add_action('comment_closed', array(&$this, 'comment_closed'));
		add_action('comment_on_draft', array(&$this, 'comment_on_draft'));
		add_filter('comment_post_redirect', array(&$this, 'comment_post_redirect'));
		//add_action('comment_flood_trigger', array(&$this, 'comment_flood'));
		//add_action('comment_duplicate_trigger', array(&$this, 'comment_flood'));
		//add_filter('comment_flood_filter', '__return_false');

		if(!$_REQUEST["parent"]) $_REQUEST["parent"] = 0;
		if(!$_REQUEST["ip"]) $_REQUEST["ip"] = $appful_api->getClientIP();
		if(!$_REQUEST["user_agent"]) $_REQUEST["user_agent"] = $_SERVER['HTTP_USER_AGENT'];

		$data = array(
			'comment_post_ID' => $_REQUEST["post_id"],
			'comment_author_url' => $_REQUEST["url"],
			'comment_content' => $_REQUEST["content"],
			'comment_type' => '',
			'comment_parent' => $_REQUEST["parent"],
			'comment_author_IP' => $_REQUEST["ip"],
			'comment_agent' => $_REQUEST["user_agent"],
			'comment_date_gmt' => current_time( 'mysql', 1 ),
			'comment_date' => current_time('mysql')
		);
        if(isset($_REQUEST["user_id"])) {
            $data['user_id'] = $_REQUEST["user_id"];
            $data['comment_author'] = get_the_author_meta('display_name', $data['user_id']);
            $data['comment_author_email'] = get_the_author_meta('user_email', $data['user_id']);
        }
        else {
            $data['comment_author'] = $_REQUEST["author_name"];
            $data['comment_author_email'] = $_REQUEST["email"];
        }

		$data = wp_filter_comment($data);
		$data['comment_approved'] = wp_allow_comment($data, true);
		if(is_wp_error($data['comment_approved'])) {
			$error = $data['comment_approved'];
			$response = array("status" => end(explode('comment_', array_keys($error->errors)[0])), 'error' => array_values($error->errors)[0]);
			if($response['status'] != 'flood' || !get_option("appful_allow_commentflood", false)) {
				$appful_api->response->respond($response);
				die();
			}
		}

		$comment_id = wp_insert_comment($data);
		do_action('comment_post', $comment_id, $data['comment_approved'], $data);

		$this->import_wp_object(get_comment($comment_id));

		$appful_api->response->respond(array("status" => $data["comment_approved"] == 1 ? "ok" : "pending", "payload" => $this));
		die();
	}


	function comment_id_not_found() {
		global $appful_api;
		$appful_api->error("Post ID '{$_REQUEST['post_id']}' not found.");
		die();
	}


	function comment_closed() {
		global $appful_api;
		$appful_api->error("Post is closed for comments.");
		die();
	}


	function comment_on_draft() {
		global $appful_api;
		$appful_api->error("You cannot comment on unpublished posts.");
		die();
	}


	function comment_flood() {
		global $appful_api;
		if(!get_option("appful_allow_commentflood", false)) {
			$appful_api->response->respond(array("status" => "flood"));
			die();
		}
	}


	function comment_duplicate() {
		global $appful_api;
		$appful_api->response->respond(array("status" => "duplicate"));
		die();
	}


	function comment_post_redirect() {
		global $comment, $appful_api;
		$status = ($comment->comment_approved) ? 'ok' : 'pending';
		$new_comment = new Appful_API_Comment($comment);
		$appful_api->response->respond($new_comment, $status);
	}


}


?>
