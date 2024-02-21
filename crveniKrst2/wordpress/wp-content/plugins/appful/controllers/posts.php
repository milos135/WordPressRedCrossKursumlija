<?php

/*
  Controller name: Beiträge
  Controller description: Dieses Modul wird für die Erstellung und Bearbeitung von Beiträgen aus der App benötigt.
 */

class Appful_API_Posts_Controller {

    public function create_post() {
        global $appful_api;
        if (!current_user_can('edit_posts')) {
            $appful_api->error("You need to login with a user that has 'edit_posts' capacity.");
        }
        if (!$appful_api->query->nonce) {
            $appful_api->error("You must include a 'nonce' value to create posts. Use the `get_nonce` Core API method.");
        }
        $nonce_id = $appful_api->get_nonce_id('posts', 'create_post');
        if (!wp_verify_nonce($appful_api->query->nonce, $nonce_id)) {
            $appful_api->error("Your 'nonce' value was incorrect. Use the 'get_nonce' API method.");
        }
        nocache_headers();
        $post = new Appful_API_Post();
        $id = $post->create($_REQUEST);
        if (empty($id)) {
            $appful_api->error("Could not create post.");
        }
        return array(
            'post' => $post
        );
    }

    public function update_post() {
        global $appful_api;
        $post = $appful_api->introspector->get_current_post();
        if (empty($post)) {
            $appful_api->error("Post not found.");
        }
        if (!current_user_can('edit_post', $post->ID)) {
            $appful_api->error("You need to login with a user that has the 'edit_post' capacity for that post.");
        }
        if (!$appful_api->query->nonce) {
            $appful_api->error("You must include a 'nonce' value to update posts. Use the `get_nonce` Core API method.");
        }
        $nonce_id = $appful_api->get_nonce_id('posts', 'update_post');
        if (!wp_verify_nonce($appful_api->query->nonce, $nonce_id)) {
            $appful_api->error("Your 'nonce' value was incorrect. Use the 'get_nonce' API method.");
        }
        nocache_headers();
        $post = new Appful_API_Post($post);
        $post->update($_REQUEST);
        return array(
            'post' => $post
        );
    }

    public function delete_post() {
        global $appful_api;
        $post = $appful_api->introspector->get_current_post();
        if (empty($post)) {
            $appful_api->error("Post not found.");
        }
        if (!current_user_can('edit_post', $post->ID)) {
            $appful_api->error("You need to login with a user that has the 'edit_post' capacity for that post.");
        }
        if (!current_user_can('delete_posts')) {
            $appful_api->error("You need to login with a user that has the 'delete_posts' capacity.");
        }
        if ($post->post_author != get_current_user_id() && !current_user_can('delete_other_posts')) {
            $appful_api->error("You need to login with a user that has the 'delete_other_posts' capacity.");
        }
        if (!$appful_api->query->nonce) {
            $appful_api->error("You must include a 'nonce' value to update posts. Use the `get_nonce` Core API method.");
        }
        $nonce_id = $appful_api->get_nonce_id('posts', 'delete_post');
        if (!wp_verify_nonce($appful_api->query->nonce, $nonce_id)) {
            $appful_api->error("Your 'nonce' value was incorrect. Use the 'get_nonce' API method.");
        }
        nocache_headers();
        wp_delete_post($post->ID);
        return array();
    }

}

?>
