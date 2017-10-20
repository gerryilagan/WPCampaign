<?php

class Wpcampaign_ActiveCampaign {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wpcampaign_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wpcampaign_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wpcampaign-activecampaign.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wpcampaign_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wpcampaign_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wpcampaign-activecampaign.js', array( 'jquery' ), $this->version, false );

	}
	
	function register_shortcodes() {
	  add_shortcode("activecampaign", array($this, "shortcodes"));
	}

	function shortcodes($args) {
		// check for Settings options saved first.
		$settings = get_option("settings_activecampaign");
		if ($settings) {
			if (isset($settings["forms"]) && $settings["forms"]) {
				if (isset($args) && isset($args["form"])) {
					$form_id = $args["form"];
					$form = $settings["forms"][$form_id];
					$form_source = $this->activecampaign_form_source($settings, $form);
					return $form_source;
				}
			}
		}
		else {
			// try widget options.
			$widget = get_option("widget_activecampaign_widget");
			// it comes out as an array with other things in it, so loop through it
			foreach ($widget as $k => $v) {
				// look for the one that appears to be the ActiveCampaign widget settings
				if (isset($v["api_url"]) && isset($v["api_key"]) && isset($v["form_html"])) {
					$widget_display = $v["form_html"];
					return $widget_display;
				}
			}
		}
	  return "";
	}
	
	/*
	 * The page for ActionCampaign
	 */
	function page() {

		if (!current_user_can("manage_options"))  {
			wp_die(__("You do not have sufficient permissions to access this page."));
		}

		$step = 1;
		$instance = array();
		$connected = false;

		if ($_SERVER["REQUEST_METHOD"] == "POST") {

			// saving the settings page.

			if ($_POST["api_url"] && $_POST["api_key"]) {

				$ac = new ActiveCampaignWordPress($_POST["api_url"], $_POST["api_key"]);

				if (!(int)$ac->credentials_test()) {
					echo "<p style='margin: 0 0 20px; padding: 14px; font-size: 14px; color: #873c3c; font-family:arial; background: #ec9999; line-height: 19px; border-radius: 5px; overflow: hidden;'>" . __("Access denied: Invalid credentials (URL and/or API key).", "menu-activecampaign") . "</p>";
				}
				else {

					$instance = $_POST;

					// first form submit (after entering API credentials).

					// get account details.
					$account = $ac->api("account/view");
					$instance["account_view"] = get_object_vars($account);
					$instance["account"] = $account->account;

					$user_me = $ac->api("user/me");
					// the tracking ID from the Integrations page.
					$instance["tracking_actid"] = $user_me->trackid;

					// get forms.
					$instance = $this->activecampaign_getforms($ac, $instance);
					$instance = $this->activecampaign_form_html($ac, $instance);
			   
			   		// $accontactlist = $ac->api("contact/list?ids=ALL&full=1&filters[listid]=1");
			   		// $accontactlist = $ac->api("contact/list?ids=ALL&full=1");
			   
					$connected = true;

				}

			}
			else {
				// one or both of the credentials fields is empty. it will just disconnect below because $instance is empty.
			}

			update_option("settings_activecampaign", $instance);

		}
		else {

			$instance = get_option("settings_activecampaign");
	//dbg($instance);

			if (isset($instance["api_url"]) && $instance["api_url"] && isset($instance["api_key"]) && $instance["api_key"]) {

				// instance saved already.
				$connected = true;

			}
			else {

				// settings not saved yet.

				// see if they set up our widget (maybe we can pull the API URL and Key from that).
				$widget = get_option("widget_activecampaign_widget");

				if ($widget) {
					// if the ActiveCampaign widget is activated in a sidebar (dragged to a sidebar).

					$widget_info = current($widget); // take the first item.

					if (isset($widget_info["api_url"]) && $widget_info["api_url"] && isset($widget_info["api_key"]) && $widget_info["api_key"]) {
						// if they already supplied an API URL and key in the widget.
						$instance["api_url"] = $widget_info["api_url"];
						$instance["api_key"] = $widget_info["api_key"];
					}
				}

			}

		}

		?>

		<div class="wrap">

			<div id="icon-options-general" class="icon32"><br></div>

			<h2><?php echo __("Active Campaign Settings", "menu-activecampaign"); ?></h2>
   
			<p style='font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 1.5;'>
				<?php

					echo __("Configure your ActiveCampaign subscription form to be used as a shortcode anywhere on your site. Use <code>[activecampaign form=ID]</code> shortcode in posts, pages, or a sidebar after setting up everything below. Questions or problems? Contact help@activecampaign.com.", "menu-activecampaign");

				?>
			</p>
   
			<form name="activecampaign_settings_form" method="post" action="" style='font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 1.5;'>

				<hr style="border: 1px dotted #ccc; border-width: 1px 0 0 0; margin-top: 30px;" />

				<h3><?php echo __("API Credentials", "menu-activecampaign"); ?></h3>

				<p>
					<b><?php echo __("API URL", "menu-activecampaign"); ?>:</b>
					<br />
					<input type="text" name="api_url" id="activecampaign_api_url" value="<?php echo esc_attr($instance["api_url"]); ?>" style="width: 400px;" />
				</p>

				<p>
					<b><?php echo __("API Key", "menu-activecampaign"); ?>:</b>
					<br />
					<input type="text" name="api_key" id="activecampaign_api_key" value="<?php echo esc_attr($instance["api_key"]); ?>" style="width: 500px;" />
				</p>

				<?php
					$button_value = ($connected) ? "Update Settings" : "Connect";

					if ($button_value == "Update Settings") {
						// Only show this additional form submit button if they are already connected.
						?>
						<p><button type="submit" style="font-size: 16px; margin-top: 25px; padding: 10px;"><?php echo __($button_value, "menu-activecampaign"); ?></button></p>
						<?php
					}

					if (!$connected) {

						?>

						<p style='font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 1.5;'><?php echo __("Get your API credentials from the Settings > Developer section:", "menu-activecampaign"); ?></p>
	   
						<p><img src="<?php echo plugins_url("wpcampaign"); ?>/admin/img/settings1.png" /></p>

						<?php

					}
					else {

						?>



						<?php

					}

				?>

				<?php

					if (isset($instance["forms"]) && $instance["forms"]) {

						?>

						<hr style="border: 1px dotted #ccc; border-width: 1px 0 0 0; margin-top: 30px;" />

						<h3><?php echo __("Subscription Forms", "menu-activecampaign"); ?></h3>
						<p style='font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 1.5;'><i><?php echo __("Choose subscription forms to cache locally. To add new forms go to your <a href=\"http://" . $instance["account"] . "/admin/main.php?action=form\" target=\"_blank\" style='color: #23538C !important;'>ActiveCampaign > Integration section</a>.", "menu-activecampaign"); ?></i></p>

						<?php

						// just a flag to know if ANY form is checked (chosen)
						$form_checked = 0;

						$settings_st_checked = (isset($instance["site_tracking"]) && (int)$instance["site_tracking"]) ? "checked=\"checked\"" : "";

						foreach ($instance["forms"] as $form) {

							// $instance["form_id"] is an array of form ID's (since we allow multiple now).

							$checked = "";
							$options_visibility = "none";
							if (isset($instance["form_id"]) && $instance["form_id"] && in_array($form["id"], $instance["form_id"])) {
								$checked = "checked=\"checked\"";
								$form_checked = 1;
								$options_visibility = "block";
							}

							$settings_swim_checked = (isset($instance["syim"][$form["id"]]) && $instance["syim"][$form["id"]] == "swim") ? "checked=\"checked\"" : "";
							$settings_sync_checked = (isset($instance["syim"][$form["id"]]) && $instance["syim"][$form["id"]] == "sync") ? "checked=\"checked\"" : "";
							if (!$settings_swim_checked && !$settings_sync_checked) $settings_swim_checked = "checked=\"checked\""; // default
							$settings_ajax_checked = (isset($instance["ajax"][$form["id"]]) && (int)$instance["ajax"][$form["id"]]) ? "checked=\"checked\"" : "";

							$settings_css_checked = "";
							if ( (isset($instance["css"][$form["id"]]) && (int)$instance["css"][$form["id"]]) || !$form_checked) {
								// either it's been checked before, OR
								// no form is chosen yet, so it's likely coming from step 1, so default the CSS checkbox to checked.
								$settings_css_checked = "checked=\"checked\"";
							}

							$settings_action_value = (isset($instance["action"][$form["id"]]) && $instance["action"][$form["id"]]) ? $instance["action"][$form["id"]] : "";

							?>

							<hr style="border: 1px dotted #ccc; border-width: 1px 0 0 0; margin: 30px 0 20px 0;" />

							<input type="checkbox" name="form_id[]" id="activecampaign_form_<?php echo $form["id"]; ?>" value="<?php echo $form["id"]; ?>" onclick="toggle_form_options(this.value, this.checked);" <?php echo $checked; ?> />
							<label for="activecampaign_form_<?php echo $form["id"]; ?>"><a href="http://<?php echo $instance["account"]; ?>/admin/main.php?action=form_edit&id=<?php echo $form["id"]; ?>" target="_blank"><?php echo $form["name"]; ?></a></label>
							<br />
					   
							<div id="form_options_<?php echo $form["id"]; ?>" style="display: <?php echo $options_visibility; ?>; margin-left: 30px;">
								<h4><?php echo __("Form Options", "menu-activecampaign"); ?></h4>
								<p><i><?php echo __("Leave as default for normal behavior, or customize based on your needs.", "menu-activecampaign"); ?></i></p>
								<div style="display: none;">
									<input type="radio" name="syim[<?php echo $form["id"]; ?>]" id="activecampaign_form_swim_<?php echo $form["id"]; ?>" value="swim" <?php echo $settings_swim_checked; ?> onchange="swim_toggle(<?php echo $form["id"]; ?>, this.checked);" />
									<label for="activecampaign_form_swim_<?php echo $form["id"]; ?>" style="">Add Subscriber</label>
									<br />
									<input type="radio" name="syim[<?php echo $form["id"]; ?>]" id="activecampaign_form_sync_<?php echo $form["id"]; ?>" value="sync" <?php echo $settings_sync_checked; ?> onchange="sync_toggle(<?php echo $form["id"]; ?>, this.checked);" />
									<label for="activecampaign_form_sync_<?php echo $form["id"]; ?>" style="">Sync Subscriber</label>
									<br />
									<br />
								</div>
								<?php if (!isset($form["version"]) || $form["version"] != 2): ?>
								<input type="checkbox" name="ajax[<?php echo $form["id"]; ?>]" id="activecampaign_form_ajax_<?php echo $form["id"]; ?>" value="1" <?php echo $settings_ajax_checked; ?> onchange="ajax_toggle(<?php echo $form["id"]; ?>, this.checked);" />
								<label for="activecampaign_form_ajax_<?php echo $form["id"]; ?>" style="">Submit form without refreshing page</label>
								<br />
								<?php endif; ?>
								<input type="checkbox" name="css[<?php echo $form["id"]; ?>]" id="activecampaign_form_css_<?php echo $form["id"]; ?>" value="1" <?php echo $settings_css_checked; ?> />
								<label for="activecampaign_form_css_<?php echo $form["id"]; ?>" style="">Keep original form CSS</label>
							</div>
							
							<?php

						}

						?>

						<?php 
						/*
						<hr style="border: 1px dotted #ccc; border-width: 1px 0 0 0; margin: 30px 0 20px 0;" />

						<h3><?php echo __("Site Tracking", "menu-activecampaign"); ?></h3>
						<p><i><?php echo __("Site tracking lets you record visitor history on your site to use for targeted segmenting. Learn more on the <a href=\"http://" . $instance["account"] . "/track/\" target=\"_blank\" style='color: #23538C !important;'>ActiveCampaign > Integration section</a>.", "menu-activecampaign"); ?></i></p>

						<input type="checkbox" name="site_tracking" id="activecampaign_site_tracking" value="1" <?php echo $settings_st_checked; ?> onchange="site_tracking_toggle(this.checked);" />
						<label for="activecampaign_site_tracking" style=""><?php echo __("Enable Site Tracking", "menu-activecampaign"); ?></label>
						(<a href="http://www.activecampaign.com/help/site-event-tracking/" style='color: #23538C !important;' target="_blank">?</a>)
						*/
						?>
						<script type='text/javascript'>

							// shows or hides the sub-options section beneath each form checkbox.
							function toggle_form_options(form_id, ischecked) {
								var form_options = document.getElementById("form_options_" + form_id);
								var display = (ischecked) ? "block" : "none";
								form_options.style.display = display;
							}

							//var swim_radio = document.getElementById("activecampaign_form_swim");

							function ac_str_is_url(url) {
								url += '';
								return url.match( /((http|https|ftp):\/\/|www)[a-z0-9\-\._]+\/?[a-z0-9_\.\-\?\+\/~=&#%;:\|,\[\]]*[a-z0-9\/=?&;%\[\]]{1}/i );
							}

							function swim_toggle(form_id, swim_checked) {
								if (swim_checked) {

								}
							}

							function sync_toggle(form_id, sync_checked) {
								var ajax_checkbox = document.getElementById("activecampaign_form_ajax_" + form_id);
								var action_textbox = document.getElementById("activecampaign_form_action_" + form_id);
								if (sync_checked && action_textbox.value == "") {
									// if Sync is chosen, and there is no custom action URL, check the Ajax option.
									ajax_checkbox.checked = true;
								}
							}

							function ajax_toggle(form_id, ajax_checked) {
								var ajax_checkbox = document.getElementById("activecampaign_form_ajax_" + form_id);
								var sync_radio = document.getElementById("activecampaign_form_sync_" + form_id);
								var action_textbox = document.getElementById("activecampaign_form_action_" + form_id);
								var site_tracking_checkbox = document.getElementById("activecampaign_site_tracking");
								if (ajax_checked && site_tracking_checkbox.checked)  {
									alert("If you use this option, site tracking cannot be enabled.");
									site_tracking_checkbox.checked = false;
								}
							}

							function action_toggle(form_id, action_value) {
								var action_textbox = document.getElementById("activecampaign_form_action_" + form_id);
								if (action_textbox.value && ac_str_is_url(action_textbox.value)) {

								}
							}

							function site_tracking_toggle(is_checked) {
								// we can't allow site tracking if ajax is used because that uses the API.
								// so here we check to see if they have chosen ajax for any form, an if so alert them and uncheck the ajax options.
								if (is_checked)  {
									var inputs = document.getElementsByTagName("input");
									// if Sync is checked, and action value is empty or invalid, and they UNcheck Ajax, alert them.
									var checked_already = [];
									for (var i in inputs) {
										var c = inputs[i];
										if (c.type == "checkbox" && c.name.match(/^ajax\[/) && c.checked) {;
											// example: <input type="checkbox" name="ajax[1642]" id="activecampaign_form_ajax_1642" value="1" checked="checked" onchange="ajax_toggle(1642, this.checked);">
											checked_already.push(c.id);
										}
									}
									if (checked_already.length) {
										// if at least one of the ajax checkboxes is checked.
										alert("If you enable site tracking, a page refresh is required.");
										for (var i in checked_already) {
											var id = checked_already[i];
											var dom_item = document.getElementById(id);
											dom_item.checked = false;
										}
									}
								}
							}

						</script>

						<?php

					}

				?>

				<p><button type="submit" style="font-size: 16px; margin-top: 25px; padding: 10px;"><?php echo __($button_value, "menu-activecampaign"); ?></button></p>

			</form>

			<?php

				if (isset($instance["forms"])) {

					?>

					<hr style="border: 1px dotted #ccc; border-width: 1px 0 0 0; margin-top: 30px;" />
					<h3><?php echo __("Subscription Form(s) Preview", "menu-activecampaign"); ?></h3>	

					<?php

					foreach ($instance["forms"] as $form_id => $form_metadata) {

						$form_source = $this->activecampaign_form_source($instance, $form_metadata, true);
						echo $form_source;
				   
						?>
				   
						<p><?php echo __("Embed using"); ?><code>[activecampaign form=<?php echo $form_id; ?>]</code></p>
				   
						<hr style="border: 1px dotted #ccc; border-width: 1px 0 0 0; margin-top: 40px;" />
				   
						<?php
			   
					}
	   
				}

			?>

		</div>

		<?php

	}

	function activecampaign_getforms($ac, $instance) {
	  $forms = $ac->api("form/getforms");
	  if ((int)$forms->success) {
		$items = array();
		$forms = get_object_vars($forms);
		foreach ($forms as $key => $value) {
		  if (is_numeric($key)) {
			$items[$value->id] = get_object_vars($value);
		  }
		}
		$instance["forms"] = $items;
	  }
	  else {
		if ($forms->error == "Failed: Nothing is returned") {
			$instance["error"] = "Nothing was returned. Do you have at least one form created in your ActiveCampaign account?";
		}
		else {
			$instance["error"] = $forms->error;
		}
	  }
	  return $instance;
	}


	 function activecampaign_form_html($ac, $instance) {

		 if ($instance["forms"]) {
			 foreach ($instance["forms"] as $form) {

				 // $instance["form_id"] is an array of form ID's (since we allow multiple now).

				 if (isset($instance["form_id"]) && in_array($form["id"], $instance["form_id"])) {

					 if (isset($form["version"]) && $form["version"] == 2) {
						 // Nothing to do here - we'll generate the form source code on page load.
						 continue;
					 }

					 // Version 1 forms only should proceed here!!

					 $domain = $instance["account"];
					 $protocol = "https:";

					 $form_embed_params = array(
						 "id" => $form["id"],
						 "ajax" => $instance["ajax"][$form["id"]],
						 "css" => $instance["css"][$form["id"]],
					 );

					 $sync = ($instance["syim"][$form["id"]] == "sync") ? 1 : 0;

					 if ($instance["action"][$form["id"]]) {
						 $form_embed_params["action"] = $instance["action"][$form["id"]];
					 }

					 if ((int)$form_embed_params["ajax"] && !isset($form_embed_params["action"])) {
						 // if they are using Ajax, but have not provided a custom action URL, we need to push it to a script where we can submit the form/process API request.
						 // remove the "http(s)" portion, because it was conflicting with the Ajax request (I was getting 404's).
						 $api_url_process = preg_replace("/https:\/\//", "", $instance["api_url"]);
						 $form_embed_params["action"] = plugins_url("form_process.php?sync=" . $sync, __FILE__);
					 }

					 // prepare the params for the API call
					 $api_params = array();
					 foreach ($form_embed_params as $var => $val) {
						 $api_params[] = $var . "=" . urlencode($val);
					 }

					 // fetch the HTML source
					 $html = $ac->api("form/embed?" . implode("&", $api_params));

					 if ((int)$form_embed_params["ajax"]) {
						 // used for the result message that is displayed after submitting the form via Ajax
						 $html = "<div id=\"form_result_message\"></div>" . $html;
					 }

					 if ($html) {
						 if ($instance["account"]) {
							 // replace the API URL with the account URL (IE: https://account.api-us1.com is changed to http://account.activehosted.com).
							 // (the form has to submit to the account URL.)
							 if (!$instance["action"]) {
								 $html = preg_replace("/action=['\"][^'\"]+['\"]/", "action='" . $protocol . "//" . $domain . "/proc.php'", $html);
							 }
						 }
						 // replace the Submit button to be an actual submit type.
						 //$html = preg_replace("/input type='button'/", "input type='submit'", $html);
					 }

					 if ((int)$form_embed_params["css"]) {
						 // get the style content so we can prepend each rule with the form ID (IE: #_form_1341).
						 // this is in case there are multiple forms on the same page - their styles need to be unique.
						 preg_match_all("|<style[^>]*>(.*)</style>|iUs", $html, $style_blocks);
						 if (isset($style_blocks[1]) && isset($style_blocks[1][0]) && $style_blocks[1][0]) {
							 $css = $style_blocks[1][0];
							 // remove excess whitespace from within the string.
							 $css = preg_replace("/\s+/", " ", $css);
							 // remove whitespace from beginning and end of string.
							 $css = trim($css);
							 $css_rules = explode("}", $css);
							 $css_rules_new = array();
							 foreach ($css_rules as $rule) {
								 $rule_array = explode("{", $rule);
								 $rule_array[0] = preg_replace("/\s+/", " ", $rule_array[0]);
								 $rule_array[0] = trim($rule_array[0]);
								 $rule_array[1] = preg_replace("/\s+/", " ", $rule_array[1]);
								 $rule_array[1] = trim($rule_array[1]);
								 if ($rule_array[1]) {
									 // there could be comma-separated rules.
									 $rule_array2 = explode(",", $rule_array[0]);
									 foreach ($rule_array2 as $rule_) {
										 $rule_ = "#_form_" . $form["id"] . " " . $rule_;
										 $css_rules_new[] = $rule_ . " {" . $rule_array[1] . "}";
									 }
								 }
							 }
						 };

						 $new_css = implode("\n\n", $css_rules_new);
						 // remove existing styles.
						 $html = preg_replace("/<style[^>]*>(.*)<\/style>/s", "", $html);
						 // replace with updated CSS string.
						 $html = "<style>" . $new_css . "</style>" . $html;
					 }

					 // check for custom width.
					 if ((int)$form["widthpx"]) {
						 // if there is a custom width set
						 // find the ._form CSS rule
						 preg_match_all("/\._form {[^}]*}/", $html, $_form_css);
						 if (isset($_form_css[0]) && $_form_css[0]) {
							 foreach ($_form_css[0] as $_form) {
								 // find "width:400px"
								 preg_match("/width:[0-9]+px/", $_form, $width);
								 if (isset($width[0]) && $width[0]) {
									 // IE: replace "width:400px" with "width:200px"
									 $html = preg_replace("/" . $width[0] . "/", "width:" . (int)$form["widthpx"] . "px", $html);
								 }
							 }
						 }
					 }

					 $instance["form_html"][$form["id"]] = $html;

				 }

			 }
	   }
	   else {
			 // no forms created in the AC account yet.
			 echo "<p style='margin: 0 0 20px; padding: 14px; font-size: 14px; color: #776e30; font-family:arial; background: #fff3a5; line-height: 19px; border-radius: 5px; overflow: hidden;'>" . __("Make sure you have at least one form created in ActiveCampaign.") . "</p>";
	   }

	   return $instance;
	 }


	 /**
	  * Get the source code for the form itself.
	  * In the past we just returned the form HTML code (CSS + HTML), but the new version of forms just uses the JavaScript stuff (HTML JavaScript include).
	  *
	  * @param  array   settings  The saved ActiveCampaign settings (from the WordPress admin section).
	  * @param  array   form      The individual form metadata (that we obtained from the forms/getforms API call).
	  * @param  boolean static    Set to true so the "floating" forms don't float. Typically this is done for the admin section only.
	  * @return string            The raw source code that will render the form in the browser.
	  */
	 function activecampaign_form_source($settings, $form, $static = false) {
		 $source = "";
		 if (isset($form["version"]) && $form["version"] == 2) {
			 if ($form["layout"] == "inline-form") {
				 $source .= "<div class='_form_" . $form["id"] . "'></div>";
			 }
			 // Set to activehosted.com domain by default.
			 $domain = $settings["account_view"]["account"];
			 $source .= "<script type='text/javascript' src='";
			 $source .= sprintf("https://%s/f/embed.php?", $domain);
			 if ($static) {
				 $source .= "static=1&";
			 }
			 $source .= sprintf("id=%d&%s", $form["id"], strtoupper(uniqid()));
			 if (!isset($settings["css"][$form["id"]]) || !$settings["css"][$form["id"]]) {
				 $source .= "&nostyles=1";
			 }
			 $source .= "'></script>";
		 } else {
			 // Version 1 forms.
		 }
		 return $source;
	 }


}
