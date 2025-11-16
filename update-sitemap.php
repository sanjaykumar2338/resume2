<?php
/**
 * Regenerates sitemap.xml with all published pages and posts.
 *
 * Usage:
 *   php update-sitemap.php
 *   or open https://www.resumeaitools.com/update-sitemap.php?key=YOUR_SECRET
 */

require_once __DIR__ . '/wp-load.php';

$urls = [];

$pages = get_pages( [ 'post_status' => 'publish' ] );
foreach ( $pages as $page ) {
    $urls[] = [
        'loc'     => get_permalink( $page->ID ),
        'lastmod' => get_post_modified_time( 'c', true, $page ),
    ];
}

$posts = get_posts( [
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
] );
foreach ( $posts as $post ) {
    $urls[] = [
        'loc'     => get_permalink( $post->ID ),
        'lastmod' => get_post_modified_time( 'c', true, $post ),
    ];
}

$entries = '';
foreach ( $urls as $item ) {
    $entries .= "    <url>\n";
    $entries .= '        <loc>' . esc_url( $item['loc'] ) . "</loc>\n";
    if ( ! empty( $item['lastmod'] ) ) {
        $entries .= '        <lastmod>' . esc_html( $item['lastmod'] ) . "</lastmod>\n";
    }
    $entries .= "    </url>\n";
}

$xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
$xml .= $entries;
$xml .= "</urlset>\n";

$written = file_put_contents( ABSPATH . 'sitemap.xml', $xml );

if ( php_sapi_name() === 'cli' ) {
    if ( false === $written ) {
        fwrite( STDERR, "Failed to write sitemap.xml\n" );
        exit( 1 );
    }
    fwrite( STDOUT, "sitemap.xml regenerated successfully\n" );
    exit( 0 );
}

wp_die( false === $written ? 'Failed to write sitemap.xml' : 'sitemap.xml updated successfully.' );
