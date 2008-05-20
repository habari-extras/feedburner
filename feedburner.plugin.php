<?php
class FeedBurner extends Plugin
{
	/**
	 * Feed groups used in the dashboard statistics module
	 * The key is the title of the statistic,
	 * the value is an array of Feedburner feeds based on the array above ($feedburner_feeds)
	 *
	 * You shouldn't have to edit this, that's why it is not in the FormUI (options)
	 */
	private static $feed_groups= array(
		'entries' => array( 'introspection', 'collection' ),
		'comments' => array( 'comments' ),
	);

	/**
	 * Required Plugin Informations
	 */
	public function info()
	{
		return array(
			'name' => 'FeedBurner',
			'version' => '1.4',
			'url' => 'http://habariproject.org/',
			'author' =>	'Habari Community',
			'authorurl' => 'http://habariproject.org/',
			'license' => 'Apache License 2.0',
			'description' => 'Feedburner plugin for Habari',
			'copyright' => '2007'
		);
	}

	/**
	 * Saves default (example) data
	 */
	public function action_plugin_activation()
	{
		if ( !Options::get( 'feedburner:installed' ) ) {
			Options::set( 'feedburner:introspection', 'http://feeds.feedburner.com/HabariProject' );
			Options::set( 'feedburner:collection', 'http://feeds.feedburner.com/HabariProject' );
			Options::set( 'feedburner:comments', 'http://feeds.feedburner.com/HabariProject/comments' );
			self::reset_exclusions();
			Options::set( 'feedburner:installed', true );
		}
	}

	/**
	 * Reset exclusions list to default
	 * Adds FeedBurner, FeedValidator.org and Validome.org
	 */
	public function reset_exclusions()
	{
		Options::set( 'feedburner:exclude_agents', array(
			'FeedBurner/1.0 (http://www.FeedBurner.com)', // FeedBurner.com
			'FeedValidator/1.3', // FeedValidator.org
			) );
		Options::set( 'feedburner:exclude_ips', array(
			'212.162.14.235', // Validome.org
			) );
		return true;
	}

	/**
	 * When the AtomHandler is created, check what action called it
	 * If the action is set in our URL list, intercept and redirect to Feedburner
	 */
	public function action_init_atom()
	{
		$action= Controller::get_action();
		$feed_url= Options::get( 'feedburner:' . $action );
		$exclude_ips= Options::get( 'feedburner:exlude_ips' );
		$exclude_agents= Options::get( 'feedburner:exclude_agents' );

		if ( $feed_url != '' ) {
			if ( !in_array( $_SERVER['REMOTE_ADDR'], ( array ) $exclude_ips ) ) {
				if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && !in_array( $_SERVER['HTTP_USER_AGENT'], ( array ) $exclude_agents ) ) {
					ob_clean();
					header( 'Location: ' . $feed_url, TRUE, 302 );
					die();
				}
			}
		}
	}

	public function filter_admin_modules( $modules )
	{
		$modules['feedburner']= '<div class="options">&nbsp;</div><div class="modulecore">
			<h2>Feedburner Stats</h2><div class="handle">&nbsp;</div>' . "\n" .
			$this->theme_feedburner_stats() .
			'</div>';
		return $modules;
	}


	public function theme_feedburner_stats()
	{
		if ( Cache::has( 'feedburner_stats' ) ) {
			$stats= Cache::get( 'feedburner_stats' );
		}
		else {
			$stats= $this->get_stats();
			Cache::set( 'feedburner_stats', $stats );
		}

		$stats_table= '<ul class="items">'. "\n";
		foreach ( $stats as $key => $count ) {
			$stats_table.= '<li class="item clear">' . "\n";
			$stats_table.= '<span class="pct90">' . "{$key}</span>\n"; 
			$stats_table.= '<span class="comments pct10">' . "{$count}</span>\n";
			$stats_table.= "</li>\n";
		}
		$stats_table.= "</ul>\n";

		return $stats_table;
	}

	private function get_stats()
	{
		$stats= array();
		foreach ( self::$feed_groups as $type => $feeds ) {
			$readers= array();
			$reach= array();
			$reader_str= "FeedBurner Readers ({$type})";
			$reach_str= "FeedBurner Reach ({$type})";
			foreach ( $feeds as $feed ) {
				if ( $feed_url = Options::get( 'feedburner:' . $feed ) ) {
					$awareness_api= 'http://api.feedburner.com/awareness/1.0/GetFeedData?uri=' . $feed_url;
					$request= new RemoteRequest( $awareness_api );
					if ( !$request->execute() ) {
						return;
					}
					$xml= simplexml_load_string( $request->get_response_body() );
					if ( $xml['stat'] == 'fail' ) {
						$stat_str= "{$xml->err['msg']} ({$type})";
						$stats[$stat_str]= '';
					}
					else {
						$readers[$feed_url]= ( string ) $xml->feed->entry['circulation'];
						$reach[$feed_url]= ( string ) $xml->feed->entry['reach'];
						$stats[$reader_str]= array_sum( $readers );
						$stats[$reach_str]= array_sum( $reach );
					}
				}
			}
		}

		return $stats;
	}

	/**
	 * Add our menu to the FormUI for plugins.
	 *
	 * @param array $actions Array of menu items for this plugin.
	 * @param string $plugin_id A unique plugin ID, it needs to match ours.
	 * @return array Original array with our added menu.
	 */
	public function filter_plugin_config( $actions, $plugin_id ) {
		if ( $plugin_id == $this->plugin_id ) {
			$actions[]= 'Options';
			$actions[]= 'Reset Exclusions';
		}

		return $actions;
	}

	/**
	 * Handle calls from FormUI actions.
	 * Show the form to manage the plugin's options.
	 *
	 * @param string $plugin_id A unique plugin ID, it needs to match ours.
	 * @param string $action The menu item the user clicked.
	 */
	public function action_plugin_ui( $plugin_id, $action ) {
		if ( $plugin_id == $this->plugin_id ) {
			switch ( $action ) {
				case 'Options':
					$fb= new FormUI( 'feedburner' );
					$fb_introspection= $fb->add( 'text', 'introspection', 'Introspection:' );
					$fb_introspection->add_validator( 'validate_url' );
					$fb_collection= $fb->add( 'text', 'collection', 'Collection:' );
					$fb_collection->add_validator( 'validate_url' );
					$fb_comments= $fb->add( 'text', 'comments', 'Comments:' );
					$fb_comments->add_validator( 'validate_url' );
					$fb->add( 'fieldset', 'Feed Assignments', array( $fb_introspection, $fb_collection, $fb_comments ) );

					$fb_exclusions= $fb->add( 'static', 'exclusions', '<p>Exclusions will not be redirected to the Feedburner service.<br><strong>Do not remove default exclusions, else the plugin will break.</strong>' );
					$fb_agents= $fb->add( 'textmulti', 'exclude_agents', 'Agents to exclude', Options::get( 'feedburner:exclude_agents' ) );
					$fb_ips= $fb->add( 'textmulti', 'exclude_ips', 'IPs to exclude', Options::get( 'feedburner:exclude_ips' ) );
					$fb->add( 'fieldset', 'Exclusions', array( $fb_exclusions, $fb_agents, $fb_ips ) );

					$fb->on_success( array( $this, 'save_options' ) );
					$fb->out();
					break;
				case 'Reset Exclusions':
					if ( self::reset_exclusions() ) {
						$fb= new FormUI( 'feedburner' );
						$fb->add( 'static', 'reset_exclusions', '<p>The exclusions lists have been reset to the defaults.</p>' );
						$fb->set_option( 'save_button', false );
						$fb->out();
					}
					else {
						$fb= new FormUI( 'feedburner' );
						$fb->add( 'static', 'reset_exclusions', '<p>An error occurred while trying to reset the exclusions lists, please try again or report the problem.</p>' );
						$fb->set_option( 'save_button', false );
						$fb->out();
					}
					break;
			}
		}
	}

	/**
	 * Fail-safe method to force options to be saved in Habari's options table.
	 *
	 * @return bool Return true to force options to be saved in Habari's options table.
	 */
	public function save_options( $ui ) {
		return true;
	}

}
?>
