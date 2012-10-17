<?php if ( !defined( 'HABARI_PATH' ) ) { die( 'No direct access' ); } ?>
	<ul class="items">
<?php foreach ( (array)$content->feedburner_stats as $key => $count ) : ?>
		<li class="item clear">
			<span class="pct90"><?php echo $key; ?></span>
			<span class="comments pct10"><?php echo $count; ?></span>
		</li>
<?php endforeach ?>
	</ul>
