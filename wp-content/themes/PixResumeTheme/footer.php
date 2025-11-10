	<footer class="site-footer">
		<div class="container footer-grid">
			<div>
				<a class="brand" href="#top"><?php bloginfo( 'name' ); ?></a>
				<p class="tagline">
					<?php
					$description = get_bloginfo( 'description', 'display' );
					echo esc_html( $description ? $description : __( 'Sharper wording. Better first impressions.', 'fixresume' ) );
					?>
				</p>
			</div>
			<div class="footer-links">
				<a href="<?php echo esc_url( home_url( '#how-it-works' ) ); ?>"><?php esc_html_e( 'Process', 'fixresume' ); ?></a>
				<a href="<?php echo esc_url( home_url( '#sample' ) ); ?>"><?php esc_html_e( 'Examples', 'fixresume' ); ?></a>
				<a href="<?php echo esc_url( home_url( '#faq' ) ); ?>"><?php esc_html_e( 'Support', 'fixresume' ); ?></a>
			</div>
			<p class="footnote">©
				<?php echo esc_html( date_i18n( 'Y' ) ); ?>
				<?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'Crafted for job seekers ready to stand out.', 'fixresume' ); ?></p>
		</div>
	</footer>

	<?php wp_footer(); ?>
</body>
</html>
