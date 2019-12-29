<?php get_header();

global $porto_settings;
?>

<div id="content" class="no-content">
	<div class="container">
		<section class="page-not-found">
			<div class="row">
				<div class="col-lg-12">
					<div class="page-not-found-main">
				<?php if ( $porto_settings['error-block'] ) : ?>
						<?php echo do_shortcode( '[porto_block name="' . $porto_settings['error-block'] . '"]' ); ?>
				<?php endif; ?>
					</div>
				</div>
			</div>
		</section>
	</div>
</div>

<?php get_footer(); ?>