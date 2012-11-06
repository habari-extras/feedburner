<?php if ( !defined( 'HABARI_PATH' ) ) { die( 'No direct access' ); }

class FeedBurner extends Plugin
{
	private static $version = 2.1;

	/**
	 * Feed groups used in the dashboard statistics module
	 * The key is the title of the statistic,
	 * the value is an array of Feedburner feeds based on the array above ($feedburner_feeds)
	 *
	 * You shouldn't have to edit this, that's why it is not in the FormUI (options)
	 */
	private static $feed_groups = array(
		'entries' => array( 'collection' ),
		'comments' => array( 'comments' ),
	);

	/**
	 * The help message - it provides a larger explanation of what this plugin
	 * does and how to use it.
	 *
	 * @return string
	 */
	public function help()
	{
		$help = '<p>'. _t( 'Feedburner plugin for Habari allows you to redirect your feeds to FeedBurner. It also adds a dashboard module displaying the feed statistics for each feed.' ) . '</p>';
		$help .= '<h3>' . _t( 'Usage:') .'</h3>';
		$help .= '<ul><li>' . _t( 'Feed Assignments: Enter the name you\'ve assigned to your respective entries and comments feeds on Feedburner. This is the last part of the FeedBurner URL. For example, if your FeedBurner feed URL is http://feeds.feedburner.com/MainFeed, then enter "MainFeed" into the appropriate box.') . '</li>';
		$help .= '<li>' . _t( 'Exclusions: Use this section to specify user agents and IP addresses that you do not wish to be redirected to FeedBurner. The default values provided are there to prevent FeedBurner\'s bots being redirected back to itself, so do NOT delete these.') . '</li>';
		$help .= '</ul></p>';
		return $help;
	}

	/**
	 * Saves default (example) data
	 */
	public function action_plugin_activation( $file )
	{
		if ( !Options::get( 'feedburner__installed' ) ) {
			Options::set( 'feedburner__collection', '' );
			Options::set( 'feedburner__comments', '' );
			self::reset_exclusions();
			Options::set( 'feedburner__installed', true );
		}
	}

	/**
	 * Deletes old, unused option after upgrading
	 */
	public function action_init()
	{
		if ( Options::get( 'feedburner__introspection' ) ) {
			Options::delete( 'feedburner__introspection' );
		}
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

		if ( $feed_uri != '' && (!isset( URL::get_matched_rule()->named_arg_values['index']) || URL::get_matched_rule()->named_arg_values['index'] == 1 ) ) {
			if ( !in_array( $_SERVER['REMOTE_ADDR'], ( array ) $exclude_ips ) ) {
				if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && !in_array( $_SERVER['HTTP_USER_AGENT'], ( array ) $exclude_agents ) ) {
					ob_clean();
					header( 'Location: http://feedproxy.google.com/' . $feed_uri, TRUE, 302 );
					die();
				}
			}
		}
	}

	/**
	 * Add our menu to the FormUI for plugins.
	 *
	 * @param array $actions Array of menu items for this plugin.
	 * @param string $plugin_id A unique plugin ID, it needs to match ours.
	 * @return array Original array with our added menu.
	 */
	public function filter_plugin_config( $actions, $plugin_id )
	{
		if ( $plugin_id == $this->plugin_id ) {
			$actions['reset'] = _t( 'Reset Exclusions' );
		}

		return $actions;
	}

	public function configure()
	{
		$fb = new FormUI( 'feedburner' );
		$fb_assignments = $fb->append( 'fieldset', 'feed_assignments', _t( 'Feed Assignments' ) );
		$fb_collection = $fb_assignments->append( 'text', 'collection', 'feedburner__collection', _t( 'Site-wide Posts Atom Feed:' ) );
		$fb_comments = $fb_assignments->append( 'text', 'comments', 'feedburner__comments', _t( 'Site-wide Comment Atom Feed:' ) );

		$fb_exclusions = $fb->append( 'fieldset', 'exclusions', _t( 'Exclusions' ) );
		$fb_exclusions_text = $fb_exclusions->append( 'static', 'exclusions', '<p>'._t( 'Exclusions will not be redirected to the Feedburner service.' ).'<br><strong>'._t( 'Do not remove default exclusions, else the plugin will break.' ).'</strong>' );
		$fb_agents = $fb_exclusions->append( 'textmulti', 'exclude_agents', 'feedburner__exclude_agents', _t( 'Agents to exclude' ) );
		$fb_ips = $fb_exclusions->append( 'textmulti', 'exclude_ips', 'feedburner__exclude_ips', _t( 'IPs to exclude' ) );
		$fb->append( 'submit', 'save', _t( 'Save' ) );

		$fb->set_option( 'success_message', _t( 'Configuration saved' ) );
		$fb->out();
	}

	public function action_plugin_ui_reset( $plugin_id, $action )
	{
		if ( self::reset_exclusions() ) {
			$fb = new FormUI( 'feedburner' );
			$fb->append( 'static', 'reset_exclusions', '<p>'._t( 'The exclusions lists have been reset to the defaults.') .'</p>' );
			$fb->set_option( 'save_button', false );
			$fb->out();
		}
		else {
			$fb = new FormUI( 'feedburner' );
			$fb->append( 'static', 'reset_exclusions', '<p>'._t( 'An error occurred while trying to reset the exclusions lists, please try again or report the problem.' ).'</p>' );
			$fb->set_option( 'save_button', false );
			$fb->out();
		}
	}

	public function action_upgrade( $last_version )
	{
		if( $last_version == 2.0 ) {
			$block = DB::get_row('SELECT * FROM {blocks} WHERE type = :type;', array( 'type' => 'feedburner' ), 'Block' );
			if( $block ) {
				$block->delete();
			}
		}
	}

}
?>
