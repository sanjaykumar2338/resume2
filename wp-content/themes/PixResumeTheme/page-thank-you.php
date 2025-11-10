<?php
/**
 * Template Name: Thank You Page
 *
 * @package FixResume
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$builder_url = home_url( '/resume-builder/?welcome=1' );
$session_id  = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<main class="thank-you container">
	<section class="thank-you-panel">
		<h1><?php esc_html_e( 'Payment received – you’re all set!', 'fixresume' ); ?></h1>
		<p><?php esc_html_e( 'Your subscription is active. Use the button below to return to the resume builder and start downloading your optimized resumes.', 'fixresume' ); ?></p>
		<?php if ( $session_id ) : ?>
			<p class="thank-you-session"><?php printf( esc_html__( 'Session: %s', 'fixresume' ), esc_html( $session_id ) ); ?></p>
		<?php endif; ?>
		<a class="button primary" id="thankyou-return" href="<?php echo esc_url( $builder_url ); ?>">
			<?php esc_html_e( 'Continue to builder', 'fixresume' ); ?>
		</a>
	</section>
	<section class="thank-you-support">
		<h2><?php esc_html_e( 'Need help?', 'fixresume' ); ?></h2>
		<p><?php esc_html_e( 'If you have trouble accessing downloads or need a receipt, reach out to support and we’ll respond quickly.', 'fixresume' ); ?></p>
		<p><a href="mailto:support@example.com"><?php esc_html_e( 'support@example.com', 'fixresume' ); ?></a></p>
	</section>
</main>

<?php
get_footer();

?>
<script>
(function () {
  try {
    var stored = sessionStorage.getItem('rai_return');
    if (stored) {
      var btn = document.getElementById('thankyou-return');
      if (btn) {
        btn.href = stored;
      }
      sessionStorage.removeItem('rai_return');
      window.location.href = stored;
    }
  } catch (error) {
    console.warn(error);
  }
})();
</script>
