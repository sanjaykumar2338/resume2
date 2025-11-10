<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$escape_multi = static function ( $value ) {
    return nl2br( esc_html( (string) $value ) );
};

$score = isset( $data['score'] ) ? (int) $data['score'] : null;
$document = $data['resume_document'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title><?php esc_html_e( 'Enhanced Resume', 'resume-ai-toolkit' ); ?></title>
    <style>
        body { font-family: 'Inter', Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #0f172a; margin: 0; padding: 32px 40px; background: #f8fafc; }
        h1 { font-size: 26px; margin: 0 0 6px; }
        h2 { font-size: 18px; margin: 24px 0 12px; }
        p { margin: 0 0 10px; }
        .score { font-size: 32px; font-weight: 700; color: #059669; }
        .section { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 18px 20px; margin-bottom: 18px; }
        .preview { border: 1px solid #cbd5f5; background: #fff; padding: 18px; border-radius: 16px; }
        .preview h3 { margin-top: 0; }
    </style>
</head>
<body>
    <header class="section">
        <p class="eyebrow"><?php esc_html_e( 'AI Insights', 'resume-ai-toolkit' ); ?></p>
        <h1><?php esc_html_e( 'Resume enhancement report', 'resume-ai-toolkit' ); ?></h1>
        <p class="score"><?php echo esc_html( null === $score ? '--' : $score . '/100' ); ?></p>
    </header>

    <section class="section">
        <h2><?php esc_html_e( 'Grammar & Clarity', 'resume-ai-toolkit' ); ?></h2>
        <p><?php echo $escape_multi( $data['grammar'] ?? '' ); ?></p>
    </section>

    <section class="section">
        <h2><?php esc_html_e( 'Keywords (ATS)', 'resume-ai-toolkit' ); ?></h2>
        <p><?php echo $escape_multi( $data['keywords'] ?? '' ); ?></p>
    </section>

    <section class="section">
        <h2><?php esc_html_e( 'Formatting & Structure', 'resume-ai-toolkit' ); ?></h2>
        <p><?php echo $escape_multi( $data['formatting'] ?? '' ); ?></p>
    </section>

    <?php if ( ! empty( $data['summary'] ) ) : ?>
        <section class="section">
            <h2><?php esc_html_e( 'Executive Summary', 'resume-ai-toolkit' ); ?></h2>
            <p><?php echo $escape_multi( $data['summary'] ); ?></p>
        </section>
    <?php endif; ?>

    <?php if ( $document ) : ?>
        <section class="section preview">
            <h2><?php esc_html_e( 'Preview', 'resume-ai-toolkit' ); ?></h2>
            <div class="preview-body"><?php echo $document; ?></div>
        </section>
    <?php endif; ?>
</body>
</html>
