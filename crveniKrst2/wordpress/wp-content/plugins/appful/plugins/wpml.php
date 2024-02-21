<?php

class Appful_Plugin_WPML {

	var $installed;

	public function __construct() {
		if (class_exists('SitePress')) {
			$this->installed = count($this->languages()) > 0;
		}
	}


	public function installed() {
		return $this->installed;
	}


	public function languages() {
		return array_keys(apply_filters('wpml_active_languages', NULL));
	}


	public function current() {
		return ICL_LANGUAGE_CODE;
	}


	public function post_lang($post_id) {
		if ($this->installed()) {
			$lang_infos = apply_filters('wpml_post_language_details', NULL, $post_id);
			return $lang_infos["language_code"];
		}
	}


	public function filterVar($key) {
		if ($this->installed()) {
			if (!$this->is_default()) {
				return $key . "_" . $this->current();
			}
		}

		return $key;
	}


	public function default_language() {
		global $sitepress;
		return $sitepress->get_default_language();
	}


	public function is_default() {
		return $this->current() == $this->default_language();
	}


}