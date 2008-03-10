<?php
class FeedBurner extends Plugin {
	
	/* FeedBurner URL List
	 * You MUST change this to your Feed Address.
	 *
	 * introspection - Overall Collection Feed
	 * collection - Overall Collection Feed
	 * comments - Overall Comments Feed
	 *
	 * URL Example: http://feeds.feedburner.com/HabariProject
	 *
	 * Actions you do not define will not be filtered.
	 */
	private static $feedburner_feeds= array(
		'introspection' => 'http://feeds.feedburner.com/HabariProject',
		'collection' => 'http://feeds.feedburner.com/HabariProject',
		'comments' => 'http://feeds.feedburner.com/HabariProject/comments',
	);

	/* Required Plugin Informations */
	public function info() {
		return array(
			'name' => 'FeedBurner',
			'version' => '1.2',
			'url' => 'http://habariproject.org/',
			'author' =>	'Habari Community',
			'authorurl' => 'http://habariproject.org/',
			'license' => 'Apache License 2.0',
			'description' => 'Feedburner plugin for Habari',
			'copyright' => '2007'
		);
	}
	
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
	
	public function filter_statistics_summary( $stats ) {
		foreach ( self::$feed_url as $type => $url ) {
			if ( $url != '' ) {
				$awareness_api= 'http://api.feedburner.com/awareness/1.0/GetFeedData?uri=' . $url;
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
					$readers= ( string ) $xml->feed->entry['circulation'];
					$reach= ( string ) $xml->feed->entry['reach'];
					
					$reader_str= "FeedBurner Readers ({$type})";
					$reach_str= "FeedBurner Reach ({$type})";
					$stats[$reader_str]= $readers;
					$stats[$reach_str]= $reach;
				}
			}
		}
		
		return $stats;
	}

}
?>
