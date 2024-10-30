<?php
/**
 * Plugin Name: Icecast Now Playing
 * Plugin URI: http://www.galwayland.com/?page_id=140
 * Description: A widget to display Icecast server statistics.
 * Version: 2.0.0
 * Author: Wlliam J. Galway
 * Author URI: http://galwayland.com
 *
 * Icecast Now Playing a widget to display Icecast server connection stats in a Wordpress blog.
 *   Copyright (C) <2010>  <William J. Galway>
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


/**
 * Icecast Status Widget class.
 */
//error_reporting(-1);
error_reporting(E_ALL);
class Icecast_Status_Widget extends WP_Widget {
	public function __construct() {
	parent::__construct(
	'icecast-widget',  // Base ID
	'Icecast Now Playing' // Name
		);
	}

	/**
	 * How to display the widget on the screen.
			var time = "<?php echo $rtime  ?>";
	 */
	public function widget($args, $instance) {
		extract($args);
		/* Our variables from the widget settings. */
		$title = apply_filters('widget_title', $instance['title']);
		$rtime = $instance['refreshtime'];
		$content = '<span class="icecast-content"></span><script type="text/javascript">
			jQuery(document).ready(function($){
			var time = ' . json_encode($rtime) . ';
			var load = function(){ $("#' . $this->id . ' .icecast-content").load("", { "icecast-widget": ' . $this->number . '}); };
			load();
			setInterval(load,time); // Refresh Interval
			console.log(time);
        });
    </script>';
		
		/* Before widget (defined by themes). */
		echo $before_widget;

		/* Display the widget title if one was input (before and after defined by themes). */
		if ($title)
			echo $before_title . $title . $after_title;
		
		echo $content;
		
		/* After widget (defined by themes). */
		echo $after_widget;
	}

	/**
	 * Retrieve data from icecast server
	 * @param array $instance
	 * @return WP_Error|array 
	 */
	function getData($instance) {
		
		$server = $instance['servername'];
		$username = $instance['username'];
		$password = $instance['password'];
		$mount1 = $instance['mount1'];
		$mount2 = $instance['mount2'];
		$mount3 = $instance['mount3'];
		
		// Get data from server
		$http = new WP_Http();
		$headers = array ('Authorization' => 'Basic '.base64_encode("$username:$password")) ;
        	$response = $http->request("http://$server/admin/stats" ,  array( 'headers' => $headers)) ;
		if ($response instanceof WP_Error)
			return $response;
		
		if ($response['response']['code'] == 401)
			return new WP_Error('icecast_server_error', $response['body']);
		
		// Extract interesting data from XML
		$source = "";
		$stats = $response['body'];
		$info = array();
		$dom = new DomDocument('1.0', 'UTF-8');
		$dom->loadXML($stats);
		foreach ($dom->getElementsByTagName('source') as $source) {
			if ($source->getAttribute('mount') == $mount1)
				$i = 1;
			elseif ($source->getAttribute('mount') == $mount2)
				$i = 2;
			elseif ($source->getAttribute('mount') == $mount3)
				$i = 3;
			else
				continue;
			
			$info['track' . $i] = $source->getElementsByTagName('title')->item(0)->nodeValue;
			$info['listen' . $i] = $source->getElementsByTagName('listenurl')->item(0)->nodeValue;
			$info['users' . $i] = $source->getElementsByTagName('listeners')->item(0)->nodeValue;
			$info['server' . $i] = $source->getElementsByTagName('server_url')->item(0)->nodeValue;
		}
		
		return $info;
	}
	
	/**
	 * Format data received from icecast to HTML
	 * @param WP_Error|array $info
	 * @param type $instance
	 * @return string 
	 */
	function formatData($info, $instance)
	{
		if ($info instanceof WP_Error)
		{
			return "ERROR: " . $info->get_error_message();
		}
		
		$description1 = $instance['description1'];
		$description2 = $instance['description2'];
		$description3 = $instance['description3'];
		$display_listeners = $instance['display_listeners'];
		$result = '';
		/** 	
		 * Begin display Icecast connection statistics in widget
		 * Change the widget display layout here
		 */
		if (isset($info['track1'])) {
			if ($description1) {
				$result .= "<strong>$description1</strong><br/>";
			}
			$result .= "<a href=\"$info[listen1]\">$info[track1]</a>\n";
			$result .= "<br/>";
			if ($display_listeners) {
				$result .= "Listeners connected: <a href=\"$info[server1]\">$info[users1]</a>";
				$result .= "<br />";
			}
		}

		if (isset($info['track2'])) {
			if ($description2) {
				$result .= "<strong>$description2</strong><br/>";
			}
			$result .= "<a href=\"$info[listen2]\">$info[track2]</a>\n";
			$result .= "<br />";
			if ($display_listeners) {
				$result .= "Listeners connected: <a href=\"$info[server2]\">$info[users2]</a>";
				$result .= "<br />";
			}
		}
		
		if (isset($info['track3'])) {
			if ($description3) {
				$result .= "<strong>$description3</strong><br/>";
			}
			$result .= "<a href=\"$info[listen3]\">$info[track3]</a>\n";
			$result .= "<br />";
			if ($display_listeners) {
				$result .= "Listeners connected: <a href=\"$info[server3]\">$info[users3]</a>";
				$result .= "<br />";
			}
		}
		
		return $result;
	}
	
	/**
	 * Method called via ajax
	 * @param type $number 
	 */
	function ajax($number) {
		
		// Find instance
		$instance = $this->get_settings();
		if ( array_key_exists( $number, $instance ) ) {
			$instance = $instance[$number];
			
			// Do widget work
			$info = $this->getData($instance);
			echo $this->formatData($info, $instance);
		}
	
		exit();
	}
	
	/**
	 * Update the widget settings.
	 */
	public function update($new_instance, $old_instance) {
		$instance = $old_instance;

		/* Strip tags for title and name to remove HTML (important for text inputs). */
		/* Necessary for form data persistance. */
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['servername'] = strip_tags($new_instance['servername']);
		$instance['username'] = strip_tags($new_instance['username']);
		$instance['password'] = strip_tags($new_instance['password']);
		$instance['mount1'] = strip_tags($new_instance['mount1']);
		$instance['mount2'] = strip_tags($new_instance['mount2']);
		$instance['mount3'] = strip_tags($new_instance['mount3']);
		$instance['description1'] = strip_tags($new_instance['description1']);
		$instance['description2'] = strip_tags($new_instance['description2']);
		$instance['description3'] = strip_tags($new_instance['description3']);
		$instance['refreshtime'] = strip_tags($new_instance['refreshtime']);
		$instance['display_listeners'] = strip_tags($new_instance['display_listeners']);

		return $instance;
	}

	/**
	 * Displays the widget settings controls on the widget panel.
	 * Make use of the get_field_id() and get_field_name() function
	 * when creating your form elements. This handles the confusing stuff.
	 */
	public function form($instance) {

		/* Set up some default widget settings. */
		$defaults = array(
			'title' => __('Icecast', 'icecaststatus'),
			'servername' => __('127.0.0.1:8000', 'icecaststatus'),
			'username' => __('admin', 'icecaststatus'),
			'password' => __('password', 'icecaststatus'),
			'mount1' => __('/ices', 'icecaststatus'),
			'mount2' => __('/mpd', 'icecaststatus'),
			'mount3' => __('/mpd', 'icecaststatus'),
			'description1' => __('Now Playing', 'icecaststatus'),
			'description2' => __('Now Playing', 'icecaststatus'),
			'description3' => __('Now Playing', 'icecaststatus'),
			'refreshtime' => __('30000', 'icecaststatus'),
			'display_listeners' => 1
		);


		$instance = wp_parse_args((array) $instance, $defaults);
		?>
		<!-- Widget Title: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'hybrid'); ?></label>
			<input id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $instance['title']; ?>" style="width:100%;" />
		</p>


		<!-- Server Name: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id('servername'); ?>"><?php _e('server name:port', 'icecaststatus'); ?></label>
			<input id="<?php echo $this->get_field_id('servername'); ?>" name="<?php echo $this->get_field_name('servername'); ?>" value="<?php echo $instance['servername']; ?>" style="width:100%;" />
		</p>

		<!-- User Name: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id('username'); ?>"><?php _e('username:', 'icecaststatus'); ?></label>
			<input id="<?php echo $this->get_field_id('username'); ?>" name="<?php echo $this->get_field_name('username'); ?>" value="<?php echo $instance['username']; ?>" style="width:100%;" />
		</p>

		<!-- Password: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id('password'); ?>"><?php _e('password:', 'icecaststatus'); ?></label>
			<input id="<?php echo $this->get_field_id('password'); ?>" name="<?php echo $this->get_field_name('password'); ?>" value="<?php echo $instance['password']; ?>" style="width:100%;" />
		</p>

		<!-- Mount: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id('mount1'); ?>"><?php _e('mount 1:', 'icecaststatus'); ?></label>
			<input id="<?php echo $this->get_field_id('mount1'); ?>" name="<?php echo $this->get_field_name('mount1'); ?>" value="<?php echo $instance['mount1']; ?>" style="width:100%;" />
		</p>

		<!-- Stream Description: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id('description1'); ?>"><?php _e('stream description:', 'icecaststatus'); ?></label>
			<input id="<?php echo $this->get_field_id('description1'); ?>" name="<?php echo $this->get_field_name('description1'); ?>" value="<?php echo $instance['description1']; ?>" style="width:100%;" />
		</p>

		<!-- Mount: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id('mount2'); ?>"><?php _e('mount 2:', 'icecaststatus'); ?></label>
			<input id="<?php echo $this->get_field_id('mount2'); ?>" name="<?php echo $this->get_field_name('mount2'); ?>" value="<?php echo $instance['mount2']; ?>" style="width:100%;" />
		</p>

		<!-- Stream Description: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id('description2'); ?>"><?php _e('stream description:', 'icecaststatus'); ?></label>
			<input id="<?php echo $this->get_field_id('description2'); ?>" name="<?php echo $this->get_field_name('description2'); ?>" value="<?php echo $instance['description2']; ?>" style="width:100%;" />
		
		</p>
		<!-- Mount: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id('mount3'); ?>"><?php _e('mount3:', 'icecaststatus'); ?></label>
			<input id="<?php echo $this->get_field_id('mount3'); ?>" name="<?php echo $this->get_field_name('mount3'); ?>" value="<?php echo $instance['mount3']; ?>" style="width:100%;" />
		</p>

		<!-- Stream Description: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id('description3'); ?>"><?php _e('stream description:', 'icecaststatus'); ?></label>
			<input id="<?php echo $this->get_field_id('description3'); ?>" name="<?php echo $this->get_field_name('description3'); ?>" value="<?php echo $instance['description3']; ?>" style="width:100%;" />
		</p>
		
<!-- Refresh time : NUmber Input -->
		<p>
			<input type="number" id="<?php echo $this->get_field_id('refreshtime'); ?>" name="<?php echo $this->get_field_name('refreshtime'); ?>" value="<?php echo $instance['refreshtime']; ?>" style="width:100%;" />
			<label for="<?php echo $this->get_field_id('refreshtime'); ?>"><?php _e('refresh time milliseconds:', 'icecaststatus'); ?></label>
		</p>

		<!-- Display listeners: Checkbox -->
		<p>
			<input type="checkbox" id="<?php echo $this->get_field_id('display_listeners'); ?>" name="<?php echo $this->get_field_name('display_listeners'); ?>" value="1"<?php echo ($instance['display_listeners'] > 0 ? ' checked="checked"' : ''); ?> />
			<label for="<?php echo $this->get_field_id('display_listeners'); ?>"><?php _e('display listeners', 'icecaststatus'); ?></label>
		</p>

		<?php
	}
}

/**
 * Function for handling AJAX requests
 * @return void 
 */
function icecast_ajax_handler() {
	
	if (!isset($_REQUEST['icecast-widget']))
		return;
	
	$widget = new Icecast_Status_Widget();
	$widget->ajax($_REQUEST['icecast-widget']);
}

// Load jQuery
wp_enqueue_script('jquery');

// Add the ajax handler to init()
add_action('init', 'icecast_ajax_handler');

// Register widget using the widgets_init hook
add_action( 'widgets_init', function() {
	register_widget( 'Icecast_Status_Widget' );
});

// $my_widget = new Icecast_Status_Widget();
