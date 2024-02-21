<?php

class Appful_API_Category {

    var $id;          // Integer
    var $slug;        // String
    var $title;       // String
    var $description; // String
    var $parent;      // Integer
    var $post_count;  // Integer

    function Appful_API_Category($wp_category = null) {
        if ($wp_category) {
            $this->import_wp_object($wp_category);
        }
    }

    function import_wp_object($wp_category) {
        $this->id = (int) $wp_category->term_id;
        $this->slug = $wp_category->slug;
        $this->title = $wp_category->name;
        $this->description = $wp_category->description;
        $this->parent = (int) $wp_category->parent;
        $this->post_count = (int) $wp_category->count;
        $this->thumbnails = appful_taxonomy_images($wp_category->term_id);
        if(!is_array($this->thumbnails)) unset($this->thumbnails);
        $taxonomy_image_url = get_option('appful_taxonomy_image'. $wp_category->term_id);
		if (!empty($taxonomy_image_url)) {
			$attachment_id = appful_taxonomy_get_attachment_id_by_url($taxonomy_image_url);
			if (!empty($attachment_id)) {
				$this->thumbnail_id = $attachment_id;
			}
		}
        //if(strlen($this->thumbnail) == 0) unset($this->thumbnail);
    }

}

?>
