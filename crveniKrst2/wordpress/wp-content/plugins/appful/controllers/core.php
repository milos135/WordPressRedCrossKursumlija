<?php

/*
  Controller name: Basis
  Controller description: Dieses Modul wird für die App ansich vorausgesetzt.
 */

class Appful_API_Core_Controller {

	public function info() {
		global $appful_api;
		$php = '';
		if (!empty($appful_api->query->controller)) {
			return $appful_api->controller_info($appful_api->query->controller);
		} else {
			$dir = appful_api_dir();
			if (file_exists("$dir/appful.php")) {
				$php = file_get_contents("$dir/appful.php");
			} else {
				$dir = dirname($dir);
				if (file_exists("$dir/appful.php")) {
					$php = file_get_contents("$dir/appful.php");
				}
			}
			if (preg_match('/^\s*Version:\s*(.+)$/m', $php, $matches)) {
				$version = $matches[1];
			} else {
				$version = '(Unknown)';
			}

			$response = array('version' => $version, "allow_url_fopen" => ini_get('allow_url_fopen') ? true : false, "curl" => get_loaded_extensions() ? true : false, "use_ssl" => get_option("appful_ssl_available") ? true : false, "server_id" => get_option("appful_server_id", 1), "disable_ssl" => get_option("appful_disable_ssl") ? true : false, "disable_curl" => get_option("appful_disable_curl") ? true : false, "disable_fopen" => get_option("appful_disable_fopen") ? true : false, 'php_version' => phpversion(), 'json' => function_exists('json_decode'), 'authorized' => strlen(get_option('appful_session_id', '')) == 0 ? false : true);
			if(!$response["use_ssl"]) {
				$response["last_ssl_check"] = get_option("appful_last_ssl_check");
				$ssl_error = get_option("appful_ssl_error");
				if($ssl_error) $response["ssl_error"] = $ssl_error;
			}
			if (!ini_get('allow_url_fopen') && !in_array('curl', get_loaded_extensions())) {
				$response["status"] = "error";
				$response["error"] = "requests not possible";
			}
			return $response;
		}
	}


	public function get_recent_posts() {
		global $appful_api;
		$posts = $appful_api->introspector->get_posts();
		return $this->posts_result($posts);
	}


	public function get_posts() {
		global $appful_api;
		$url = parse_url($_SERVER['REQUEST_URI']);
		$defaults = array(
			'ignore_sticky_posts' => true
		);
		$query = wp_parse_args($url['query']);
		unset($query['jsn']);
		unset($query['post_status']);
		$query = array_merge($defaults, $query);
		$posts = $appful_api->introspector->get_posts($query);
		$result = $this->posts_result($posts);
		$result['query'] = $query;
		return $result;
	}


	public function get_post() {
		global $appful_api, $post;
		$post = $appful_api->introspector->get_current_post();
		if ($post) {
			$previous = get_adjacent_post(false, '', true);
			$next = get_adjacent_post(false, '', false);
			$response = array(
				'post' => new Appful_API_Post($post)
			);
			if ($previous) {
				$response['previous_url'] = get_permalink($previous->ID);
			}
			if ($next) {
				$response['next_url'] = get_permalink($next->ID);
			}
			return $response;
		} else {
			$appful_api->error("Not found.");
		}
	}


	public function get_page() {
		global $appful_api;
		extract($appful_api->query->get(array('id', 'slug', 'page_id', 'page_slug', 'children')));
		if ($id || $page_id) {
			if (!$id) {
				$id = $page_id;
			}
			$posts = $appful_api->introspector->get_posts(array(
					'page_id' => $id
				));
		} else if ($slug || $page_slug) {
				if (!$slug) {
					$slug = $page_slug;
				}
				$posts = $appful_api->introspector->get_posts(array(
						'pagename' => $slug
					));
			} else {
			$appful_api->error("Include 'id' or 'slug' var in your request.");
		}

		// Workaround for https://core.trac.wordpress.org/ticket/12647
		if (empty($posts)) {
			$url = $_SERVER['REQUEST_URI'];
			$parsed_url = parse_url($url);
			$path = $parsed_url['path'];
			if (preg_match('#^http://[^/]+(/.+)$#', get_bloginfo('url'), $matches)) {
				$blog_root = $matches[1];
				$path = preg_replace("#^$blog_root#", '', $path);
			}
			if (substr($path, 0, 1) == '/') {
				$path = substr($path, 1);
			}
			$posts = $appful_api->introspector->get_posts(array('pagename' => $path));
		}

		if (count($posts) == 1) {
			if (!empty($children)) {
				$appful_api->introspector->attach_child_posts($posts[0]);
			}
			return array(
				'page' => $posts[0]
			);
		} else {
			$appful_api->error("Not found.");
		}
	}


	public function get_date_posts() {
		global $appful_api;
		if ($appful_api->query->date) {
			$date = preg_replace('/\D/', '', $appful_api->query->date);

			if (preg_match('/^\d{4}(\d{2})?(\d{2})?$/', $date)) {
				$request = array('year' => substr($date, 0, 4));
				if (strlen($date) > 4) {
					$request['monthnum'] = (int) substr($date, 4, 2);
				}
				if (strlen($date) > 6) {
					$request['day'] = (int) substr($date, 6, 2);
				}
			}

		}

		$posts = $appful_api->introspector->get_posts($request);
		return $this->posts_result($posts);
	}


	public function get_category_posts() {
		global $appful_api;
		$category = $appful_api->introspector->get_current_category();
		if (!$category) {
			$appful_api->error("Not found.");
		}
		$posts = $appful_api->introspector->get_posts(array(
				'cat' => $category->id
			));
		return $this->posts_object_result($posts, $category);
	}


	public function get_tag_posts() {
		global $appful_api;
		$tag = $appful_api->introspector->get_current_tag();
		if (!$tag) {
			$appful_api->error("Not found.");
		}
		$posts = $appful_api->introspector->get_posts(array(
				'tag' => $tag->slug
			));
		return $this->posts_object_result($posts, $tag);
	}


	public function get_author_posts() {
		global $appful_api;
		$author = $appful_api->introspector->get_current_author();
		if (!$author) {
			$appful_api->error("Not found.");
		}
		$posts = $appful_api->introspector->get_posts(array(
				'author' => $author->id
			));
		return $this->posts_object_result($posts, $author);
	}


	public function get_search_results() {
		global $appful_api;
		if ($appful_api->query->search) {
			$posts = $appful_api->introspector->get_posts(array(
					's' => $appful_api->query->search
				));
		} else {
			$appful_api->error("Include 'search' var in your request.");
		}
		return $this->posts_result($posts);
	}


	public function get_date_index() {
		global $appful_api;
		$permalinks = $appful_api->introspector->get_date_archive_permalinks();
		$tree = $appful_api->introspector->get_date_archive_tree($permalinks);
		return array(
			'permalinks' => $permalinks,
			'tree' => $tree
		);
	}


	public function get_category_index() {
		global $appful_api;
		$args = null;
		if (!empty($appful_api->query->parent)) {
			$args = array(
				'parent' => $appful_api->query->parent
			);
		}
		$categories = $appful_api->introspector->get_categories($args);
		return array(
			'count' => count($categories),
			'categories' => $categories
		);
	}


	public function get_tag_index() {
		global $appful_api;
		$tags = $appful_api->introspector->get_tags();
		return array(
			'count' => count($tags),
			'tags' => $tags
		);
	}


	public function get_author_index() {
		global $appful_api;
		$authors = $appful_api->introspector->get_authors();
		return array(
			'count' => count($authors),
			'authors' => array_values($authors)
		);
	}


	public function get_page_index() {
		global $appful_api;
		$pages = array();
		$post_type = $appful_api->query->post_type ? $appful_api->query->post_type : 'page';

		// Thanks to blinder for the fix!
		$numberposts = empty($appful_api->query->count) ? -1 : $appful_api->query->count;
		$wp_posts = get_posts(array(
				'post_type' => $post_type,
				'post_parent' => 0,
				'order' => 'ASC',
				'orderby' => 'menu_order',
				'numberposts' => $numberposts
			));
		foreach ($wp_posts as $wp_post) {
			$pages[] = new Appful_API_Post($wp_post);
		}
		foreach ($pages as $page) {
			$appful_api->introspector->attach_child_posts($page);
		}
		return array(
			'pages' => $pages
		);
	}


	public function get_nonce() {
		global $appful_api;
		extract($appful_api->query->get(array('controller', 'method')));
		if ($controller && $method) {
			$controller = strtolower($controller);
			if (!in_array($controller, $appful_api->get_controllers())) {
				$appful_api->error("Unknown controller '$controller'.");
			}
			require_once $appful_api->controller_path($controller);
			if (!method_exists($appful_api->controller_class($controller), $method)) {
				$appful_api->error("Unknown method '$method'.");
			}
			$nonce_id = $appful_api->get_nonce_id($controller, $method);
			return array(
				'controller' => $controller,
				'method' => $method,
				'nonce' => wp_create_nonce($nonce_id)
			);
		} else {
			$appful_api->error("Include 'controller' and 'method' vars in your request.");
		}
	}


	protected function get_object_posts($object, $id_var, $slug_var) {
		global $appful_api;
		$object_id = "{$type}_id";
		$object_slug = "{$type}_slug";
		extract($appful_api->query->get(array('id', 'slug', $object_id, $object_slug)));
		if ($id || $$object_id) {
			if (!$id) {
				$id = $$object_id;
			}
			$posts = $appful_api->introspector->get_posts(array(
					$id_var => $id
				));
		} else if ($slug || $$object_slug) {
				if (!$slug) {
					$slug = $$object_slug;
				}
				$posts = $appful_api->introspector->get_posts(array(
						$slug_var => $slug
					));
			} else {
			$appful_api->error("No $type specified. Include 'id' or 'slug' var in your request.");
		}
		return $posts;
	}


	protected function posts_result($posts) {
		global $wp_query;
		return array(
			'count' => count($posts),
			'count_total' => (int) $wp_query->found_posts,
			'pages' => $wp_query->max_num_pages,
			'posts' => $posts
		);
	}


	protected function posts_object_result($posts, $object) {
		global $wp_query;
		// Convert something like "Appful_API_Category" into "category"
		$object_key = strtolower(substr(get_class($object), 9));
		return array(
			'count' => count($posts),
			'pages' => (int) $wp_query->max_num_pages,
			$object_key => $object,
			'posts' => $posts
		);
	}


}


?>
