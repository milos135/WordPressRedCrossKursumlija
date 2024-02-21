<?php
header("Access-Control-Allow-Origin: *");
$_SERVER["HTTP_USER_AGENT"] = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10) AppleWebKit/600.1.15 (KHTML, like Gecko) Version/8.0 Safari/600.1.15";
/* Don't remove this line. */
define('WP_USE_THEMES', false);
require_once('../../../../wp-config.php');
$wp->init(); $wp->parse_request(); $wp->query_posts();
$wp->register_globals(); $wp->send_headers();
get_header();
?>
<body>
<?php
get_poll($_GET['id']);
?>

<style>
.bar-nav {
	display: none;
}
.bar-nav ~ .content {
	padding-top: 0px!important;
}
body {
	background: transparent;
}
</style>

<script type='text/javascript'>
	/* <![CDATA[ */
	var pollsL10n = {"ajax_url":"<?php echo addslashes(get_option("siteurl", "")) ?>\/wp-admin\/admin-ajax.php","text_wait":"Your last request is still being processed. Please wait a while ...","text_valid":"Please choose a valid poll answer.","text_multiple":"Maximum number of choices allowed: ","show_loading":"1","show_fading":"1"};
	/* ]]> */
</script>
<script type='text/javascript' src='<?php echo get_option("siteurl", "") ?>/wp-content/plugins/wp-polls/polls-js.js?ver=2.63'></script>
</body>
</html>