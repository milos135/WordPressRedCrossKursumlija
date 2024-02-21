<?php

function appful_taxonomy_init() {
	$appful_taxonomies = get_taxonomies();
	if (is_array($appful_taxonomies)) {
		foreach ($appful_taxonomies as $appful_taxonomy) {
			add_action($appful_taxonomy.'_add_form_fields', 'appful_taxonomy_add_texonomy_field');
			add_action($appful_taxonomy.'_edit_form_fields', 'appful_taxonomy_edit_texonomy_field');
		}
	}
}


// add image field in add form
function appful_taxonomy_add_texonomy_field() {
	global $appful_api;

	if (get_bloginfo('version') >= 3.5)
		wp_enqueue_media();
	else {
		wp_enqueue_style('thickbox');
		wp_enqueue_script('thickbox');
	}

	echo '<div class="form-field">
		<label for="taxonomy_image">' . $appful_api->localize("taxonomy_image") . '</label>
		<input type="hidden" name="taxonomy_image" id="taxonomy_image" value="" />
		<br/>
		<button class="appful_upload_image_button button">' . $appful_api->localize("taxonomy_image_select") . '</button>
	</div>'.appful_taxonomy_script();
}


// add image field in edit form
function appful_taxonomy_edit_texonomy_field($taxonomy) {
	global $appful_api;

	if (get_bloginfo('version') >= 3.5)
		wp_enqueue_media();
	else {
		wp_enqueue_style('thickbox');
		wp_enqueue_script('thickbox');
	}

	$image_url = appful_taxonomy_image_url( $taxonomy->term_id, NULL);
	echo '<tr class="form-field">
		<th scope="row" valign="top"><label for="taxonomy_image">' . $appful_api->localize("taxonomy_image") . '</label></th>
		<td><img class="taxonomy-image" src="' . appful_taxonomy_image_url( $taxonomy->term_id, 'medium') . '"/><br/><input type="hidden" name="taxonomy_image" id="taxonomy_image" value="'.$image_url.'" /><br />
		<button class="appful_upload_image_button button">' . $appful_api->localize("taxonomy_image_select") . '</button>
		<button class="appful_remove_image_button button">' . $appful_api->localize("taxonomy_image_remove") . '</button>
		</td>
	</tr>'.appful_taxonomy_script();
}


// upload using wordpress upload
function appful_taxonomy_script() {
	return '<script type="text/javascript">
	    jQuery(document).ready(function($) {
			var wordpress_ver = "'.get_bloginfo("version").'", upload_button;
			$(".appful_upload_image_button").click(function(event) {
				upload_button = $(this);
				var frame;
				if (wordpress_ver >= "3.5") {
					event.preventDefault();
					if (frame) {
						frame.open();
						return;
					}
					frame = wp.media();
					frame.on( "select", function() {
						// Grab the selected attachment.
						var attachment = frame.state().get("selection").first();
						frame.close();
						if (upload_button.parent().prev().children().hasClass("tax_list")) {
							upload_button.parent().prev().children().val(attachment.attributes.url);
							upload_button.parent().prev().prev().children().attr("src", attachment.attributes.url);
						}
						else
							$("#taxonomy_image").val(attachment.attributes.url);
					});
					frame.open();
				}
				else {
					tb_show("", "media-upload.php?type=image&amp;TB_iframe=true");
					return false;
				}
			});

			$(".appful_remove_image_button").click(function() {
				$(".taxonomy-image").attr("src", "");
				$("#taxonomy_image").val("");
				$(this).parent().siblings(".title").children("img").attr("src","");
				$(".inline-edit-col :input[name=\'taxonomy_image\']").val("");
				return false;
			});

			if (wordpress_ver < "3.5") {
				window.send_to_editor = function(html) {
					imgurl = $("img",html).attr("src");
					if (upload_button.parent().prev().children().hasClass("tax_list")) {
						upload_button.parent().prev().children().val(imgurl);
						upload_button.parent().prev().prev().children().attr("src", imgurl);
					}
					else
						$("#taxonomy_image").val(imgurl);
					tb_remove();
				}
			}

			$(".editinline").click(function() {
			    var tax_id = $(this).parents("tr").attr("id").substr(4);
			    var thumb = $("#tag-"+tax_id+" .thumb img").attr("src");

				if (thumb != "") {
					$(".inline-edit-col :input[name=\'taxonomy_image\']").val(thumb);
				} else {
					$(".inline-edit-col :input[name=\'taxonomy_image\']").val("");
				}

				$(".inline-edit-col .title img").attr("src",thumb);
			});
	    });
	</script>';
}


// save our taxonomy image while edit or save term
add_action('edit_term', 'appful_taxonomy_save_taxonomy_image');
add_action('create_term', 'appful_taxonomy_save_taxonomy_image');
function appful_taxonomy_save_taxonomy_image($term_id) {
	if (isset($_POST['taxonomy_image']))
		update_option('appful_taxonomy_image'.$term_id, $_POST['taxonomy_image'], NULL);
}


// get attachment ID by image url
function appful_taxonomy_get_attachment_id_by_url($image_src) {
	global $wpdb;
	$query = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid = %s", $image_src);
	$id = $wpdb->get_var($query);
	return (!empty($id)) ? $id : NULL;
}


// get taxonomy image url for the given term_id (Place holder image by default)
function appful_taxonomy_image_url($term_id = NULL, $size = 'full') {
	if (!$term_id) {
		if (is_category())
			$term_id = get_query_var('cat');
		elseif (is_tag())
			$term_id = get_query_var('tag_id');
		elseif (is_tax()) {
			$current_term = get_term_by('slug', get_query_var('term'), get_query_var('taxonomy'));
			$term_id = $current_term->term_id;
		}
	}

	$taxonomy_image_url = get_option('appful_taxonomy_image'.$term_id);
	if (!empty($taxonomy_image_url)) {
		$attachment_id = appful_taxonomy_get_attachment_id_by_url($taxonomy_image_url);
		if (!empty($attachment_id)) {
			$taxonomy_image_url = wp_get_attachment_image_src($attachment_id, $size);
			$taxonomy_image_url = $taxonomy_image_url[0];
		}
	}

	return $taxonomy_image_url;
}


function appful_taxonomy_quick_edit_custom_box($column_name, $screen, $name) {
	global $appful_api;

	if ($column_name == 'thumb')
		echo '<fieldset>
		<div class="thumb inline-edit-col">
			<label>
				<span class="title"><img src="" alt="Thumbnail"/></span>
				<span class="input-text-wrap"><input type="hidden" name="taxonomy_image" value="" class="tax_list" /></span>
				<span class="input-text-wrap">
					<button class="appful_upload_image_button button">' . $appful_api->localize("taxonomy_image_select") . '</button>
					<button class="appful_remove_image_button button">' . $appful_api->localize("taxonomy_image_remove") . '</button>
				</span>
			</label>
		</div>
	</fieldset>';
}


function appful_taxonomy_images($term_id = NULL) {
	global $appful_api;

	if (!$term_id) {
		if (is_category())
			$term_id = get_query_var('cat');
		elseif (is_tag())
			$term_id = get_query_var('tag_id');
		elseif (is_tax()) {
			$current_term = get_term_by('slug', get_query_var('term'), get_query_var('taxonomy'));
			$term_id = $current_term->term_id;
		}
	}

	$taxonomy_image_url = get_option('appful_taxonomy_image'.$term_id);
	if (!empty($taxonomy_image_url)) {
		$attachment_id = appful_taxonomy_get_attachment_id_by_url($taxonomy_image_url);
		if ($attachment_id) {
			if ($appful_api->introspector) {
				$attachment = $appful_api->introspector->get_attachment($attachment_id);
				if ($attachment) {
					$images = $attachment->images;
					if (count($images) > 0) {
						$returnImages = array();
						if (isset($images["full"])) {
							$returnImages["full"] = $images["full"];
						}
						if (isset($images["medium"])) {
							$returnImages["medium"] = $images["medium"];
						}
						if (count($returnImages) > 0) {
							return $returnImages;
						}
					}
				}
			}

			return $images;
		} else {
			return array("full" => array("url" => $taxonomy_image_url, "width" => 10, "height" => 10));
		}
	}
}


?>