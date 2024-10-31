<?php
class RKWPhReviewAdmin
{
	var $parentClass = '';

	function RKWPhReviewAdmin($parentClass) {
            define('IN_RKWPH_ADMIN',1);

            /* begin - haxish but it works */
            $this->parentClass = &$parentClass;
            foreach ($this->parentClass as $col => $val) {
                $this->$col = &$this->parentClass->$col;
            }
            /* end - haxish but it works */
	}

	function real_admin_init() {

            $this->parentClass->init();
            $this->enqueue_admin_stuff();

            register_setting( 'rkwpr_options', 'rkwpr_options' );

            /* used for redirecting to settings page upon initial activation */
            if (get_option('rkwpr_gotosettings', false)) {
                delete_option('rkwpr_gotosettings');
                unregister_setting('rkwpr_gotosettings', 'rkwpr_gotosettings');

                /* no auto settings redirect if upgrading */
                if ( isset($this->p->action) && $this->p->action == 'activate-plugin' ) { return false; }

                $url = get_admin_url().'options-general.php?page=rkwpr_options';
                $this->parentClass->rkwpr_redirect($url);
            }
	}

	function rkwpr_add_meta_box() {
		global $rkwpr_meta_box;

		$prefix = 'rkwpr_';

		$rkwpr_meta_box = array(
		'id' => 'rkwpr-meta-box',
		'title' => 'WP hReview',
		'page' => 'page',
		'context' => 'normal',
		'priority' => 'high',
		'fields' => array(
			array(
				'name' => '<span style="font-weight:bold;">Enable WP hReview</span> for this page',
				'desc' => 'Plugin content will be displayed at the top of your post below the title.',
				'id' => $prefix . 'enable',
				'type' => 'checkbox'
			),
			array(
				'name' => 'Product Name',
				'desc' => ' ',
				'id' => $prefix . 'product_name',
				'type' => 'text',
				'std' => ''
			),
			array(
				'name' => 'Rating',
				'desc' => 'Star rating out of 10',
				'id' => $prefix . 'rating',
				'type' => 'select',
				'options' => array('1','2','3','4','5','6','7','8','9','10'),
				'std' => ''
			),
			array(
				'name' => 'Typical Price',
				'desc' => 'Price with 2 decimal places with an appropiate currency symbol. EG. &pound;100.00 or &pound;100',
				'id' => $prefix . 'typical_price',
				'type' => 'text',
				'std' => ''
			)
		)
	);

		/* add for pages and posts */
		add_meta_box($rkwpr_meta_box['id'], $rkwpr_meta_box['title'], array(&$this, 'rkwpr_show_meta_box'), 'page', $rkwpr_meta_box['context'], $rkwpr_meta_box['priority']);
		add_meta_box($rkwpr_meta_box['id'], $rkwpr_meta_box['title'], array(&$this, 'rkwpr_show_meta_box'), 'post', $rkwpr_meta_box['context'], $rkwpr_meta_box['priority']);
	}

	function real_admin_save_post($post_id) {
            global $rkwpr_meta_box,$wpdb;

            // check autosave
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return $post_id;
            }

            // check permissions
            if ( isset($this->p->post_type) && $this->p->post_type == 'page' ) {
                if (!current_user_can('edit_page', $post_id)) {
                    return $post_id;
                }
            } elseif (!current_user_can('edit_post', $post_id)) {
                return $post_id;
            }

			if ( isset($rkwpr_meta_box) && isset($rkwpr_meta_box['fields']) && is_array($rkwpr_meta_box['fields']) )
			{
				foreach ($rkwpr_meta_box['fields'] as $field) {

					if ( isset($this->p->post_title) ) {
						$old = get_post_meta($post_id, $field['id'], true);

						if (isset($this->p->$field['id'])) {
							$new = $this->p->$field['id'];
							if ($new && $new != $old) {
								update_post_meta($post_id, $field['id'], $new);
							} elseif ($new == '' && $old) {
								delete_post_meta($post_id, $field['id'], $old);
							}
						} else {
							delete_post_meta($post_id, $field['id'], $old);
						}
					}

				}
			}

            return $post_id;
	}

	function rkwpr_show_meta_box() {
		global $rkwpr_meta_box, $post;

		echo '<table class="form-table">';

		foreach ($rkwpr_meta_box['fields'] as $field) {
			// get current post meta data
			$meta = get_post_meta($post->ID, $field['id'], true);

			if ($field['id'] == 'rkwpr_enable' && $post->post_name == '') {
				if ($post->post_type == 'post' && $this->options['enable_posts_default'] == 1) {
					$meta = 1; /* enable by default for posts */
				}
				else if ($post->post_type == 'page' && $this->options['enable_pages_default'] == 1) {
					$meta = 1; /* enable by default for pages */
				}
			}

			echo '<tr>',
				 '<th style="width:30%"><label for="', $field['id'], '">', $field['name'], '</label></th>',
				 '<td>';
			switch ($field['type']) {
				case 'text':
					echo '<input type="text" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['std'], '" size="30" style="width:97%" />', '<br />', $field['desc'];
					break;
				case 'textarea':
					echo '<textarea name="', $field['id'], '" id="', $field['id'], '" cols="60" rows="4" style="width:97%">', $meta ? $meta : $field['std'], '</textarea>', '<br />', $field['desc'];
					break;
				case 'select':
					echo '<select name="', $field['id'], '" id="', $field['id'], '">';
					foreach ($field['options'] as $option) {
						echo '<option', $meta == $option ? ' selected="selected"' : '', '>', $option, '</option>';
					}
					echo '</select>';
					break;
				case 'radio':
					foreach ($field['options'] as $option) {
						echo '<input type="radio" name="', $field['id'], '" value="', $option['value'], '"', $meta == $option['value'] ? ' checked="checked"' : '', ' />', $option['name'];
					}
					break;
				case 'checkbox':
					echo '<input value="1" type="checkbox" name="', $field['id'], '" id="', $field['id'], '"', $meta ? ' checked="checked"' : '', ' />';
					break;
			}
			echo '<td></tr>';
		}

		echo '</table>';
	}

	/* some admin styles can override normal styles for inplace edits */
	function enqueue_admin_stuff() {
            $pluginurl = $this->parentClass->getpluginurl();

            if (isset($this->p->page) && ( $this->p->page == 'rkwpr_view_reviews' || $this->p->page == 'rkwpr_options' ) ) {
				wp_register_style('rk-wp-hreview-admin',$pluginurl.'rk-wp-hreview-admin.css',array(),$this->plugin_version);
				wp_enqueue_script('rk-wp-hreview-admin');
				wp_enqueue_style('rk-wp-hreview-admin');
            }
	}

	/* v4 uuid */
	function gen_uuid() {
            return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
                mt_rand( 0, 0xffff ),
                mt_rand( 0, 0x0fff ) | 0x4000,
                mt_rand( 0, 0x3fff ) | 0x8000,
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
            );
	}

   function update_options() {
       /* we still process and validate this internally, instead of using the Settings API */

       global $wpdb;
       $msg ='';

       $this->security();

       if (isset($this->p->optin))
       {
           if ($this->options['activate'] == 0)
           {
               $this->options['activate'] = 1;
               $this->options['act_email'] = $this->p->email;

               update_option('rkwpr_options', $this->options);
               $msg = 'Thank you. Please configure the plugin below.';
           }
       }
       else
       {
           check_admin_referer('rkwpr_options-options'); /* nonce check */

           $updated_options = $this->options;

           /* reset these to 0 so we can grab the settings below */
           $updated_options['ask_fields']['fname'] = 0;
           $updated_options['ask_fields']['femail'] = 0;
           $updated_options['ask_fields']['fwebsite'] = 0;
           $updated_options['ask_fields']['ftitle'] = 0;
           $updated_options['require_fields']['fname'] = 0;
           $updated_options['require_fields']['femail'] = 0;
           $updated_options['require_fields']['fwebsite'] = 0;
           $updated_options['require_fields']['ftitle'] = 0;
           $updated_options['show_fields']['fname'] = 0;
           $updated_options['show_fields']['femail'] = 0;
           $updated_options['show_fields']['fwebsite'] = 0;
           $updated_options['show_fields']['ftitle'] = 0;
           $updated_options['ask_custom'] = array();
           $updated_options['field_custom'] = array();
           $updated_options['require_custom'] = array();
           $updated_options['show_custom'] = array();

           /* quick update of all options needed */
           foreach ($this->p as $col => $val)
           {
               if (isset($this->options[$col]))
               {
                   switch($col)
                   {
                       case 'field_custom': /* we should always hit field_custom before ask_custom, etc */
                           foreach ($val as $i => $name) { $updated_options[$col][$i] = ucwords( strtolower( $name ) ); } /* we are so special */
                           break;
                       case 'ask_custom':
                       case 'require_custom':
                       case 'show_custom':
                           foreach ($val as $i => $v) { $updated_options[$col][$i] = 1; } /* checkbox array with ints */
                           break;
                       case 'ask_fields':
                       case 'require_fields':
                       case 'show_fields':
                           foreach ($val as $v) { $updated_options[$col]["$v"] = 1; } /* checkbox array with names */
                           break;
                       default:
                           $updated_options[$col] = $val; /* a non-array normal field */
                           break;
                   }
               }
           }

           /* prevent E_NOTICE warnings */
           if (!isset($this->p->enable_pages_default)) { $this->p->enable_pages_default = 0; }
           if (!isset($this->p->enable_posts_default)) { $this->p->enable_posts_default = 0; }
           if (!isset($this->p->goto_show_button)) { $this->p->goto_show_button = 0; }
           if (!isset($this->p->support_us)) { $this->p->support_us = 0; }

           /* some int validation */
           $updated_options['enable_pages_default'] = intval($this->p->enable_pages_default);
           $updated_options['enable_posts_default'] = intval($this->p->enable_posts_default);
           $updated_options['form_location'] = intval($this->p->form_location);
           $updated_options['goto_show_button'] = intval($this->p->goto_show_button);
           $updated_options['reviews_per_page'] = intval($this->p->reviews_per_page);
           $updated_options['show_hcard'] = intval($this->p->show_hcard);
           $updated_options['show_hcard_on'] = intval($this->p->show_hcard_on);
           $updated_options['support_us'] = intval($this->p->support_us);

           if ($updated_options['reviews_per_page'] < 1) { $updated_options['reviews_per_page'] = 10; }

           if ($updated_options['show_hcard_on']) {
               if (
                   empty($updated_options['business_name']) ||
                   empty($updated_options['business_url']) ||
                   empty($updated_options['business_email']) ||
                   empty($updated_options['business_street']) ||
                   empty($updated_options['business_city']) ||
                   empty($updated_options['business_state']) ||
                   empty($updated_options['business_zip']) ||
                   empty($updated_options['business_phone'])
               ) {
                   $msg .= "* Notice: You must enter in ALL business information to use the hCard output *<br /><br />";
                   $updated_options['show_hcard_on'] = 0;
               }
           }

           $msg .= 'Your settings have been saved.';
           update_option('rkwpr_options', $updated_options);
       }

       return $msg;
   }

   function show_activation() {
       echo '
       <div class="postbox" style="width:700px;">
           <h3>Notify me of new releases</h3>
           <div style="padding:10px; background:#ffffff;">
               <p style="color:#060;">If you would like to be notified of any critical security updates, please enter your email address below. Your information will only be used for notification of future releases.</p><br />
               <form method="post" action="">
                   <input type="hidden" name="optin" value="1" />
                   <label for="email">Email Address: </label><input type="text" size="32" id="email" name="email" />&nbsp;
                   <input type="submit" class="button-primary" value="OK!" name="submit" />&nbsp;
                   <input type="submit" class="button-primary" value="No Thanks!" name="submit" />
               </form>
               <p style="color:#BE5409;font-size:14px;font-weight:bold;"><br />Click "OK!" or "No Thanks!" above to access the full plugin settings.</p>
           </div>
       </div>';
   }

   function show_options() {

       $su_checked = '';
       if ($this->options['support_us']) {
           $su_checked = 'checked';
       }

       $enable_posts_checked = '';
       if ($this->options['enable_posts_default']) {
           $enable_posts_checked = 'checked';
       }

       $enable_pages_checked = '';
       if ($this->options['enable_pages_default']) {
           $enable_pages_checked = 'checked';
       }

       $goto_show_button_checked = '';
       if ($this->options['goto_show_button']) {
           $goto_show_button_checked = 'checked';
       }

       $af = array('fname' => '','femail' => '','fwebsite' => '','ftitle' => '');
       if ($this->options['ask_fields']['fname'] == 1) { $af['fname'] = 'checked'; }
       if ($this->options['ask_fields']['femail'] == 1) { $af['femail'] = 'checked'; }
       if ($this->options['ask_fields']['fwebsite'] == 1) { $af['fwebsite'] = 'checked'; }
       if ($this->options['ask_fields']['ftitle'] == 1) { $af['ftitle'] = 'checked'; }

       $rf = array('fname' => '','femail' => '','fwebsite' => '','ftitle' => '');
       if ($this->options['require_fields']['fname'] == 1) { $rf['fname'] = 'checked'; }
       if ($this->options['require_fields']['femail'] == 1) { $rf['femail'] = 'checked'; }
       if ($this->options['require_fields']['fwebsite'] == 1) { $rf['fwebsite'] = 'checked'; }
       if ($this->options['require_fields']['ftitle'] == 1) { $rf['ftitle'] = 'checked'; }

       $sf = array('fname' => '','femail' => '','fwebsite' => '','ftitle' => '');
       if ($this->options['show_fields']['fname'] == 1) { $sf['fname'] = 'checked'; }
       if ($this->options['show_fields']['femail'] == 1) { $sf['femail'] = 'checked'; }
       if ($this->options['show_fields']['fwebsite'] == 1) { $sf['fwebsite'] = 'checked'; }
       if ($this->options['show_fields']['ftitle'] == 1) { $sf['ftitle'] = 'checked'; }

       echo '
       <div class="postbox" style="width:700px;">
           <h3>Display Options</h3>
           <div id="rkwpr_ad">
               <div style="background:#eaf2fa;padding:6px;border-top:1px solid #ccc;border-bottom:1px solid #ccc;">
                   <legend>Tips</legend>
               </div>
               <div style="padding:10px;">
                   How to use: <small>When adding/editing any post/page, you have a setting block on the page for WP hReview. If you enable the plugin for that post, it will then use the default options set on this page.</small>
                   <br /><br />
                   Shortcodes: <small>The following shortcodes can be used in the page/post content of any page. These codes will not work when placed directly in a theme template file, since Wordpress does not parse their content. Shortcode features are in beta testing.</small>
                   <br /><br />
                   [RKWPH_INSERT] <small>is available for you to use on any page/post. Simply include [RKWPH_INSERT] in the content of the post where you would like the reviews/form output to be displayed. If this code is found, the plugin will automatically enable itself for the post.</small>

               </div>
               <form method="post" action="">

                   <div style="background:#eaf2fa;padding:6px;border-top:1px solid #ccc;border-bottom:1px solid #ccc;">
                       <legend>General Settings</legend>
                   </div>
                       <small>If using the "Product" type, you can enter the product name in the "WP Customer Reviews" box when editing your pages. If this is set to "Business", the plugin will present all reviews as if they are reviews of your business as listed above.</small>
                       <br /><br />
                       <input id="enable_posts_default" name="enable_posts_default" type="checkbox" '.$enable_posts_checked.' value="1" />&nbsp;<label for="enable_posts_default"><small>Enable the plugin by default for new posts.</small></label>
                       <br /><br />
                       <input id="enable_pages_default" name="enable_pages_default" type="checkbox" '.$enable_pages_checked.' value="1" />&nbsp;<label for="enable_pages_default"><small>Enable the plugin by default for new pages.</small></label>
                       <br /><br />
                       <input id="support_us" name="support_us" type="checkbox" '.$su_checked.' value="1" />&nbsp;<label for="support_us"><small>Support our work and keep this plugin free. By checking this box, a small "Powered by WP hReview" link will be placed at the bottom of pages that use the plugin.</small></label>
                       <br />
                       <div class="submit" style="padding:10px 0px 0px 0px;"><input type="submit" class="button-primary" value="Save Changes" name="Submit"></div>
                   </div>
                   <div style="background:#eaf2fa;padding:6px;border-top:1px solid #ccc;border-bottom:1px solid #ccc;">
                       <legend>Advanced</legend>
                   </div>
                   <div style="padding:10px;padding-bottom:0px;">
                       <small><span style="color:#c00;">Be very careful when using these options. They should do exactly what they say, but are experimental, so use them at your own risk. Most users do not need to even think about using these options, but they are here in case you need them.</span></small>
                       <br /><br />
                       <div class="submit" style="padding:10px 0px 0px 0px;"><input type="submit" class="button-primary" value="Enable Plugin for all Existing Posts" name="Submit"></div>
                       <div class="submit" style="padding:10px 0px 0px 0px;"><input type="submit" class="button-primary" value="Disable Plugin for all Existing Posts" name="Submit"></div>
                       <div class="submit" style="padding:10px 0px 0px 0px;"><input type="submit" class="button-primary" value="Enable Plugin for all Existing Pages" name="Submit"></div>
                       <div class="submit" style="padding:10px 0px 0px 0px;"><input type="submit" class="button-primary" value="Disable Plugin for all Existing Pages" name="Submit"></div>
                   </div>';
                   settings_fields("rkwpr_options");
                   echo '
               </form>
               <br />
           </div>
       </div>';
       /* settings_fields is for Settings API / WPMU / future WP compatibility */
   }

   function security() {
       if (!current_user_can('manage_options'))
       {
           wp_die( __('You do not have sufficient permissions to access this page.') );
       }
   }

   function real_admin_options() {
        $this->security();

        $msg = '';

		// make sure the db is created
		global $wpdb;
		$exists = $wpdb->get_var("SHOW TABLES LIKE '$this->dbtable'");
		if ($exists != $this->dbtable) {
			$this->parentClass->check_migrate(true);
			$exists = $wpdb->get_var("SHOW TABLES LIKE '$this->dbtable'");
			if ($exists != $this->dbtable) {
				print "<br /><br /><br />COULD NOT CREATE DATABASE TABLE, PLEASE REPORT THIS ERROR";
			}
		}

        if (!isset($this->p->Submit)) { $this->p->Submit = ''; }

        if ($this->p->Submit == 'Save Changes') {
            $msg = $this->update_options();
            $this->parentClass->get_options();
        }
        elseif ($this->p->Submit == 'Enable Plugin for all Existing Posts') {
            global $wpdb;
            $wpdb->query( "DELETE $wpdb->postmeta FROM $wpdb->postmeta
                            LEFT JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->postmeta.post_id
                            WHERE $wpdb->posts.post_type = 'post' AND $wpdb->postmeta.meta_key = 'rkwpr_enable' " );

            $wpdb->query( "INSERT INTO $wpdb->postmeta
                            SELECT 0,$wpdb->posts.ID,'rkwpr_enable',1
                            FROM $wpdb->posts
                            WHERE $wpdb->posts.post_type = 'post' " );
        }
        elseif ($this->p->Submit == 'Disable Plugin for all Existing Posts') {
            global $wpdb;
            $wpdb->query( "DELETE $wpdb->postmeta FROM $wpdb->postmeta
                            LEFT JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->postmeta.post_id
                            WHERE $wpdb->posts.post_type = 'post' AND $wpdb->postmeta.meta_key = 'rkwpr_enable' " );
        }
        elseif ($this->p->Submit == 'Enable Plugin for all Existing Pages') {
            global $wpdb;
            $wpdb->query( "DELETE $wpdb->postmeta FROM $wpdb->postmeta
                            LEFT JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->postmeta.post_id
                            WHERE $wpdb->posts.post_type = 'page' AND $wpdb->postmeta.meta_key = 'rkwpr_enable' " );

            $wpdb->query( "INSERT INTO $wpdb->postmeta
                            SELECT 0,$wpdb->posts.ID,'rkwpr_enable',1
                            FROM $wpdb->posts
                            WHERE $wpdb->posts.post_type = 'page' " );
        }
        elseif ($this->p->Submit == 'Disable Plugin for all Existing Pages') {
            global $wpdb;
            $wpdb->query( "DELETE $wpdb->postmeta FROM $wpdb->postmeta
                            LEFT JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->postmeta.post_id
                            WHERE $wpdb->posts.post_type = 'page' AND $wpdb->postmeta.meta_key = 'rkwpr_enable' " );
        }

        if (isset($this->p->email)) {
            $msg = $this->update_options();
            $this->parentClass->get_options();
        }

        echo '
        <div id="rkwpr_respond_1" class="wrap">
            <h2>WP hReview - Options</h2>';
            if ($msg) { echo '<h3 style="color:#a00;">'.$msg.'</h3>'; }
            echo '
            <div class="metabox-holder">
            <div class="postbox" style="width:700px;">
                <h3 style="cursor:default;">About WP hReview</h3>
                <div style="padding:0 10px; background:#ffffff;">
                    <p>
                        Version: <strong>'.$this->plugin_version.'</strong><br /><br />
                        WP hReview allows you to post product reviews using WordPress that Google can index as reviews and show stars so you stand out in results.
                    </p>
                    <br />
                </div>
                <div style="padding:6px; background:#eaf2fa;">
                    Plugin Homepage: <a target="_blank" href="http://www.robertmkelly.co.uk/plugins/rk-wp-hreview/">http://www.robertmkelly.co.uk/plugins/rk-wp-hreview/</a><br /><br />
                    Support Email: <a href="mailto:wp@robertmkelly.co.uk">wp@robertmkelly.co.uk</a><br /><br />
                </div>
            </div>';

        //if ($this->options['activate'] == 0) {
            //$this->show_activation();
           // echo '<br /></div>';
            //return;
      //  }

        $this->show_options();
        echo '<br /></div>';
    }

}

if (!defined('IN_RKWPH_ADMIN')) {
    global $RKWPhReview, $RKWPhReviewAdmin;
    $RKWPhReviewAdmin = new RKWPhReviewAdmin($RKWPhReview);
}
?>