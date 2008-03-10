<?php
class FeedBurner extends Plugin {

	/**
	 * FeedBurner URL List
	 * You MUST change this to your Feed Address
	 *
	 * introspection - Overall Collection Feed
	 * collection - Overall Collection Feed
	 * comments - Overall Comments Feed
	 *
	 * URL Example: http://feeds.feedburner.com/HabariProject
	 *
	 * Actions you do not define will not be filtered
	 */
	private static $feedburner_feeds= array(
		'introspection' => 'http://feeds.feedburner.com/HabariProject',
		'collection' => 'http://feeds.feedburner.com/HabariProject',
		'comments' => 'http://feeds.feedburner.com/HabariProject/comments',
	);

	/**
	 * Feed groups used in the dashboard statistics module
	 * The key is the title of the statistic,
	 * the value is an array of Feedburner feeds based on the array above ($feedburner_feeds)
	 *
	 * You shouldn't have to edit this
	 */
	private static $feed_groups= array(
		'entries' => array( 'introspection', 'collection' ),
		'comments' => array( 'comments' ),
	);

	/**
	 * Required Plugin Informations
	 */
	public function info() {
		return array(
			'name' => 'FeedBurner',
			'version' => '1.3',
			'url' => 'http://habariproject.org/',
			'author' =>	'Habari Community',
			'authorurl' => 'http://habariproject.org/',
			'license' => 'Apache License 2.0',
			'description' => 'Feedburner plugin for Habari',
			'copyright' => '2007'
		);
	}

	/**
	 * When the AtomHandler is created, check what action called it
	 * If the action is set in our URL list, intercept and redirect to Feedburner
	 */
	public function action_init_atom()
	{
		/* List of exclusions - You SHOULD NOT edit this! */
		$exclude_agents= array(
			'FeedBurner/1.0 (http://www.FeedBurner.com)', // FeedBurner.com
			'FeedValidator/1.3', // FeedValidator.org
		);
		$exclude_ips= array(
			'212.162.14.235', // Validome.org
		);
		
		/* DO NOT edit below! */
		$action= Controller::get_action();
		if ( isset( self::$feedburner_feeds[$action] ) ) {
			if ( !in_array( $_SERVER['REMOTE_ADDR'], $exclude_ips ) ) {
				if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && !in_array( $_SERVER['HTTP_USER_AGENT'], $exclude_agents ) ) {
					ob_clean();
					header( 'Location: ' . self::$feedburner_feeds[$action], TRUE, 302 );
					die();
				}
			}
		}
	}

	/**
	 * Add the FeedBurner statistics to the admin dashboard
	 *
	 * @param array $stats Statistic summary array used in the dashboard
	 * @return array Statistic summary array to which we appended Feedburner statistics
	 */
	public function filter_statistics_summary( $stats ) {
		foreach ( self::$feed_groups as $type => $feeds ) {
			$readers= array();
			$reach= array();
			$reader_str= "FeedBurner Readers ({$type})";
			$reach_str= "FeedBurner Reach ({$type})";
			foreach ( $feeds as $feed ) {
				if ( self::$feedburner_feeds[$feed] != '' ) {
					$awareness_api= 'http://api.feedburner.com/awareness/1.0/GetFeedData?uri=' . self::$feedburner_feeds[$feed];
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
						$readers[]= (string) $xml->feed->entry['circulation'];
						$reach[]= (string) $xml->feed->entry['reach'];
						$stats[$reader_str]= array_sum($readers);
						$stats[$reach_str]= array_sum($reach);
					}
				}
			}
		}
		
		return $stats;
	}

}
?>