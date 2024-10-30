<?php
/*
Plugin Name: Content Link Center
Plugin URI: http://www.def.ch/content-link-center/
Description: Auto replace specific words or phrases in your content with a link
Author: Swiss IP GmbH
Version: 1.32
Author URI: http://www.def.ch/
*/

if(!is_admin()) {
	////////////////////////////////
	// Start Auto Update
	$update_time 	= get_option('content_link_center_update_time');
	$cache_time 	= 86400;
	$ablauf 		= ($update_time+$cache_time);
	$api 			= get_option('content_link_center_api');

	if (time() > $ablauf) {
		$update = false;

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
				if(!isset($options['keys'])) $options['keys'] = array();
				if(!array_key_exists($rows[0], $options['keys'])) {
					$options['links'][] = array(
						'name' => strip_tags(stripslashes($rows[0])),
						'url' => strip_tags(stripslashes($rows[1])),
						'instances' => strip_tags(stripslashes($rows[2])),
						'match_whole_word' => strip_tags(stripslashes($rows[3])),
						'new_window' => strip_tags(stripslashes($rows[4])),
						'link_autolink' => strip_tags($rows[5])
					);
					$options['keys'][$rows[0]] = true;
					$update = true;
				}
				$c++;
			}
		}
		if($update == true) {
			update_option('content_link_center', $options);
			update_option('content_link_center_update_time', time());
		}
	}
	// End Auto Update
	////////////////////////////////

	// Activate the plugin
	add_filter('the_content', 'contentLinkCenterContent');
} else {
	require_once('control_panel.php');
	new contentLinkCenterControlPanel(plugin_basename(__FILE__));
}

if(!function_exists('contentLinkCenterCurrentPageURL')) {
	function contentLinkCenterCurrentPageURL() {
		// Construct current page
		$currentPageURL = 'http';
		if ($_SERVER['HTTPS'] == 'on') $currentPageURL .= 's';

		$currentPageURL .= '://';
		if ($_SERVER['SERVER_PORT'] != '80') {
			$currentPageURL .= $_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].$_SERVER['REQUEST_URI'];
		} else {
			$currentPageURL .= $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
		}
		return $currentPageURL;
	}
}

if(!function_exists('contentLinkCenterContent')) {
	function contentLinkCenterContent($content) {

		$options = get_option('content_link_center');

		// Set default value for autolink linking back to itself
		if(!isset($link['link_autolink'])) $link['link_autolink'] = true;

		if(isset($options['links']) AND !empty($options['links'])) {
			foreach($options['links'] as $link) {
				if(!(preg_match("@".preg_quote($link['url']) .'$@', contentLinkCenterCurrentPageURL())) OR $link['link_autolink'] == true) {
					$wordBoundary = '';
					if($link['match_whole_word'] == true) $wordBoundary = '\b';

					$newWindow = '';
					if($link['new_window'] == true) $newWindow = ' target="_blank"';

					$content = preg_replace('@('.$wordBoundary.$link['name'].$wordBoundary.')(?!([^<>]*<[^Aa<>]+>)*([^<>]+)?</[aA]>)(?!([^<\[]+)?[>\]])@', '<a'.$newWindow.' href="'.$link['url'].'">'.$link['name'].'</a>', $content, $link['instances']);
				}
			}
		}
		return $content;
	}
}
