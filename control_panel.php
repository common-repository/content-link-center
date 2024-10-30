<?php
class contentLinkCenterControlPanel {
	var $options = array();
	function __construct($file) {
		$this->contentLinkCenterControlPanel($file);
	}

	function contentLinkCenterControlPanel($file) {
		// Add Settings link to plugin page
		add_filter("plugin_action_links_".$file, array($this, 'actlinks'));
		// Any settings to initialize
		add_action('admin_init', array($this, 'adminInit'));
		// Load menu page
		add_action('admin_menu', array($this, 'addAdminPage'));
		// Load admin CSS style sheet
		add_action('admin_head', array($this, 'registerHead'));
	}

	function actlinks($links) {
		// Add a link to this plugins settings page
		$settings_link = '<a href="options-general.php?page=content-link-center">Settings</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	function adminInit() {
		register_setting('contentLinkCenterOptions', 'content_link_center');
	}

	function addAdminPage() {
		add_options_page('Content Link Center Options', 'Content Link Center', 'administrator', 'content-link-center', array($this, 'admin'));
	}

	function admin() {
		echo '<div class="wrap">';
		echo '<div class="clc">';
		echo '<form method="post" action="">';

		echo '<h2>Content Link Center Settings</h2>';
		echo '<p><small>By: Swiss IP GmbH</small></p>';
		echo '<table class="form-table" cellspacing="2" cellpadding="5">';

		settings_fields('contentLinkCenterOptions');

		$update = false;

		if ($_REQUEST['old_api'] != $_REQUEST['new_api']) {
			update_option('content_link_center_api', $_REQUEST['new_api']);
		}
		$api = get_option('content_link_center_api');

		if ($_REQUEST['new_api'] != '') {
			$clc_url = 'http://www.def.ch/content-link-center/show_links.php?api_key='.$api.'&host='.$_SERVER["HTTP_HOST"];
			$clc_html = "";
			if (function_exists('curl_init')) {
				$ch = curl_init();
				curl_setopt ($ch, CURLOPT_URL, $clc_url);
				curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt	($ch, CURLOPT_TIMEOUT, 2);
				curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 1);
				curl_setopt ($ch, CURLOPT_LOW_SPEED_LIMIT, 100);
				curl_setopt ($ch, CURLOPT_LOW_SPEED_TIME, 1);
				$clc_html = curl_exec($ch);
				curl_close($ch);
			} else if (function_exists('fsockopen')) {
				if(@fsockopen("www.def.ch",80,$errno,$errstr,2)){
					$clc_html=@implode("",file($clc_url));
				}
			} else {
				$clc_html = @file_get_contents($clc_url);
			}

			if ($clc_html != '') {
				if (preg_match("</OUTPUT>", $clc_html)) {
					update_option('content_link_center_data', $clc_html);
				}
			}

			$data 	= get_option('content_link_center_data');
			$lines 	= @explode("\n", $data);
			$c = 1;
			foreach($lines as $line) {
				if ($line != '<OUTPUT>' && $line != '</OUTPUT>' && $line != '') {
					$rows = @explode("|", $line);
					if(!isset($this->options['keys'])) $this->options['keys'] = array();
					if(!array_key_exists($rows[0], $this->options['keys'])) {
						$this->options['links'][] = array(
							'name' => strip_tags(stripslashes($rows[0])),
							'url' => strip_tags(stripslashes($rows[1])),
							'instances' => strip_tags(stripslashes($rows[2])),
							'match_whole_word' => strip_tags(stripslashes($rows[3])),
							'new_window' => strip_tags(stripslashes($rows[4])),
							'link_autolink' => strip_tags($rows[5])
						);
						$this->options['keys'][$rows[0]] = true;
						$update = true;
					}
					$c++;
				}
			}
			if($update == true) {
				update_option('content_link_center', $this->options);
				update_option('content_link_center_update_time', time());
				echo "<div class='updated fade'><p><strong>API saved</strong></p></div>";
			}
		}
		$this->options = get_option('content_link_center');

		echo '</table>'."\n";
		echo '<h3 class="title">'.__('Add API Key').'</h3>'."\n";
		echo '<table class="form-table" cellspacing="2" cellpadding="5">'."\n";
		$update_time 	= get_option('content_link_center_update_time');
		if ($update_time > 0) {
			$last_update = ' (Last update: '.date("d.m.Y H:i:s", $update_time).')';
		}
		echo '<tr><th scope="row"><label>'.__('API Key').':</label></th><td><input class="regular-text" type="text" name="new_api" value="'.$api.'" /><input type="hidden" name="old_api" value="'.$api.'" /><br /><span class="description">Enter the api key from <a target="_blank" href="http://www.def.ch/content-link-center/">def.ch</a>'.$last_update.'</span></td></tr>'."\n";
		echo '</table>'."\n";

		echo '<br />'."\n";
		echo '<p class="submit"><input class="button-primary" type="submit" value="'.__('Save API').'" /></p>'."\n";
		echo '</form><p>&nbsp;</p>'."\n";


		if(isset($this->options['links']) AND !empty($this->options['links'])) {
			echo '<h3 class="title">'.__('Existing Content Links').'</h3>';
			echo '<table class="form-table" cellspacing="1" cellpadding="0">';
			foreach($this->options['links'] as $key => $link) {
				echo '<tr><td>'.__('Link Text').':</td><td>'.$link['name'].'</td></tr>'."\n";
				echo '<tr><td>'.__('URL').':</td><td>'.$link['url'].'</td></tr>'."\n";
				echo '<tr><td>'.__('How many replacements').': </td><td>'.$link['instances'].'</td></tr>'."\n";

				if ($link['match_whole_word'] == 1) {
					$match_whole_word = 'Yes';
				} else {
					$match_whole_word = 'No';
				}
				echo '<tr><td>'.__('Only match whole words').':</td><td>'.$match_whole_word.'</td></tr>'."\n";

				if ($link['new_window'] == 1) {
					$new_window = 'Yes';
				} else {
					$new_window = 'No';
				}
				echo '<tr><td>'.__('Open link in new window').':</td><td>'.$new_window.'</td></tr>'."\n";

//				if ($link['link_autolink'] == 1) {
//					$link_autolink = 'Yes';
//				} else {
//					$link_autolink = 'No';
//				}
//				echo '<tr><td>'.__('Link contentlink with same URL back to itself').':</td><td>'.$link_autolink.'</td></tr>'."\n";

				echo '<tr><td colspan="2"><hr /></td></tr>';
			}
		}

		echo '</table>';
		echo '<p>&nbsp;</p></div>

		</div>';
	}

	function registerHead() {
		$url = WP_PLUGIN_URL.'/'.basename(dirname(__FILE__)).'/content-link-center.css';
		echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"".$url."\" />\n";
	}
}