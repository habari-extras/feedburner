	<h2><a href="http://feedburner.google.com" title="Go to Feedburner">Feedburner Stats</a></h2><div class="handle">&nbsp;</div>
	<ul class="items">
<?php foreach ( $feedburner_stats as $key => $count ) : ?>
		<li class="item clear">
			<span class="pct90"><?php echo $key; ?></span>
			<span class="comments pct10"><?php echo $count; ?></span>
		</li>
<?php endforeach ?>
	</ul>
