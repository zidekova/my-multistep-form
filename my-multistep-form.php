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