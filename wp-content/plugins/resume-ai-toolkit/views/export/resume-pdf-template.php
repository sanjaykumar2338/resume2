<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$escape = static function ( $value ) {
    return esc_html( (string) $value );
};

$skills = $data['skills'] ?? '';
if ( is_string( $skills ) ) {
    $skills = array_filter( array_map( 'trim', explode( ',', $skills ) ) );
}
if ( ! is_array( $skills ) ) {
    $skills = [];
}

$employment = $data['employment'] ?? [];
$education  = $data['education'] ?? [];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Resume</title>
    <style>
        body { font-family: 'Inter', Arial, sans-serif; font-size: 13px; line-height: 1.45; color: #0f172a; margin: 0; padding: 32px 40px; }
        h1 { margin: 0 0 4px; font-size: 26px; }
        h2 { font-size: 15px; text-transform: uppercase; letter-spacing: 0.12em; color: #64748b; margin: 32px 0 12px; }
        h3 { margin: 0 0 4px; font-size: 14px; }
        p { margin: 0 0 6px; }
        .contact { color: #475569; font-size: 12px; margin-bottom: 12px; }
        .section { margin-bottom: 20px; }
        .item { margin-bottom: 16px; }
        .dates { font-size: 12px; color: #64748b; }
        .skills { display: flex; flex-wrap: wrap; gap: 6px; }
        .skill { border: 1px solid #e2e8f0; padding: 4px 10px; border-radius: 999px; font-size: 12px; }
    </style>
</head>
<body>
    <header>
        <h1><?php echo $escape( trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) ) ); ?></h1>
        <?php if ( ! empty( $data['job_title'] ) ) : ?>
            <p><?php echo $escape( $data['job_title'] ); ?></p>
        <?php endif; ?>
        <?php
        $contact = array_filter( [ $data['location'] ?? '', $data['email'] ?? '', $data['phone'] ?? '' ] );
        if ( $contact ) :
            ?>
            <p class="contact"><?php echo $escape( implode( ' • ', $contact ) ); ?></p>
        <?php endif; ?>
    </header>

    <?php if ( ! empty( $data['summary'] ) ) : ?>
        <section class="section">
            <h2><?php esc_html_e( 'Summary', 'resume-ai-toolkit' ); ?></h2>
            <p><?php echo nl2br( $escape( $data['summary'] ) ); ?></p>
        </section>
    <?php endif; ?>

    <?php if ( ! empty( $employment ) ) : ?>
        <section class="section">
            <h2><?php esc_html_e( 'Experience', 'resume-ai-toolkit' ); ?></h2>
            <?php foreach ( $employment as $role ) :
                $title   = trim( ( $role['title'] ?? '' ) . ' • ' . ( $role['company'] ?? '' ), ' •' );
                $dates   = trim( ( $role['start'] ?? '' ) . ' – ' . ( $role['end'] ?? '' ), ' –' );
                $summary = $role['summary'] ?? '';
                ?>
                <div class="item">
                    <?php if ( $title ) : ?><h3><?php echo $escape( $title ); ?></h3><?php endif; ?>
                    <?php if ( $dates ) : ?><p class="dates"><?php echo $escape( $dates ); ?></p><?php endif; ?>
                    <?php if ( $summary ) : ?><p><?php echo nl2br( $escape( $summary ) ); ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ( ! empty( $education ) ) : ?>
        <section class="section">
            <h2><?php esc_html_e( 'Education', 'resume-ai-toolkit' ); ?></h2>
            <?php foreach ( $education as $edu ) :
                $line  = trim( ( $edu['degree'] ?? '' ) . ', ' . ( $edu['school'] ?? '' ), ', ' );
                $dates = trim( ( $edu['start'] ?? '' ) . ' – ' . ( $edu['end'] ?? '' ), ' –' );
                ?>
                <div class="item">
                    <?php if ( $line ) : ?><h3><?php echo $escape( $line ); ?></h3><?php endif; ?>
                    <?php if ( $dates ) : ?><p class="dates"><?php echo $escape( $dates ); ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ( ! empty( $skills ) ) : ?>
        <section class="section">
            <h2><?php esc_html_e( 'Skills', 'resume-ai-toolkit' ); ?></h2>
            <div class="skills">
                <?php foreach ( $skills as $skill ) : ?>
                    <span class="skill"><?php echo $escape( $skill ); ?></span>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</body>
</html>
