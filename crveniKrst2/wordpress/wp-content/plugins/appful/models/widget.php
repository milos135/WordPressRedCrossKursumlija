<?php

class Appful_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		$var = explode("_", get_locale());
		parent::__construct(
			'appful', // Base ID
			__( 'appful', 'text_domain' ), // Name
			array( 'description' => $var[0] == "de" ? "Mache Leser auf deine App aufmerksam." : "Draw the readers attention on your App.") // Args
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		global $appful_api;

		$apps = $appful_api->response->decode_json(get_option("appful_widget_apps"));
		if(!$instance["app_id"] && count($apps) > 0) {
			$instance["app_id"] = $apps[0]["id"];
		}
		if(!($instance["app_id"] > 0)) return;

		$branding = $appful_api->response->decode_json(get_option("appful_widget_branding"));
		if(!$branding) $branding = array("title" => "App by appful", "url" => "https://appful.io");

		foreach($apps as $entry) {
			if($entry["id"] == $instance["app_id"]) {
				$app = $entry;
				break;
			}
		}
		if(!$app) return;

		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ). $args['after_title'];
		}
		?>
		<link href="<?php echo plugins_url("assets/css/appful-widget.css", dirname(__FILE__)) ?>" rel="stylesheet">
		<div class="af-widget-wrap" id="af-<?php echo $instance["size"] == 1 ? "small" : "big" ?>">
			<div class="af-app-icon">
				<img src="<?php echo $app["thumbnails"]["120"] ?>" alt="App Icon" />
			</div>
			<div class="af-meta-box af-<?php echo $app["itunes_id"] && $app["packet_name"] ? "dual" : "single" ?>">
				<h3><?php echo $app["bundle_name"] ?></h3>
				<p><?php echo $instance["description"] ?></p>
				<?php if($branding && $app["branding"]) { ?><a href="<?php echo $branding["url"] ?>" class="af-credit" target="_blank"><?php echo $branding["title"] ?></a><?php } ?>
				<?php if($app["itunes_id"]) { ?><a href="http://itunes.apple.com/<?php echo $appful_api->locale() ?>/app/id<?php echo $app["itunes_id"] ?>" target="_blank"><img class="af-app-store-button" src="<?php echo plugins_url("assets/img/app-store-button.png", dirname(__FILE__)) ?>" alt="App Store Button" width="" height="" /></a><?php } ?>
				<?php if($app["packet_name"]) { ?><a href="https://play.google.com/store/apps/details?id=<?php echo $app["packet_name"] ?>" target="_blank"><img class="af-app-store-button" src="<?php echo plugins_url("assets/img/play-store-button.png", dirname(__FILE__)) ?>" alt="Play Store Button" width="" height="" /></a><?php } ?><br>
			</div>
			<?php if($branding && $app["branding"]) { ?><br><?php if(strlen($instance["description"]) > 0) { ?><br><?php } } ?>
		</div>
		<?php
		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		global $appful_api;
		$title = ! empty( $instance['title'] ) ? $instance['title'] : "App";
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>">Titel:</label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>

			<?php
			$apps = $appful_api->response->decode_json(get_option("appful_widget_apps"));
			if(count($apps) == 0) {
				?><label><?php echo $appful_api->localize("error_no_published_app") ?></label><?php
			} else {
			?>
			<?php if(count($apps) == 1) {
				$app = $apps[0];
			?>
				<input type="hidden" id="<?php echo $this->get_field_id('app_id'); ?>" name="<?php echo $this->get_field_name('app_id'); ?>" value="<?php echo $app["id"] ?>">
			<?php } else { ?>
			<p>
			<label for="<?php echo $this->get_field_id('app_id'); ?>"><?php echo $appful_api->localize("select_app") ?>:</label>
			<select id="<?php echo $this->get_field_id('app_id'); ?>" name="<?php echo $this->get_field_name('app_id'); ?>">
				<?php
				for($i = 0; $i < count($apps)+1; $i++) {
					if($i == 0) {
						?><option value="0"> — <?php echo $appful_api->localize("select") ?> — </option><?php
					} else {
						$app = $apps[$i-1];
						?><option value="<?php echo $app["id"] ?>"<?php echo $instance["app_id"] == $app["id"] ? " selected" : "" ?>><?php echo $app["bundle_name"] ?></option><?php
					}
				} ?>
			</select>
			</p>
			<?php }
			} ?>
		<p>
			<label for="<?php echo $this->get_field_id('description'); ?>"><?php echo $appful_api->localize("description") ?>:</label>
			<input class="widefat" id="<?php echo $this->get_field_id('description'); ?>" name="<?php echo $this->get_field_name('description'); ?>" type="text" value="<?php echo esc_attr($instance["description"]) ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('size'); ?>"><?php echo $appful_api->localize("size") ?>:</label>
			<select id="<?php echo $this->get_field_id('size'); ?>" name="<?php echo $this->get_field_name('size'); ?>">
				<option value="1"<?php echo $instance["size"] == 1 ? " selected" : "" ?>><?php echo $appful_api->localize("size_small") ?></option>
				<option value="2"<?php echo $instance["size"] != 1 ? " selected" : "" ?>><?php echo $appful_api->localize("size_large") ?></option>
			</select>
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update($new_instance, $old_instance) {
		$instance = $new_instance;
		if(strlen($instance['title']) == 0) unset($instance['title']);
		if(!($instance["app_id"] > 0)) unset($instance["app_id"]);

		return $instance;
	}

} // class Foo_Widget