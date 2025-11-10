<?php
/**
 * Unlock modal shared between wizards.
 *
 * @package FixResume
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="unlock-modal" id="unlock-modal" aria-hidden="true">
	<div class="unlock-modal__backdrop" data-unlock-close></div>
	<div class="unlock-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="unlock-modal-title">
		<button type="button" class="unlock-modal__close" data-unlock-close aria-label="<?php esc_attr_e( 'Close modal', 'fixresume' ); ?>">&times;</button>
		<p class="eyebrow"><?php esc_html_e( 'Unlock downloads', 'fixresume' ); ?></p>
		<h2 id="unlock-modal-title"><?php esc_html_e( 'Get unlimited PDF & DOCX exports', 'fixresume' ); ?></h2>
		<ul class="unlock-modal__list">
			<li><?php esc_html_e( 'Unlimited PDF & DOCX downloads', 'fixresume' ); ?></li>
			<li><?php esc_html_e( 'ATS-ready layouts and AI templates', 'fixresume' ); ?></li>
			<li><?php esc_html_e( 'Cancel anytime inside Billing Portal', 'fixresume' ); ?></li>
		</ul>
		<div class="unlock-modal__actions">
			<button type="button" class="button primary ra-trial-button" data-plan="primary" data-unlock-start>
				<?php esc_html_e( 'Start 7-day trial', 'fixresume' ); ?>
			</button>
			<button type="button" class="button ghost" data-unlock-close>
				<?php esc_html_e( 'Maybe later', 'fixresume' ); ?>
			</button>
		</div>
	</div>
</div>
