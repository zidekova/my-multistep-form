<?php
class MMF_Shortcode {
    public static function init() {
        add_shortcode('my_multistep_form', [__CLASS__, 'render']);
    }

    public static function render($atts = []) {
        // Načítaj CSS/JS len ak sa shortcode zobrazuje
        wp_enqueue_style('mmf-css');
        // enqueue fontawesome - always enqueue it to ensure icons display
        wp_enqueue_style('mmf-fa');
        wp_enqueue_script('mmf-js');

        // Možnosť poslať atribúty (napr. farba, theme, ...)
        $atts = shortcode_atts([
            'id' => 'mmf-form-1',
        ], $atts, 'my_multistep_form');

        ob_start();
        // Sprístupníme $atts v šablóne
        $mmf_atts = $atts;
        include __DIR__ . '/../templates/form.php';
        return ob_get_clean();
    }
}