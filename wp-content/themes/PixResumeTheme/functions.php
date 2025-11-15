<?php
/**
 * Theme bootstrap for FixResume.
 *
 * @package FixResume
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function (): void {
    if (!get_option('users_can_register')) {
        update_option('users_can_register', 1);
    }
});

add_action('register_form', function (): void {
    $password = isset($_POST['user_pass']) ? wp_unslash($_POST['user_pass']) : '';
    $confirm  = isset($_POST['user_pass_confirm']) ? wp_unslash($_POST['user_pass_confirm']) : '';
    ?>
    <p>
        <label for="user_pass"><?php esc_html_e('Password', 'fixresume'); ?><br/>
            <input type="password" name="user_pass" id="user_pass" class="input" value="<?php echo esc_attr($password); ?>" autocomplete="new-password"/>
        </label>
    </p>
    <p>
        <label for="user_pass_confirm"><?php esc_html_e('Confirm password', 'fixresume'); ?><br/>
            <input type="password" name="user_pass_confirm" id="user_pass_confirm" class="input" value="<?php echo esc_attr($confirm); ?>" autocomplete="new-password"/>
        </label>
    </p>
    <?php
});

add_filter('registration_errors', function ($errors, $sanitized_user_login, $user_email) {
    $password = isset($_POST['user_pass']) ? trim(wp_unslash($_POST['user_pass'])) : '';
    $confirm  = isset($_POST['user_pass_confirm']) ? trim(wp_unslash($_POST['user_pass_confirm'])) : '';

    if (empty($password)) {
        $errors->add('user_pass', __('Please choose a password.', 'fixresume'));
    } elseif ($password !== $confirm) {
        $errors->add('user_pass_confirm', __('Passwords do not match.', 'fixresume'));
    }

    return $errors;
}, 10, 3);

add_action('user_register', function ($user_id) {
    if (empty($_POST['user_pass'])) {
        return;
    }

    $password = wp_unslash($_POST['user_pass']);
    wp_set_password($password, $user_id);
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);
}, 10, 1);

add_filter('registration_redirect', function ($redirect_to) {
    return home_url('/dashboard/');
});

add_action('after_setup_theme', function (): void {
    load_theme_textdomain('fixresume', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    register_nav_menus([
        'primary' => __('Primary Menu', 'fixresume'),
    ]);
});

function fixresume_ensure_blog_page(): void
{
    $blog_page = get_page_by_path('blog');
    if (!$blog_page) {
        $page_id = wp_insert_post(
            [
                'post_title'   => __('Blog', 'fixresume'),
                'post_name'    => 'blog',
                'post_content' => '',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]
        );

        if (is_wp_error($page_id)) {
            return;
        }

        update_post_meta($page_id, '_wp_page_template', 'page-blog.php');
    } else {
        update_post_meta($blog_page->ID, '_wp_page_template', 'page-blog.php');
    }
}

add_action('after_switch_theme', 'fixresume_ensure_blog_page');
add_action('init', 'fixresume_ensure_blog_page');

function fixresume_seed_primary_menu(): void
{
    $menu_name = __('Primary Navigation', 'fixresume');
    $menu_obj  = wp_get_nav_menu_object($menu_name);
    if (!$menu_obj) {
        $menu_id = wp_create_nav_menu($menu_name);
    } else {
        $menu_id = (int) $menu_obj->term_id;
    }

    if (is_wp_error($menu_id) || ! $menu_id) {
        return;
    }

    $locations = get_theme_mod('nav_menu_locations');
    if (!is_array($locations)) {
        $locations = [];
    }

    if (!isset($locations['primary']) || (int) $locations['primary'] !== $menu_id) {
        $locations['primary'] = $menu_id;
        set_theme_mod('nav_menu_locations', $locations);
    }

    $existing_items = wp_get_nav_menu_items($menu_id);
    if (!empty($existing_items)) {
        return; // Admin already customized the menu.
    }

    $menu_items = [
        [
            'title'   => __('How it works', 'fixresume'),
            'url'     => home_url('#how-it-works'),
            'classes' => '',
        ],
        [
            'title'   => __('Sample suggestions', 'fixresume'),
            'url'     => home_url('#sample'),
            'classes' => '',
        ],
        [
            'title'   => __('Blogs', 'fixresume'),
            'url'     => home_url('/blog/'),
            'classes' => '',
        ],
        [
            'title'   => __('FAQ', 'fixresume'),
            'url'     => home_url('#faq'),
            'classes' => '',
        ],
        [
            'title'   => __('Upload resume', 'fixresume'),
            'url'     => home_url('#upload'),
            'classes' => 'cta-link',
        ],
    ];

    foreach ($menu_items as $item) {
        wp_update_nav_menu_item(
            $menu_id,
            0,
            [
                'menu-item-title'  => $item['title'],
                'menu-item-url'    => esc_url_raw($item['url']),
                'menu-item-status' => 'publish',
                'menu-item-type'   => 'custom',
                'menu-item-classes'=> $item['classes'],
            ]
        );
    }
}

add_action('after_switch_theme', 'fixresume_seed_primary_menu');
add_action('init', 'fixresume_seed_primary_menu');

if (!function_exists('fixresume_nav_auth_items')) {
    function fixresume_nav_auth_items(): string
    {
        if (is_user_logged_in()) {
            return sprintf(
                '<li><a href="%1$s">%2$s</a></li><li><a href="%3$s">%4$s</a></li>',
                esc_url(home_url('/dashboard/')),
                esc_html__('Dashboard', 'fixresume'),
                esc_url(wp_logout_url(home_url())),
                esc_html__('Sign out', 'fixresume')
            );
        }

        return sprintf(
            '<li><a href="%1$s">%2$s</a></li><li><a href="%3$s">%4$s</a></li>',
            esc_url(wp_login_url(home_url('/dashboard/'))),
            esc_html__('Sign in', 'fixresume'),
            esc_url(wp_registration_url()),
            esc_html__('Sign up', 'fixresume')
        );
    }
}

add_filter('wp_nav_menu_items', function ($items, $args) {
    if (!isset($args->theme_location) || 'primary' !== $args->theme_location) {
        return $items;
    }

    return $items . fixresume_nav_auth_items();
}, 10, 2);

add_action('wp_enqueue_scripts', function (): void {
    $is_logged_in = is_user_logged_in();
    $can_download = $is_logged_in && function_exists('rai_user_can_download') ? rai_user_can_download(get_current_user_id()) : false;
    $pricing_url = home_url('/pricing/');
    $current_user_email = '';
    if ($is_logged_in) {
        $current_user_email = wp_get_current_user()->user_email;
    }

    wp_enqueue_style(
        'fixresume-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
        [],
        null
    );

    $main_css_path = get_template_directory() . '/assets/css/main.css';
    $main_css_version = file_exists($main_css_path) ? filemtime($main_css_path) : null;

    wp_enqueue_style(
        'fixresume-main',
        get_template_directory_uri() . '/assets/css/main.css',
        ['fixresume-fonts'],
        $main_css_version
    );

    wp_enqueue_script(
        'sweetalert2',
        'https://cdn.jsdelivr.net/npm/sweetalert2@11',
        [],
        null,
        true
    );

    wp_enqueue_script(
        'html-docx-js',
        'https://cdn.jsdelivr.net/npm/html-docx-js/dist/html-docx.min.js',
        [],
        null,
        true
    );

    wp_enqueue_script(
        'jspdf',
        'https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js',
        [],
        null,
        true
    );

    $paywall_js_path = get_template_directory() . '/assets/js/paywall.js';
    $paywall_js_version = file_exists($paywall_js_path) ? filemtime($paywall_js_path) : null;

    wp_enqueue_script(
        'fixresume-paywall',
        get_template_directory_uri() . '/assets/js/paywall.js',
        ['sweetalert2'],
        $paywall_js_version,
        true
    );

    wp_localize_script(
        'fixresume-paywall',
        'fixResumePaywall',
        [
            'pricingUrl'        => esc_url_raw($pricing_url),
            'checkoutEndpoint'  => esc_url_raw(rest_url('resume-ai/v1/checkout')),
            'portalEndpoint'    => esc_url_raw(rest_url('resume-ai/v1/portal')),
            'nonce'             => wp_create_nonce('wp_rest'),
            'isLoggedIn'        => $is_logged_in,
            'currentUserEmail'  => $current_user_email,
            'messages'          => [
                'promptTitle' => esc_html__('Start your plan', 'fixresume'),
                'promptText'  => esc_html__('Enter the email you want to associate with downloads.', 'fixresume'),
                'promptLabel' => esc_html__('Email', 'fixresume'),
                'success'     => esc_html__('Redirecting you to Stripe…', 'fixresume'),
                'error'       => esc_html__('We couldn’t start checkout. Please try again.', 'fixresume'),
            ],
        ]
    );

    $app_js_path = get_template_directory() . '/assets/js/app.js';
    $app_js_version = file_exists($app_js_path) ? filemtime($app_js_path) : null;

    wp_enqueue_script(
        'fixresume-app',
        get_template_directory_uri() . '/assets/js/app.js',
        ['sweetalert2', 'html-docx-js', 'jspdf', 'fixresume-paywall'],
        $app_js_version,
        true
    );

    wp_localize_script(
        'fixresume-app',
        'fixResumeApi',
        [
            'endpoint'    => esc_url_raw(rest_url('resume-ai/v1/optimize')),
            'currentUserEmail' => $current_user_email,
            'messages'    => [
                'loading'  => esc_html__('Analyzing your resume…', 'fixresume'),
                'success'  => esc_html__('Suggestions ready!', 'fixresume'),
                'missing'  => esc_html__('Please select a resume file before requesting suggestions.', 'fixresume'),
                'error'    => esc_html__('We couldn’t analyze your resume. Please try again.', 'fixresume'),
                'copy'     => esc_html__('Suggestions copied to clipboard', 'fixresume'),
                'copyFail' => esc_html__('Unable to copy suggestions. Please copy manually.', 'fixresume'),
                'download' => esc_html__('Your optimized resume is downloading.', 'fixresume'),
                'downloadFail' => esc_html__('No optimized resume available yet. Please rerun the analysis.', 'fixresume'),
                'downloadLabel' => esc_html__('Download optimized resume', 'fixresume'),
            ],
            'canDownload' => $can_download,
            'pricingUrl'  => esc_url_raw($pricing_url),
        ]
    );

    if (is_page_template('page-enhance-resume.php')) {
        $enhance_js_path = get_template_directory() . '/assets/js/enhance-wizard.js';
        wp_enqueue_script(
            'fixresume-enhance',
            get_template_directory_uri() . '/assets/js/enhance-wizard.js',
            ['sweetalert2', 'html-docx-js', 'jspdf', 'fixresume-paywall'],
            file_exists($enhance_js_path) ? filemtime($enhance_js_path) : null,
            true
        );

        wp_localize_script(
            'fixresume-enhance',
            'resumeEnhance',
            [
                'optimizeEndpoint' => esc_url_raw(rest_url('resume-ai/v1/optimize')),
                'exportEndpoint'   => esc_url_raw(rest_url('resume-ai/v1/export')),
                'storageKey'       => 'rai_wizard_v1',
                'canDownload'      => $can_download,
                'pricingUrl'       => esc_url_raw($pricing_url),
                'messages'         => [
                    'loading'        => esc_html__('Analyzing your resume…', 'fixresume'),
                    'success'        => esc_html__('Suggestions ready!', 'fixresume'),
                    'error'          => esc_html__('We couldn’t analyze your resume. Please try again.', 'fixresume'),
                    'missingFile'    => esc_html__('Please upload a resume before continuing.', 'fixresume'),
                    'missingAnalysis'=> esc_html__('Run an analysis first.', 'fixresume'),
                    'download'       => esc_html__('Your optimized resume is downloading.', 'fixresume'),
                    'copied'         => esc_html__('Suggestions copied to clipboard.', 'fixresume'),
                    'copyError'      => esc_html__('Unable to copy suggestions. Please try again.', 'fixresume'),
                    'exportError'    => esc_html__('We couldn’t export your resume. Please try again.', 'fixresume'),
                    'exporting'      => esc_html__('Preparing your download…', 'fixresume'),
                ],
            ]
        );
    }

    if (is_page_template('page-resume-builder.php')) {
        $builder_css_path = get_template_directory() . '/assets/css/builder.css';
        wp_enqueue_style(
            'fixresume-builder',
            get_template_directory_uri() . '/assets/css/builder.css',
            ['fixresume-main'],
            file_exists($builder_css_path) ? filemtime($builder_css_path) : null
        );

        $builder_js_path = get_template_directory() . '/assets/js/builder.js';
        wp_enqueue_script(
            'fixresume-builder',
            get_template_directory_uri() . '/assets/js/builder.js',
            ['sweetalert2', 'fixresume-paywall'],
            file_exists($builder_js_path) ? filemtime($builder_js_path) : null,
            true
        );

        $builder_prefill = [];
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $builder_prefill = [
                'first_name' => $current_user->first_name ?: '',
                'last_name'  => $current_user->last_name ?: '',
                'email'      => $current_user->user_email ?: '',
            ];
        }

        wp_localize_script(
            'fixresume-builder',
            'fixResumeBuilder',
            [
                'endpoint'       => esc_url_raw(rest_url('resume-ai/v1/builder')),
                'exportEndpoint' => esc_url_raw(rest_url('resume-ai/v1/export')),
                'messages'       => [
                    'loading'        => esc_html__('Composing your resume…', 'fixresume'),
                    'success'        => esc_html__('Resume updated!', 'fixresume'),
                    'error'          => esc_html__('We couldn’t generate your resume. Please try again.', 'fixresume'),
                    'errorTitle'     => esc_html__('Something went wrong', 'fixresume'),
                    'copy'           => esc_html__('Resume copied to clipboard', 'fixresume'),
                    'copyFail'       => esc_html__('Unable to copy resume. Please try manually.', 'fixresume'),
                    'generateFirst'  => esc_html__('Please generate the resume first.', 'fixresume'),
                    'requireName'    => esc_html__('Please provide at least your first and last name.', 'fixresume'),
                    'requireSummary' => esc_html__('Add a draft summary before improving it.', 'fixresume'),
                    'exportError'    => esc_html__('We could not build the PDF. Downloading the text version instead.', 'fixresume'),
                    'exportSuccess'  => esc_html__('PDF downloaded.', 'fixresume'),
                    'bulletSuccess'  => esc_html__('Bullet rewritten.', 'fixresume'),
                    'bulletError'    => esc_html__('We couldn’t rewrite that bullet. Try again.', 'fixresume'),
                    'bulletMissing'  => esc_html__('Add a bullet before asking AI to rewrite it.', 'fixresume'),
                ],
                'labels'        => [
                    'summary'     => esc_html__('Professional summary', 'fixresume'),
                    'education'   => esc_html__('Education', 'fixresume'),
                    'skills'      => esc_html__('Skills', 'fixresume'),
                    'experience'  => esc_html__('Experience', 'fixresume'),
                    'placeholder' => esc_html__('Fill the form to see your resume preview.', 'fixresume'),
                    'rewriteBullet'=> esc_html__('Rewrite bullet', 'fixresume'),
                ],
                'prefill'     => $builder_prefill,
                'canDownload' => $can_download,
                'pricingUrl'  => esc_url_raw($pricing_url),
            ]
        );
    }
});
