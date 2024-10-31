<?php
/*
   Plugin Name: hReview for WordPress
   Plugin URI: www.robertmkelly.co.uk
   Description: Adds hReview markup to any post allowing rich snippets to show in Google.
   Version: 1.1.1
   Author: Robert Kelly
   Author URI: www.robertmkelly.co.uk
   Author Email: wp@robertmkelly.co.uk
   License: GPLv2 or later

   Copyright 2013 Robert Kelly (wp@robertmkelly.co.uk)

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License, version 2, as
   published by the Free Software Foundation.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

class RKWPhReview {

	var $dbtable = 'rkwphreview';
	var $options = array();
	var $p = '';
	var $page = 1;
	var $plugin_version = '1.1.1';
	var $status_msg = '';

	function RKWPhReview() {
		global $wpdb;

		define('IN_RKWPH', 1);

		$this->dbtable = $wpdb->prefix . $this->dbtable;

		add_action('the_content', array(&$this, 'do_the_content'), 10); /* prio 10 prevents a conflict with some odd themes */
		add_action('init', array(&$this, 'init')); /* init also tries to insert script/styles */
		add_action('admin_init', array(&$this, 'admin_init'));
		add_action('template_redirect',array(&$this, 'template_redirect')); /* handle redirects and form posts, and add style/script if needed */
		add_action('admin_menu', array(&$this, 'addmenu'));
		add_action('save_post', array(&$this, 'admin_save_post'), 10, 2); /* 2 arguments */
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'plugin_settings_link'));
	}

	/* keep out of admin file */
	function plugin_settings_link($links) {
		$url = get_admin_url().'options-general.php?page=rkwpr_options';
		//$settings_link = '<a href="'.$url.'"><img src="' . $this->getpluginurl() . 'star.png" />&nbsp;Settings</a>';
		$settings_link = '<a href="'.$url.'">Settings</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	/* keep out of admin file */
	function addmenu() {
		//add_options_page('WP hReview', '<img src="' . $this->getpluginurl() . 'star.png" />&nbsp;Customer Reviews', 'manage_options', 'rkwpr_options', array(&$this, 'admin_options'));
		add_options_page('WP hReview', 'WP hReview', 'manage_options', 'rkwpr_options', array(&$this, 'admin_options'));
		//add_menu_page('Customer Reviews', 'Customer Reviews', 'edit_others_posts', 'rkwpr_view_reviews', array(&$this, 'admin_view_reviews'), $this->getpluginurl() . 'star.png', 50); /* 50 should be underneath comments */

		global $RKWPhReviewAdmin;
		$this->include_admin(); /* include admin functions */
		$RKWPhReviewAdmin->rkwpr_add_meta_box();
	}

	/* forward to admin file */
	function admin_options() {
		global $RKWPhReviewAdmin;
		$this->include_admin(); /* include admin functions */
		$RKWPhReviewAdmin->real_admin_options();
	}

	/* forward to admin file */
	function admin_save_post($post_id, $post) {
		global $RKWPhReviewAdmin;
		$this->include_admin(); /* include admin functions */
		$RKWPhReviewAdmin->real_admin_save_post($post_id);
	}

	function get_options() {
		$home_domain = @parse_url(get_home_url());
		$home_domain = $home_domain['scheme'] . "://" . $home_domain['host'] . '/';

		$default_options = array(
		    'act_email' => '',
		    'act_uniq' => '',
		    'activate' => 0,
		    'ask_custom' => array(),
		    'ask_fields' => array('fname' => 1, 'femail' => 1, 'fwebsite' => 1, 'ftitle' => 1, 'fage' => 0, 'fgender' => 0),
		    'business_city' => '',
		    'business_country' => 'USA',
		    'business_email' => get_bloginfo('admin_email'),
		    'business_name' => get_bloginfo('name'),
		    'business_phone' => '',
		    'business_state' => '',
		    'business_street' => '',
		    'business_url' => $home_domain,
		    'business_zip' => '',
		    'dbversion' => 0,
		    'enable_posts_default' => 0,
		    'enable_pages_default' => 0,
		    'field_custom' => array(),
		    'form_location' => 0,
		    'goto_leave_text' => 'Click here to submit your review.',
		    'goto_show_button' => 1,
		    'hreview_type' => 'business',
		    'leave_text' => 'Submit your review',
		    'require_custom' => array(),
		    'require_fields' => array('fname' => 1, 'femail' => 1, 'fwebsite' => 0, 'ftitle' => 0, 'fage' => 0, 'fgender' => 0),
		    'reviews_per_page' => 10,
		    'show_custom' => array(),
		    'show_fields' => array('fname' => 1, 'femail' => 0, 'fwebsite' => 0, 'ftitle' => 1, 'fage' => 0, 'fgender' => 0),
		    'show_hcard' => 1,
		    'show_hcard_on' => 1,
		    'submit_button_text' => 'Submit your review',
		    'support_us' => 1,
		    'title_tag' => 'h2'
		);

		$this->options = get_option('rkwpr_options', $default_options);

		/* magically easy migrations to newer versions */
		$has_new = false;
		foreach ($default_options as $col => $def_val) {

			if (!isset($this->options[$col])) {
				$this->options[$col] = $def_val;
				$has_new = true;
			}

			if (is_array($def_val)) {
				foreach ($def_val as $acol => $aval) {
					if (!isset($this->options[$col][$acol])) {
						$this->options[$col][$acol] = $aval;
						$has_new = true;
					}
				}
			}
		}

		if ($has_new) {
			update_option('rkwpr_options', $this->options);
		}
	}

	function make_p_obj() {
		$this->p = new stdClass();

		foreach ($_GET as $c => $val) {
			if (is_array($val)) {
				$this->p->$c = $val;
			} else {
				$this->p->$c = trim(stripslashes($val));
			}
		}

		foreach ($_POST as $c => $val) {
			if (is_array($val)) {
				$this->p->$c = $val;
			} else {
				$this->p->$c = trim(stripslashes($val));
			}
		}
	}

	function check_migrate() {
		global $wpdb;
		$migrated = false;

		/* remove me after official release */
		$current_dbversion = intval(str_replace('.', '', $this->options['dbversion']));
		$plugin_db_version = intval(str_replace('.', '', $this->plugin_version));

		if ($current_dbversion == $plugin_db_version) {
			return false;
		}

		global $RKWPhReviewAdmin;
		$this->include_admin(); /* include admin functions */

		/* initial installation */
		if ($current_dbversion == 0) {
			$this->options['dbversion'] = $plugin_db_version;
			$current_dbversion = $plugin_db_version;
			update_option('rkwpr_options', $this->options);
			return false;
		}

		/* check for upgrades if needed */

		/* upgrade to 2.0.0 */
		if ($current_dbversion < 200) {
			/* add multiple page support to database */

			/* change all current reviews to use the selected page id */
			$pageID = intval($this->options['selected_pageid']);
			$wpdb->query("UPDATE `$this->dbtable` SET `page_id`=$pageID WHERE `page_id`=0");

			/* add new meta to existing selected page */
			update_post_meta($pageID, 'rkwpr_enable', 1);

			$this->options['dbversion'] = 200;
			$current_dbversion = 200;
			update_option('rkwpr_options', $this->options);
			$migrated = true;
		}

		/* done with all migrations, push dbversion to current version */
		if ($current_dbversion != $plugin_db_version || $migrated == true) {
			$this->options['dbversion'] = $plugin_db_version;
			$current_dbversion = $plugin_db_version;
			update_option('rkwpr_options', $this->options);

			global $RKWPhReviewAdmin;
			$this->include_admin(); /* include admin functions */

			return true;
		}

		return false;
	}

	function is_active_page() {
		global $post;

		$has_shortcode = $this->force_active_page;
		if ( $has_shortcode !== false ) {
			return 'shortcode';
		}

		if ( !isset($post) || !isset($post->ID) || intval($post->ID) == 0 ) {
			return false; /* we can only use the plugin if we have a valid post ID */
		}

		if (!is_singular()) {
			return false; /* not on a single post/page view */
		}

		$rkwpr_enabled_post = get_post_meta($post->ID, 'rkwpr_enable', true);
		if ( $rkwpr_enabled_post ) {
			return 'enabled';
		}

		return false;
	}

	function add_style_script() {
		/* to prevent compatibility issues and for shortcodes, add to every page */
		wp_enqueue_style('rk-wp-hreview');
		wp_enqueue_script('rk-wp-hreview');
	}

	function template_redirect() {

		/* do this in template_redirect so we can try to redirect cleanly */
		global $post;
		if (!isset($post) || !isset($post->ID)) {
			$post = new stdClass();
			$post->ID = 0;
		}

		if (isset($_COOKIE['rkwpr_status_msg'])) {
			$this->status_msg = $_COOKIE['rkwpr_status_msg'];
			if ( !headers_sent() ) {
				setcookie('rkwpr_status_msg', '', time() - 3600); /* delete the cookie */
				unset($_COOKIE['rkwpr_status_msg']);
			}
		}

		$GET_P = "submitrkwpr_$post->ID";

		if ($post->ID > 0 && isset($this->p->$GET_P) && $this->p->$GET_P == $this->options['submit_button_text'])
		{
			//$msg = $this->add_review($post->ID);
			$has_error = $msg[0];
			$status_msg = $msg[1];
			$url = get_permalink($post->ID);
			$cookie = array('rkwpr_status_msg' => $status_msg);
			$this->rkwpr_redirect($url, $cookie);
		}
	}

	function rand_string($length) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$str = '';

		$size = strlen($chars);
		for ($i = 0; $i < $length; $i++) {
			$str .= $chars[rand(0, $size - 1)];
		}

		return $str;
	}

	function iso8601($time=false) {
		if ($time === false)
			$time = time();
		$date = date('Y-m-d\TH:i:sO', $time);
		return (substr($date, 0, strlen($date) - 2) . ':' . substr($date, -2));
	}

	function pagination($total_results, $reviews_per_page) {
		global $post; /* will exist if on a post */

		$out = '';
		$uri = false;
		$pretty = false;

		$range = 2;
		$showitems = ($range * 2) + 1;

		$paged = $this->page;
		if ($paged == 0) { $paged = 1; }

		if (!isset($this->p->review_status)) { $this->p->review_status = 0; }

		$pages = ceil($total_results / $reviews_per_page);

		if ($pages > 1) {
			if (is_admin()) {
				$url = '?page=rkwpr_view_reviews&amp;review_status=' . $this->p->review_status . '&amp;';
			} else {
				$uri = trailingslashit(get_permalink($post->ID));
				if (strpos($uri, '?') === false) {
					$url = $uri . '?';
					$pretty = true;
				} /* page is using pretty permalinks */ else {
					$url = $uri . '&amp;';
					$pretty = false;
				} /* page is using get variables for pageid */
			}

			$out .= '<div id="rkwpr_pagination"><div id="rkwpr_pagination_page">Page: </div>';

			if ($paged > 2 && $paged > $range + 1 && $showitems < $pages) {
				if ($uri && $pretty) {
					$url2 = $uri;
				} /* not in admin AND using pretty permalinks */ else {
					$url2 = $url;
				}
				$out .= '<a href="' . $url2 . '">&laquo;</a>';
			}

			if ($paged > 1 && $showitems < $pages) {
				$out .= '<a href="' . $url . 'rkwph=' . ($paged - 1) . '">&lsaquo;</a>';
			}

			for ($i = 1; $i <= $pages; $i++) {
				if ($i == $paged) {
					$out .= '<span class="rkwpr_current">' . $paged . '</span>';
				} else if (!($i >= $paged + $range + 1 || $i <= $paged - $range - 1) || $pages <= $showitems) {
					if ($i == 1) {
						if ($uri && $pretty) {
							$url2 = $uri;
						} /* not in admin AND using pretty permalinks */ else {
							$url2 = $url;
						}
						$out .= '<a href="' . $url2 . '" class="rkwpr_inactive">' . $i . '</a>';
					} else {
						$out .= '<a href="' . $url . 'rkwph=' . $i . '" class="rkwpr_inactive">' . $i . '</a>';
					}
				}
			}

			if ($paged < $pages && $showitems < $pages) {
				$out .= '<a href="' . $url . 'rkwph=' . ($paged + 1) . '">&rsaquo;</a>';
			}
			if ($paged < $pages - 1 && $paged + $range - 1 < $pages && $showitems < $pages) {
				$out .= '<a href="' . $url . 'rkwph=' . $pages . '">&raquo;</a>';
			}
			$out .= '</div>';
			$out .= '<div class="rkwpr_clear rkwpr_pb5"></div>';

			return $out;
		}
	}

	/* trims text, but does not break up a word */
	function trim_text_to_word($text,$len) {
		if(strlen($text) > $len) {
			$matches = array();
			preg_match("/^(.{1,$len})[\s]/i", $text, $matches);
			$text = $matches[0];
		}
		return $text.'... ';
	}

	function do_the_content($original_content) {
		global $post;

		$hReviewEnabled = get_post_meta( $post->ID, 'rkwpr_enable', true );

		/* return normal content if this is not an enabled page, or if this is a post not on single post view */
		if (!$hReviewEnabled || $hReviewEnabled == false) {
			return $original_content . $the_content;
		}

		$using_shortcode_insert = false;
		if ($original_content == 'shortcode_insert') {
			$original_content = '';
			$using_shortcode_insert = true;
		}

		$the_content = '';
		$is_active_page = $this->is_active_page();

		/* return normal content if this is not an enabled page, or if this is a post not on single post view */
		if (!$is_active_page) {
			return $original_content . $the_content;
		}

		/* Compile the snippet. */
		$hReviewRating = get_post_meta( $post->ID, 'rkwpr_rating', true );
		$hReviewTypicalPrice = get_post_meta( $post->ID, 'rkwpr_typical_price', true );
		$hReviewPostTitle = get_post_meta( $post->ID, 'rkwpr_product_name', true );
		$hReviewPostDate = $post->post_date;

		//$hReviewPostAuthorID = $post->post_author;

		$the_content .= '<div id="rkwpr_respond_1" class="hReview">'; /* start the div */
		$inside_div = true;

		if($hReviewPostTitle != "" && $hReviewRating != "" && $hReviewPostDate != '')
		{
			$the_content .= 'The <span class="item"><span class="fn">'.$hReviewPostTitle.'</span></span>';
			$the_content .= '<span class="date">';
				$the_content .= ' was given a rating of <span class="rating"><span class="value"><span class="value-title" title="'.$hReviewRating.'"></span>'.$hReviewRating.'<span class="best"><span class="value-title" title="10"></span></span>/10';
				$the_content .= ' By <span class="reviewer">'.get_the_author().'</span>';
				$the_content .= ' on <span class="dtreviewed">'.get_the_date('j F Y',$hReviewPostDate).'<span class="value-title" title="'.get_the_date('Y-m-d',$hReviewPostDate).'"></span></span>';
				if($hReviewTypicalPrice!="")
				{
					$the_content .= '. Typical price: <span class="pricerange">'.$hReviewTypicalPrice.'</span>';
				}
			$the_content .= '</span>.';
		}

		if ($this->options['support_us'] == 1) {
			//$the_content .= '<div class="rkwpr_clear rkwpr_power">Powered by <strong><a href="#">WP hReview</a></strong></div>';
		}

		$the_content .= '</div>'; /* rkwpr_respond_1 */

		//$the_content = preg_replace('/\n\r|\r\n|\n|\r|\t|\s{2}/', '', $the_content); /* minify to prevent automatic line breaks */
		$the_content = preg_replace('/\n\r|\r\n|\n|\r|\t/', '', $the_content); /* minify to prevent automatic line breaks, not removing double spaces */

		return $original_content . $the_content;
	}

	function deactivate() {
		/* do not fire on upgrading plugin or upgrading WP - only on true manual deactivation */
		if (isset($this->p->action) && $this->p->action == 'deactivate') {
			$this->options['activate'] = 0;
			update_option('rkwpr_options', $this->options);
			global $RKWPhReviewAdmin;
			$this->include_admin(); /* include admin functions */
		}
	}

	function rkwpr_redirect($url, $cookie = array()) {

		$headers_sent = headers_sent();

		if ($headers_sent == true) {
			/* use JS redirect and add cookie before redirect */
			/* we do not html comment script blocks here - to prevent any issues with other plugins adding content to newlines, etc */
			$out = "<html><head><title>Redirecting...</title></head><body><div style='clear:both;text-align:center;padding:10px;'>" .
			        "Processing... Please wait..." .
			        "<script type='text/javascript'>";
			foreach ($cookie as $col => $val) {
				$val = preg_replace("/\r?\n/", "\\n", addslashes($val));
				$out .= "document.cookie=\"$col=$val\";";
			}
			$out .= "window.location='$url';";
			$out .= "</script>";
			$out .= "</div></body></html>";
			echo $out;
		} else {
			foreach ($cookie as $col => $val) {
				setcookie($col, $val); /* add cookie via headers */
			}
			ob_end_clean();
			wp_redirect($url); /* nice redirect */
		}

		exit();
	}

	function init() { /* used for admin_init also */
		$this->make_p_obj(); /* make P variables object */
		$this->get_options(); /* populate the options array */
		$this->check_migrate(); /* call on every instance to see if we have upgraded in any way */

		if ( !isset($this->p->rkwph) ) { $this->p->rkwph = 1; }

		$this->page = intval($this->p->rkwph);
		if ($this->page < 1) { $this->page = 1; }

		add_shortcode( 'RKWPH_INSERT', array(&$this, 'shortcode_rkwpr_insert') );
		add_shortcode( 'RKWPH_SHOW', array(&$this, 'shortcode_rkwpr_show') );

		wp_register_style('rk-wp-hreview', $this->getpluginurl() . 'css/rk-wp-hreview.css', array(), $this->plugin_version);
		/* add style and script here if needed for some theme compatibility */
		$this->add_style_script();
	}

	function shortcode_rkwpr_insert() {
		$this->force_active_page = 1;
		return $this->do_the_content('shortcode_insert');
	}

	function shortcode_rkwpr_show($atts) {
		$this->force_active_page = 1;

		extract( shortcode_atts( array('postid' => 'all','num' => '3','hidecustom' => '0','hideresponse' => '0', 'snippet' => '0','more' => ''), $atts ) );

		if (strtolower($postid) == 'all') { $postid = -1; /* -1 queries all reviews */ }
		$postid = intval($postid);
		$num = intval($num);
		$hidecustom = intval($hidecustom);
		$hideresponse = intval($hideresponse);
		$snippet = intval($snippet);
		$more = $more;

		if ($postid < -1) { $postid = -1; }
		if ($num < 1) { $num = 3; }
		if ($hidecustom < 0 || $hidecustom > 1) { $hidecustom = 0; }
		if ($hideresponse < 0 || $hideresponse > 1) { $hideresponse = 0; }
		if ($snippet < 0) { $snippet = 0; }

		$inside_div = false;

		//$ret_Arr = $this->output_reviews_show( $inside_div, $postid, $num, $num, $hidecustom, $hideresponse, $snippet, $more );
		return $ret_Arr[0];
	}

	function activate() {
		register_setting('rkwpr_gotosettings', 'rkwpr_gotosettings');
		add_option('rkwpr_gotosettings', true); /* used for redirecting to settings page upon initial activation */
	}

	function include_admin() {
		global $RKWPhReviewAdmin;
		require_once($this->getplugindir() . 'rk-wp-hreview-admin.php'); /* include admin functions */
	}

	function admin_init() {
		global $RKWPhReviewAdmin;
		$this->include_admin(); /* include admin functions */
		$RKWPhReviewAdmin->real_admin_init();
	}

	function getpluginurl() {
		return trailingslashit(plugins_url(basename(dirname(__FILE__))));
	}

	function getplugindir() {
		return trailingslashit(WP_PLUGIN_DIR . '/' . str_replace(basename(__FILE__), "", plugin_basename(__FILE__)));
	}

}

if (!defined('IN_RKWPH')) {
	global $RKWPhReview;
	$RKWPhReview = new RKWPhReview();
	register_activation_hook(__FILE__, array(&$RKWPhReview, 'activate'));
	register_deactivation_hook(__FILE__, array(&$RKWPhReview, 'deactivate'));
}