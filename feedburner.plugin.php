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
	private static $feed_groups = array(
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
			'version' => '1.6',
			'url' => 'http://habariproject.org/',
			'author' =>	'Habari Community',
			'authorurl' => 'http://habariproject.org/',
			'license' => 'Apache License 2.0',
			'description' => 'Feedburner plugin for Habari',
			'copyright' => '2007'
		);
	}

	/**
	 * Add update beacon support
	 **/
	public function action_update_check()
	{
	 	Update::add( 'FeedBurner', '856031d0-3c7f-11dd-ae16-0800200c9a66', $this->info->version );
	}

	/**
	 * Saves default (example) data
	 */
	public function action_plugin_activation( $file )
	{
		if ( realpath( $file ) == __FILE__ ) {
			Modules::add( 'Feedburner' );
			if ( !Options::get( 'feedburner__installed' ) ) {
				Options::set( 'feedburner__introspection', 'HabariProject' );
				Options::set( 'feedburner__collection', 'HabariProject' );
				Options::set( 'feedburner__comments', 'HabariProject/comments' );
				self::reset_exclusions();
				Options::set( 'feedburner__installed', true );
			}
		}
	}

	public function action_plugin_deactivation( $file )
	{
		if ( realpath( $file ) == __FILE__ ) {
			Modules::remove_by_name( 'Feedburner' );
		}
	}

	public function filter_dash_modules( $modules )
	{
		$modules[]= 'Feedburner';
		$this->add_template( 'dash_feedburner', dirname( __FILE__ ) . '/dash_feedburner.php' );
		return $modules;
	}

	/**
	 * Reset exclusions list to default
	 * Adds FeedBurner, FeedValidator.org and Validome.org
	 */
	public function reset_exclusions()
	{
		Options::set( 'feedburner__exclude_agents', array(
			'FeedBurner/1.0 (http://www.FeedBurner.com)', // FeedBurner.com
			'FeedValidator/1.3', // FeedValidator.org
			) );
		Options::set( 'feedburner__exclude_ips', array(
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
		$action = Controller::get_action();
		$feed_uri = Options::get( 'feedburner__' . $action );
		$exclude_ips = Options::get( 'feedburner__exlude_ips' );
		$exclude_agents = Options::get( 'feedburner__exclude_agents' );

		if ( $feed_url != '' ) {
			if ( !in_array( $_SERVER['REMOTE_ADDR'], ( array ) $exclude_ips ) ) {
				if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && !in_array( $_SERVER['HTTP_USER_AGENT'], ( array ) $exclude_agents ) ) {
					ob_clean();
					header( 'Location: http://feedproxy.google.com/' . $feed_uri, TRUE, 302 );
					die();
				}
			}
		}
	}

	public function filter_dash_module_feedburner( $module, $module_id, $theme )
	{
		$theme->feedburner_stats = $this->theme_feedburner_stats();

		$module['content']= $theme->fetch( 'dash_feedburner' );
		return $module;
	}

	public function theme_feedburner_stats()
	{
		if ( Cache::has( 'feedburner_stats' ) ) {
			$stats = Cache::get( 'feedburner_stats' );
		}
		else {
			$stats = $this->get_stats();
			Cache::set( 'feedburner_stats', $stats );
		}

		return $stats;
	}

	private function get_stats()
	{
		$stats = array();
		foreach ( self::$feed_groups as $type => $feeds ) {
			$readers = array();
			$reach = array();
			$reader_str = "FeedBurner Readers ({$type})";
			$reach_str = "FeedBurner Reach ({$type})";
			foreach ( $feeds as $feed ) {
				if ( $feed_url = Options::get( 'feedburner__' . $feed ) ) {
					$awareness_api = 'https://feedburner.google.com/api/awareness/1.0/GetFeedData?uri=' . $feed_url;
					$request = new RemoteRequest( $awareness_api );
					if ( Error::is_error( $request->execute() ) ) {
						continue;
					}

					$xml = simplexml_load_string( $request->get_response_body() );
					if ( $xml['stat'] == 'fail' ) {
						$stat_str = "{$xml->err['msg']} ({$type})";
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
			$actions[]= 'Configure';
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
				case 'Configure':
					$fb = new FormUI( 'feedburner' );
					$fb_assignments = $fb->append( 'fieldset', 'feed_assignments', 'Feed Assignments' );
					$fb_introspection = $fb_assignments->append( 'text', 'introspection', 'feedburner__introspection', 'Introspection:' );
					$fb_collection = $fb_assignments->append( 'text', 'collection', 'feedburner__collection', 'Collection:' );
					$fb_comments = $fb_assignments->append( 'text', 'comments', 'feedburner__comments', 'Comments:' );

					$fb_exclusions = $fb->append( 'fieldset', 'exclusions', 'Exclusions' );
					$fb_exclusions_text = $fb_exclusions->append( 'static', 'exclusions', '<p>Exclusions will not be redirected to the Feedburner service.<br><strong>Do not remove default exclusions, else the plugin will break.</strong>' );
					$fb_agents = $fb_exclusions->append( 'textmulti', 'exclude_agents', 'feedburner__exclude_agents', 'Agents to exclude', Options::get( 'feedburner__exclude_agents' ) );
					$fb_ips = $fb_exclusions->append( 'textmulti', 'exclude_ips', 'feedburner__exclude_ips', 'IPs to exclude', Options::get( 'feedburner__exclude_ips' ) );
					$fb->append( 'submit', 'save', _t( 'Save' ) );

					$fb->set_option( 'success_message', _t( 'Configuration saved' ) );
					$fb->out();
					break;
				case 'Reset Exclusions':
					if ( self::reset_exclusions() ) {
						$fb = new FormUI( 'feedburner' );
						$fb->append( 'static', 'reset_exclusions', 'feedburner__reset_exclusions', '<p>The exclusions lists have been reset to the defaults.</p>' );
						$fb->set_option( 'save_button', false );
						$fb->out();
					}
					else {
						$fb = new FormUI( 'feedburner' );
						$fb->append( 'static', 'reset_exclusions', 'feedburner__reset_exclusions', '<p>An error occurred while trying to reset the exclusions lists, please try again or report the problem.</p>' );
						$fb->set_option( 'save_button', false );
						$fb->out();
					}
					break;
			}
		}
	}

}
?>
