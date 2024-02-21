<?php

class Appful_API_Post {

	// Note:
	//   Appful_API_Post objects must be instantiated within The Loop.

	var $id;              // Integer
	var $type;            // String
	var $slug;            // String
	var $url;             // String
	var $status;          // String ("draft", "published", or "pending")
	var $title;           // String
	var $title_plain;     // String
	var $content;         // String (modified by read_more query var)
	var $excerpt;         // String
	var $date;            // String (modified by date_format query var)
	var $modified;        // String (modified by date_format query var)
	var $categories;      // Array of objects
	var $tags;            // Array of objects
	var $author;          // Object
	var $comments;        // Array of objects
	var $attachments;     // Array of objects
	var $comment_count;   // Integer
	var $comment_status;  // String ("open" or "closed")
	var $thumbnail_images;       // String
	var $custom_fields;   // Object (included by using custom_fields query var)
	var $shortcodes;
	var $language;
	var $sticky;

	function Appful_API_Post($wp_post = null) {
		if (!empty($wp_post)) {
			$this->import_wp_object($wp_post);
		}
		do_action("appful_api_{$this->type}_constructor", $this);
	}


	function create($values = null) {
		unset($values['id']);
		if (empty($values) || empty($values['title'])) {
			$values = array(
				'title' => 'Untitled',
				'content' => ''
			);
		}
		return $this->save($values);
	}


	function update($values) {
		$values['id'] = $this->id;
		return $this->save($values);
	}


	function save($values = null) {
		global $appful_api, $user_ID;

		$wp_values = array();

		if (!empty($values['id'])) {
			$wp_values['ID'] = $values['id'];
		}

		if (!empty($values['type'])) {
			$wp_values['post_type'] = $values['type'];
		}

		if (!empty($values['status'])) {
			$wp_values['post_status'] = $values['status'];
		}

		if (!empty($values['title'])) {
			$wp_values['post_title'] = $values['title'];
		}

		if (!empty($values['content'])) {
			$wp_values['post_content'] = $values['content'];
		}

		if (!empty($values['author'])) {
			$author = $appful_api->introspector->get_author_by_login($values['author']);
			$wp_values['post_author'] = $author->id;
		}

		if (isset($values['categories'])) {
			$categories = explode(',', $values['categories']);
			foreach ($categories as $category_slug) {
				$category_slug = trim($category_slug);
				$category = $appful_api->introspector->get_category_by_slug($category_slug);
				if (empty($wp_values['post_category'])) {
					$wp_values['post_category'] = array($category->id);
				} else {
					array_push($wp_values['post_category'], $category->id);
				}
			}
		}

		if (isset($values['tags'])) {
			$tags = explode(',', $values['tags']);
			foreach ($tags as $tag_slug) {
				$tag_slug = trim($tag_slug);
				if (empty($wp_values['tags_input'])) {
					$wp_values['tags_input'] = array($tag_slug);
				} else {
					array_push($wp_values['tags_input'], $tag_slug);
				}
			}
		}

		if (isset($wp_values['ID'])) {
			$this->id = wp_update_post($wp_values);
		} else {
			$this->id = wp_insert_post($wp_values);
		}

		if (!empty($_FILES['attachment'])) {
			include_once ABSPATH . '/wp-admin/includes/file.php';
			include_once ABSPATH . '/wp-admin/includes/media.php';
			include_once ABSPATH . '/wp-admin/includes/image.php';
			$attachment_id = media_handle_upload('attachment', $this->id);
			$this->attachments[] = new Appful_API_Attachment($attachment_id);
			unset($_FILES['attachment']);
		}

		$wp_post = get_post($this->id);
		$this->import_wp_object($wp_post);

		return $this->id;
	}


	function import_wp_object($wp_post) {
		global $appful_api, $post;
        $post = $wp_post;
		$date_format = $appful_api->query->date_format;
		$this->id = (int) $wp_post->ID;
		setup_postdata($wp_post);
		$this->set_value('type', $wp_post->post_type);
		$this->set_value('slug', $wp_post->post_name);
		$this->set_value('url', get_permalink($this->id));
		$this->set_value('status', $wp_post->post_status);
		$this->sticky = is_sticky($wp_post->ID);

		$title = get_the_title($this->id);
		$this->set_value('title', $title);
		$title_plain = strip_tags(@$this->title);
		if ($title_plain == "") {
			if (preg_match("/data-original='.*?'/is", $title, $matches)) {
				$matches = $matches[0];
				$array = explode("'", $matches);
				$array = $array[1];
				$title_plain = base64_decode($array);
			}
		}
		$this->set_value('title_plain', $title_plain);
		$this->set_value("content", $wp_post->post_content);
		$this->set_content_value();
		$this->set_value('excerpt', apply_filters('the_excerpt', get_the_excerpt()));
		$this->set_value('date', get_post_time($date_format, true));
		$this->set_value('modified', date($date_format, strtotime($wp_post->post_modified)));
		$this->set_categories_value();
		$this->set_tags_value();
		$this->set_author_value($wp_post->post_author);
		$this->set_comments_value();
		$this->set_attachments_value();
		$this->set_value('comment_count', (int) $wp_post->comment_count);
		$this->set_value('comment_status', $wp_post->comment_status);
		$this->set_thumbnail_value();
		$this->set_custom_fields_value();
		$this->set_custom_taxonomies($wp_post->post_type);
		do_action("appful_api_import_wp_post", $this, $wp_post);


		$lang = $appful_api->wpml->post_lang($this->id);
		if ($lang) {
			$this->language = $lang;
		} else {
			unset($this->language);
		}
	}


	function set_value($key, $value) {
		global $appful_api;
		if ($appful_api->include_value($key)) {
			$this->$key = $value;
		} else {
			unset($this->$key);
		}
	}


	function set_content_value() {
		global $appful_api, $more, $wp_query, $single_query, $is_appful_app_content;
		$more = 1;
		if ($appful_api->include_value('content')) {
			$is_appful_app_content = 1;
			$content = get_the_content();
			if (!$content) $content = $this->content;

			if (!isset($single_query)) {
				$single_query = new WP_Query(array('p' => $this->id, 'post_type' => 'any'));
			}

			$current_query = $wp_query;
			$wp_query = $single_query;

			$this->shortcodes = array();

			preg_match_all("/\[.*?\][^\[]*(\[\/.*?\])?/is", str_replace("\n", "", $content), $matches);
			foreach ($matches[0] as $match) {
				$array = explode(" ", trim($match, "[/]"));
				if (!in_array($array[0], $this->shortcodes)) {
					$this->shortcodes[] = $array[0];
				}

				if ($array[0] == "poll") {
					preg_match('/id="([0-9]+)/i"', $array[1], $pollid);
					$content = str_replace($match, "<iframe class=\"wp-poll\" frameborder=\"0\" scrolling=\"no\" id=\"iframe\" onload=\"resizeIframe(this);\" src=\"". get_option("siteurl", "") ."/wp-content/plugins/appful/plugins/wp-polls.php?id=" . $pollid[1] . "\"></iframe>", $content);
				} else if ((!in_array($array[0], $appful_api->enabled_shortcodes()) && !in_array('*', $appful_api->enabled_shortcodes())) || in_array($array[0], $appful_api->disabled_shortcodes())) {
					$content = str_replace($match, "", $content);
				}
			}

			if (!$appful_api->include_value('shortcodes')) {
				unset($this->shortcodes);
			}

			remove_filter('the_content', 'likeornot_add_content');
			remove_filter('the_content', 'pmpro_membership_content_filter', 5);
			remove_filter('the_content', 'pmprosm_the_content_account_page', 30);
			add_filter('a3_lazy_load_run_filter', function ($apply_lazyload) { return false; }, 11);
			add_filter('bjll/enabled', '__return_false');
			add_filter('do_rocket_lazyload', '__return_false');
			if(class_exists('CrazyLazy')) remove_filter('the_content', array(CrazyLazy, 'prepare_images'), 12);
            if(!defined("REST_REQUEST")) define("REST_REQUEST", true); //avia compatibility
            
			$content = apply_filters('the_content', $content);
			$content = str_replace(']]>', ']]&gt;', $content);
			$this->content = $content;

			$wp_query = $current_query;
			unset($is_appful_app_content);
		} else {
			unset($this->content);
		}


	}


	function set_categories_value() {
		global $appful_api;
		if ($appful_api->include_value('categories')) {
			$this->categories = array();
			if ($wp_categories = get_the_category($this->id)) {
				foreach ($wp_categories as $wp_category) {
					$category = new Appful_API_Category($wp_category);
					if ($category->id == 1 && $category->slug == 'uncategorized') {
						// Skip the 'uncategorized' category
						continue;
					}
					$this->categories[] = $category->id;
				}
			}
		} else {
			unset($this->categories);
		}
	}


	function set_tags_value() {
		global $appful_api;
		if ($appful_api->include_value('tags')) {
			$this->tags = array();
			if ($wp_tags = get_the_tags($this->id)) {
				foreach ($wp_tags as $wp_tag) {
					$tag = new Appful_API_Tag($wp_tag);
					$this->tags[] = $tag->id;
				}
			}
		} else {
			unset($this->tags);
		}
	}


	function set_author_value($author_id) {
		global $appful_api;
		if ($appful_api->include_value('author')) {
			$this->author = new Appful_API_Author($author_id);
		} else {
			unset($this->author);
		}
	}


	function set_comments_value() {
		global $appful_api;
		if ($appful_api->include_value('comments')) {
			$this->comments = $appful_api->introspector->get_comments($this->id);
		} else {
			unset($this->comments);
		}
	}


	function set_attachments_value() {
		global $appful_api;
		if ($appful_api->include_value('attachments')) {
			$this->attachments = $appful_api->introspector->get_attachments($this->id);
		} else {
			unset($this->attachments);
		}
	}


	function set_thumbnail_value() {
		global $appful_api;
		if (!function_exists('get_post_thumbnail_id')) {
			unset($this->thumbnail_images);
			return;
		}
		$this->thumbnail_id = get_post_thumbnail_id($this->id);

		$attachment_id = $this->thumbnail_id;
		if (!$attachment_id) {
			unset($this->thumbnail_images);
			unset($this->thumbnail_id);
			return;
		}

		$this->thumbnail_id = (int)$this->thumbnail_id;

		if (!isset($_REQUEST["includethumb"])) {
			unset($this->thumbnail_images);
			return;
		}

		$thumbnail_size = $this->get_thumbnail_size();
		$attachment = $appful_api->introspector->get_attachment($attachment_id);
		$image = $attachment->images[$thumbnail_size];
		$thumbnail = $image->url;
		if (!$thumbnail) $thumbnail = $attachment->url;
		$this->thumbnail_images = $attachment->images;
		if (count($this->thumbnail_images) == 0 && $thumbnail) {
			$this->thumbnail_images = array("thumbnail" => array("url" => $thumbnail));
		}
	}


	function set_custom_fields_value() {
		global $appful_api;
		if ($appful_api->include_value('custom_fields')) {
			$wp_custom_fields = get_post_custom($this->id);
			$this->custom_fields = new stdClass();
			if ($appful_api->query->custom_fields) {
				$keys = explode(',', $appful_api->query->custom_fields);
			}
			foreach ($wp_custom_fields as $key => $value) {
				try {
				if ($appful_api->query->custom_fields) {
					if (in_array($key, $keys)) {
						$this->custom_fields->$key = $wp_custom_fields[$key];
					}
				} else if (substr($key, 0, 1) != '_') {
						$this->custom_fields->$key = $wp_custom_fields[$key];
					}
					} catch (Exception $e) {}
			}
		} else {
			unset($this->custom_fields);
		}
	}


	function set_custom_taxonomies($type) {
		global $appful_api;
		$taxonomies = get_taxonomies(array(
				'object_type' => array($type),
				'public' => true,
				'_builtin' => false
			), 'objects');
		foreach ($taxonomies as $taxonomy_id => $taxonomy) {
			$taxonomy_key = "taxonomy_$taxonomy_id";
			if (!$appful_api->include_value($taxonomy_key)) {
				continue;
			}
			$taxonomy_class = $taxonomy->hierarchical ? 'Appful_API_Category' : 'Appful_API_Tag';
			$terms = get_the_terms($this->id, $taxonomy_id);
			$this->$taxonomy_key = array();
			if (!empty($terms)) {
				$taxonomy_terms = array();
				foreach ($terms as $term) {
					$taxonomy_terms[] = new $taxonomy_class($term);
				}
				$this->$taxonomy_key = $taxonomy_terms;
			}
		}
	}


	function get_thumbnail_size() {
		global $appful_api;
		if ($appful_api->query->thumbnail_size) {
			return $appful_api->query->thumbnail_size;
		} else if (function_exists('get_intermediate_image_sizes')) {
				$sizes = get_intermediate_image_sizes();
				if (in_array('post-thumbnail', $sizes)) {
					return 'post-thumbnail';
				}
			}
		return 'thumbnail';
	}


}


?>
