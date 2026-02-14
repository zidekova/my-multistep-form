<?php
/* 
Plugin Name: My Multistep Form
Description: 7-krokový multistep formulár cez shortcode [my_multistep_form]
Version: 0.1.0
Author: Mária Žideková
*/

if ( ! defined('ABSPATH') ) exit;

define('MMF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MMF_PLUGIN_PATH', plugin_dir_path(__FILE__));

require_once __DIR__ . '/includes/class-shortcode.php';

// Registrácia shortcodu
add_action('init', function(){
    MMF_Shortcode::init();
});

// Enqueue štýlov a skriptov len tam, kde je shortcode
add_action('wp_enqueue_scripts', function() {
    // Jednoduchá detekcia – načítame vždy na frontende (prípadne urobíme presnejšiu detekciu cez has_shortcode v the_content)
    wp_register_style('mmf-css', MMF_PLUGIN_URL . 'assets/css/multistep-form.css', [], '0.1.0');
    // Register Font Awesome via CDN so icons load reliably
    wp_register_style('mmf-fa', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0');
    wp_register_script(
        'mmf-js',
        MMF_PLUGIN_URL . 'assets/js/multistep-form.js',
        [],
        '0.1.0',
        true
    );


    // Localize (texty, i18n, nastavenia)
    wp_localize_script('mmf-js', 'MMF_I18N', [
        'next' => __('Ďalej', 'my-multistep-form'),
        'prev' => __('Späť', 'my-multistep-form'),
        'submit' => __('Odoslať', 'my-multistep-form'),
        'step' => __('Krok', 'my-multistep-form'),
        'of' => __('z', 'my-multistep-form'),
    ]);
});

// Handle form submission and send email to site admin (or DJ)
add_action('admin_post_nopriv_mmf_submit', 'mmf_handle_submit');
add_action('admin_post_mmf_submit', 'mmf_handle_submit');

function mmf_handle_submit() {
    if ( ! isset($_POST['mmf_nonce']) || ! wp_verify_nonce($_POST['mmf_nonce'], 'mmf_submit_action') ) {
        wp_die('Neplatná požiadavka.');
    }

    // sanitize and collect fields
    $package = sanitize_text_field($_POST['package'] ?? '');
    $event_type = sanitize_text_field($_POST['event_type'] ?? '');
    $duration = sanitize_text_field($_POST['duration'] ?? '');
    $region = sanitize_text_field($_POST['region'] ?? '');
    $extensions = isset($_POST['extensions']) && is_array($_POST['extensions']) ? array_map('sanitize_text_field', $_POST['extensions']) : [];
    $final_price = sanitize_text_field($_POST['final_price'] ?? '');
    $event_date = sanitize_text_field($_POST['event_date'] ?? '');
    $client_message = sanitize_textarea_field($_POST['contact_message'] ?? '');
    $client_name = sanitize_text_field($_POST['contact_name'] ?? '');
    $client_email = sanitize_email($_POST['contact_email'] ?? '');
    $client_phone = sanitize_text_field($_POST['contact_phone'] ?? '');

    // friendly labels
    $package_map = [
        'cista-zabava' => 'Čistá zábava',
        'atmosfera' => 'Atmosféra',
        'wow-efekt' => 'Eventový WOW efekt'
    ];
    $event_map = [
        'svadba' => 'Svadba',
        'firemna' => 'Firemná akcia',
        'stuzkova' => 'Stužková',
        'oslava' => 'Oslava'
    ];
    $duration_map = [
        'do-7-hodin' => 'Do 7 hodín',
        '8-9-hodin' => '8-9 hodín',
        '10-a-viac-hodin' => '10 a viac hodín'
    ];
    $extensions_map = [
        'prvy-tanec-oblaky' => 'Prvý tanec v oblakoch (+150 €)',
        'vecerny-wow-moment' => 'Večerný WOW moment (+240 €)',
        'svadobny-ceremonial' => 'Svadobný ceremoniál (+150 €)'
    ];

    // included extensions mapping (same as front-end)
    $included = [
        'wow-efekt' => ['vecerny-wow-moment']
    ];

    // Build extensions list with note if included
    $ext_lines = [];
    foreach ($extensions as $ext) {
        $label = $extensions_map[$ext] ?? $ext;
        $note = (isset($included[$package]) && in_array($ext, $included[$package])) ? ' (zahrnuté v balíku)' : '';
        $ext_lines[] = $label . $note;
    }
    if (empty($ext_lines)) $ext_lines[] = 'Žiadne';

    // Compose email
    $site_name = get_bloginfo('name');
    $to = get_option('admin_email');
    $subject = sprintf('Nová objednávka z %s', $site_name);

    $body = "Nová predbežná objednávka bola odoslaná:\n\n";
    $body .= "Balík: " . ($package_map[$package] ?? $package) . "\n";
    $body .= "Typ akcie: " . ($event_map[$event_type] ?? $event_type) . "\n";
    $body .= "Dĺžka programu: " . ($duration_map[$duration] ?? $duration) . "\n";
    $body .= "Lokalita / doprava: " . ($region ? $region : 'Nedefinované') . "\n";
    $body .= "Rozšírenia: \n - " . implode("\n - ", $ext_lines) . "\n";
    $body .= "Finálna cena: € " . ($final_price !== '' ? $final_price : 'Nedefinovaná') . "\n";
    $body .= "Dátum akcie: " . ($event_date ? $event_date : 'Nedefinovaný') . "\n\n";
    $body .= "Kontaktné údaje:\n";
    $body .= "Meno: " . ($client_name ? $client_name : '-') . "\n";
    $body .= "Email: " . ($client_email ? $client_email : '-') . "\n";
    $body .= "Telefón: " . ($client_phone ? $client_phone : '-') . "\n\n";
    $body .= "Správa od klienta:\n" . ($client_message ? $client_message : '-') . "\n";

    $headers = [];
    if ($client_email) {
        // set Reply-To so DJ can reply directly
        $headers[] = 'Reply-To: ' . $client_name . ' <' . $client_email . '>';
    }

    // Send mail
    wp_mail($to, $subject, $body, $headers);

    // Redirect back with success flag
    $redirect = wp_get_referer() ? add_query_arg('mmf_sent', '1', wp_get_referer()) : home_url('/');
    wp_safe_redirect($redirect);
    exit;
}