<?php
/* 
Plugin Name: My Multistep Form
Description: 7-krokový multistep formulár cez shortcode [my_multistep_form]
Version: 0.1.4
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
    // Spustíme session pre ukladanie dát
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    wp_register_style('mmf-css', MMF_PLUGIN_URL . 'assets/css/multistep-form.css', [], '0.1.4');
    wp_register_style('mmf-fa', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', [], '6.5.1');
    wp_register_script(
        'mmf-js',
        MMF_PLUGIN_URL . 'assets/js/multistep-form.js',
        [],
        '0.1.5',
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
    
    // Preposielanie session dát do JavaScriptu
    $form_data = [];
    $error_message = '';
    
    if (isset($_SESSION['mmf_form_data'])) {
        $form_data = $_SESSION['mmf_form_data'];
        unset($_SESSION['mmf_form_data']);
    }
    
    if (isset($_SESSION['mmf_error_message'])) {
        $error_message = $_SESSION['mmf_error_message'];
        unset($_SESSION['mmf_error_message']);
    }
    
    wp_localize_script('mmf-js', 'MMF_FORM_DATA', $form_data);
    wp_localize_script('mmf-js', 'MMF_ERROR', $error_message);
});

// Force sender address/name for plugin emails
add_filter('wp_mail_from', function($from) {
    return 'info@djandrejrybar.sk';
});
add_filter('wp_mail_from_name', function($name) {
    return 'DJ Andrej Rybar';
});

// Handle form submission and send email to site admin (or DJ)
add_action('admin_post_nopriv_mmf_submit', 'mmf_handle_submit');
add_action('admin_post_mmf_submit', 'mmf_handle_submit');

function mmf_get_redirect_base_url() {
    $redirect = '';

    if (isset($_POST['mmf_return_url'])) {
        $posted_return = esc_url_raw(wp_unslash($_POST['mmf_return_url']));
        if (!empty($posted_return)) {
            $redirect = wp_validate_redirect($posted_return, '');
        }
    }

    if (empty($redirect)) {
        $referer = wp_get_referer();
        if ($referer) {
            $redirect = $referer;
        }
    }

    if (empty($redirect)) {
        $redirect = home_url('/');
    }

    return $redirect;
}

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
    
    // Server-side validation for required fields
    $errors = [];
    
    if (empty($client_name)) {
        $errors[] = 'Meno je povinné.';
    }
    
    if (empty($client_email) || !is_email($client_email)) {
        $errors[] = 'Platný email je povinný.';
    }
    
    if (empty($client_phone)) {
        $errors[] = 'Telefónne číslo je povinné.';
    } else {
        $phone_clean = preg_replace('/[^0-9]/', '', $client_phone);
        if (strlen($phone_clean) < 9) {
            $errors[] = 'Telefónne číslo musí obsahovať aspoň 9 číslic.';
        }
    }
    
    if (empty($event_date)) {
        $errors[] = 'Dátum akcie je povinný.';
    } else {
        $date_obj = DateTime::createFromFormat('Y-m-d', $event_date);
        if (!$date_obj) {
            $errors[] = 'Neplatný formát dátumu.';
        } else {
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            if ($date_obj < $today) {
                $errors[] = 'Dátum akcie nemôže byť v minulosti.';
            }
        }
    }
    
    // If there are validation errors, redirect back with error message
    if (!empty($errors)) {
        $error_message = implode(' ', $errors);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Uložíme všetky odoslané dáta a chybovú správu
        $_SESSION['mmf_form_data'] = [
            'package' => $package,
            'event_type' => $event_type,
            'duration' => $duration,
            'region' => $region,
            'extensions' => $extensions,
            'final_price' => $final_price,
            'event_date' => $event_date,
            'contact_message' => $client_message,
            'contact_name' => $client_name,
            'contact_email' => $client_email,
            'contact_phone' => $client_phone,
            'current_step' => 7
        ];
        
        $_SESSION['mmf_error_message'] = $error_message;
        
        // Presmerujeme bez error parametra v URL
        $redirect = mmf_get_redirect_base_url();
        wp_safe_redirect($redirect);
        exit;
    }

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

    $included = [
        'wow-efekt' => ['vecerny-wow-moment']
    ];

    $ext_lines = [];
    foreach ($extensions as $ext) {
        $label = $extensions_map[$ext] ?? $ext;
        $note = (isset($included[$package]) && in_array($ext, $included[$package])) ? ' (zahrnuté v balíku)' : '';
        $ext_lines[] = $label . $note;
    }
    if (empty($ext_lines)) $ext_lines[] = 'Žiadne';

    $formatted_date = 'Nedefinovaný';
    if ($event_date) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $event_date);
        if ($date_obj) {
            $formatted_date = $date_obj->format('d.m.Y');
        } else {
            $formatted_date = $event_date;
        }
    }

    // Compose email
    $to = get_option('admin_email');
    $subject = sprintf('Predbežná rezervácia z webstránky');

    $body = "Nová predbežná rezervácia pre " . $client_email . ":\n\n";
    $body .= "Balík: " . ($package_map[$package] ?? $package) . "\n";
    $body .= "Typ akcie: " . ($event_map[$event_type] ?? $event_type) . "\n";
    $body .= "Dĺžka programu: " . ($duration_map[$duration] ?? $duration) . "\n";
    $body .= "Lokalita: " . ($region ? $region : 'Nedefinované') . "\n";
    $body .= "Rozšírenia: \n - " . implode("\n - ", $ext_lines) . "\n";
    $body .= "Finálna cena: € " . ($final_price !== '' ? $final_price : 'Nedefinovaná') . "\n";
    $body .= "Dátum akcie: " . $formatted_date . "\n\n";
    $body .= "Kontaktné údaje:\n";
    $body .= "Meno: " . ($client_name ? $client_name : '-') . "\n";
    $body .= "Email: " . ($client_email ? $client_email : '-') . "\n";
    $body .= "Telefón: " . ($client_phone ? $client_phone : '-') . "\n\n";
    $body .= "Správa od klienta:\n" . ($client_message ? $client_message : '-') . "\n";

    $headers = [];
    if ($client_email) {
        $headers[] = 'Reply-To: ' . $client_name . ' <' . $client_email . '>';
    }

    wp_mail($to, $subject, $body, $headers);

    // Vymažeme session data po úspešnom odoslaní
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['mmf_form_data']);
    unset($_SESSION['mmf_error_message']);

    // Redirect back with success flag
    $redirect_base = remove_query_arg('mmf_sent', mmf_get_redirect_base_url());
    $redirect = add_query_arg('mmf_sent', '1', $redirect_base);
    wp_safe_redirect($redirect);
    exit;
}