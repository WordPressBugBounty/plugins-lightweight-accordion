<?php
/**
	 * Plugin Name: Lightweight Accordion
	 * Plugin URI: https://smartwp.com/lightweight-accordion
	 * Description: Extremely simple accordion for adding collapse elements to pages without affecting page load time. Works for Classic Editor via shortcode and Gutenberg via Block.
	 * Version: 1.6.0
	 * Text Domain: lightweight-accordion
	 * Author: Andy Feliciotti
	 * Author URI: https://smartwp.com
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'LIGHTWEIGHT_ACCORDION_VERSION', '1.6.0' );
define( 'LIGHTWEIGHT_ACCORDION_CSS_VERSION', '1.6.0' );

// Enqueue CSS when in use
add_filter( 'the_content', 'enqueue_lightweight_accordion_styles' );
add_action( 'enqueue_block_editor_assets', 'enqueue_lightweight_accordion_styles' );
function enqueue_lightweight_accordion_styles($content = ""){
	global $post;
	$include_frontend_stylesheet = apply_filters( 'lightweight_accordion_include_frontend_stylesheet', true);
	$always_include_frontend_stylesheet = apply_filters( 'lightweight_accordion_always_include_frontend_stylesheet', false);
	$include_admin_stylesheet = apply_filters( 'lightweight_accordion_include_admin_stylesheet', true);

	$plugin_url = plugin_dir_url( __FILE__ );

	if( $include_frontend_stylesheet && ( $always_include_frontend_stylesheet || ( isset($post->post_content) && has_shortcode( $post->post_content, 'lightweight-accordion') || has_block('lightweight-accordion/lightweight-accordion') || has_block('lightweight-accordion/accordion-group') ) ) ){
		wp_enqueue_style('lightweight-accordion', $plugin_url . 'css/min/lightweight-accordion.min.css', array(), LIGHTWEIGHT_ACCORDION_CSS_VERSION);
	}

	if( $include_admin_stylesheet && ( is_admin() ) ){
		wp_enqueue_style('lightweight-accordion-admin-styles', $plugin_url . 'css/min/editor-styles.min.css', array(), LIGHTWEIGHT_ACCORDION_CSS_VERSION);
	}

	return $content;
}

// Shortcode function to display lightweight accordion
function lightweight_accordion_shortcode( $atts, $content = null ) {
	$atts = shortcode_atts( array(
		'anchor' => null,
		'title' => null,
		'title_tag' => 'span',
		'accordion_open' => false,
		'bordered' => false,
		'title_background_color' => false,
		'title_text_color' => false,
		'schema' => false,
		'class' => false,
		'autop' => true,
		'group' => false
	), $atts, 'lightweight-accordion' );

	return render_lightweight_accordion( $atts, $content, false );
}
add_shortcode('lightweight-accordion', 'lightweight_accordion_shortcode');
add_shortcode('lightweight-accordion-nested', 'lightweight_accordion_shortcode');

// Block handler for Gutenberg
function lightweight_accordion_block_handler( $atts, $content, $block = null ) {
	// Check if we're inside an accordion group and get the group name from context
	if ( $block && isset( $block->context['lightweight-accordion/groupName'] ) ) {
		$atts['group'] = $block->context['lightweight-accordion/groupName'];
	}
	return render_lightweight_accordion( $atts, $content, true );
}

// Accordion Group block handler - just renders inner content
function lightweight_accordion_group_block_handler( $atts, $content ) {
	return $content;
}

// Render the actual accordion
function render_lightweight_accordion( $options, $content, $isBlock ) {
	$output = '';

	// Merge with defaults to prevent undefined key warnings
	$defaults = array(
		'anchor'                 => null,
		'title'                  => null,
		'title_tag'              => 'span',
		'accordion_open'         => false,
		'bordered'               => false,
		'title_background_color' => false,
		'title_text_color'       => false,
		'schema'                 => false,
		'class'                  => false,
		'className'              => false,
		'autop'                  => true,
		'group'                  => false,
	);
	$options = wp_parse_args( $options, $defaults );

	$process_shortcodes = apply_filters( 'lightweight_accordion_process_shortcodes', true );

	if ( $process_shortcodes ) {
		$content = do_shortcode( $content );
	}

	if ( ! $isBlock && filter_var( $options['autop'], FILTER_VALIDATE_BOOLEAN ) ) {
		$content = wpautop( preg_replace( '#<p>\s*+(<br\s*/*>)?\s*</p>#i', '', force_balance_tags( $content ) ) );
	}

	$anchor = '';
	if ( $options['anchor'] ) {
		$anchor = ' id="' . esc_attr( $options['anchor'] ) . '"';
	}

	$open = '';
	if ( $options['accordion_open'] ) {
		$open = ' open';
	}

	$group = '';
	if ( $options['group'] ) {
		$group = ' name="' . esc_attr( $options['group'] ) . '"';
	}

	$classes = array( 'lightweight-accordion' );
	if ( $options['bordered'] ) {
		$classes[] = 'bordered';
	}
	if ( $options['class'] ) {
		// Sanitize each custom class name
		$custom_classes = array_map( 'sanitize_html_class', explode( ' ', $options['class'] ) );
		$classes = array_merge( $classes, array_filter( $custom_classes ) );
	}
	if ( $options['className'] ) {
		// Sanitize each custom class name (Gutenberg className)
		$custom_classes = array_map( 'sanitize_html_class', explode( ' ', $options['className'] ) );
		$classes = array_merge( $classes, array_filter( $custom_classes ) );
	}

	$bodyClasses = array( 'lightweight-accordion-body' );

	$titleStyles = $bodyStyles = array();
	if ( $options['title_text_color'] ) {
		$titleStyles[] = 'color:' . esc_attr( $options['title_text_color'] );
		$classes[]     = 'has-text-color';
	}
	if ( $options['title_background_color'] ) {
		$titleStyles[] = 'background:' . esc_attr( $options['title_background_color'] );
		$bodyStyles[]  = 'border-color:' . esc_attr( $options['title_background_color'] );
		$classes[]     = 'has-background';
	}
	if(!empty($titleStyles)){
		$titleStyles = ' style="'.implode(';',$titleStyles).';"';
	}else{
		$titleStyles = '';
	}
	if(!empty($bodyStyles)){
		$bodyStyles = ' style="'.implode(';',$bodyStyles).';"';
	}else{
		$bodyStyles = '';
	}

	$propBox = $propTitle = $propContent = null;
	if(isset($options['schema']) && $options['schema'] == 'faq'){
		global $lightweight_accordion_schema;
		if ( !isset($lightweight_accordion_schema) || !is_array($lightweight_accordion_schema) ) {
			$lightweight_accordion_schema = array(
				'@context' => "https://schema.org",
				'@type' => 'FAQPage',
				'mainEntity' => array()
			);
		}
		// Strip HTML for JSON-LD schema (should be plain text)
		$schema_title = wp_strip_all_tags( $options['title'] );
		$schema_content = wp_strip_all_tags( $content );
		
		$lightweight_accordion_schema['mainEntity'][] = array(
			'@type' => 'Question',
			'name' => $schema_title,
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text' => $schema_content
			)
		);

		$output_microdata = apply_filters( 'lightweight_accordion_output_microdata', false);
		if($output_microdata){
			$propBox = ' itemscope itemprop="mainEntity" itemtype="https://schema.org/Question"';
			$propTitle = ' itemprop="name"';
			$propContent = ' itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer"';
			$content = ' <span itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer"><span itemprop="text">'.$content.'</span></span>';
			$lightweight_accordion_schema = null;
		}
	}

	$title = isset( $options['title'] ) ? wp_kses_post( $options['title'] ) : '';
	
	// Whitelist allowed title tags for security
	$allowed_title_tags = array( 'span', 'div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
	$title_tag = isset( $options['title_tag'] ) && in_array( $options['title_tag'], $allowed_title_tags, true ) 
		? $options['title_tag'] 
		: 'span';

	if( $title && isset($content) ){
		$output .= '<div class="' . esc_attr( implode(' ', $classes) ) . '"' . $anchor . '><details' . $propBox . $group . $open . '><summary class="lightweight-accordion-title"' . $titleStyles . '><' . esc_attr( $title_tag ) . '' . $propTitle . '>' . $title . '</' . esc_attr( $title_tag ) . '></summary><div class="' . esc_attr( implode(' ', $bodyClasses) ) . '"' . $bodyStyles . '>';
		$output .= $content;
		$output .= '</div></details></div>';
	}

	return $output;
}

// Output JSON-LD schema in the footer
add_action( 'wp_footer', 'lightweight_accordion_output_schema' );
function lightweight_accordion_output_schema() {
	global $lightweight_accordion_schema;

	if (is_array($lightweight_accordion_schema)) {
		$output = '<script type="application/ld+json" class="lightweight-accordion-faq-json">';
		$output .= wp_json_encode($lightweight_accordion_schema);
		$output .= '</script>';
		echo $output;
	}
}

// Register Gutenberg block
add_action( 'init', 'lightweight_accordion_register_block' );
function lightweight_accordion_register_block() {
	// Skip block registration if Gutenberg is not enabled.
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	$dir = dirname( __FILE__ );

	$index_js = 'build/index.js';
	wp_register_script(
		'lightweight-accordion',
		plugins_url( $index_js, __FILE__ ),
		array(
			'wp-block-editor',
			'wp-blocks',
			'wp-i18n',
			'wp-element',
			'wp-components',
			'wp-data'
		),
		filemtime( "$dir/$index_js" )
	);

	// Register the accordion group block
	register_block_type( 'lightweight-accordion/accordion-group', array(
		'editor_script'    => 'lightweight-accordion',
		'render_callback'  => 'lightweight_accordion_group_block_handler',
		'provides_context' => array(
			'lightweight-accordion/groupName' => 'groupName',
		),
		'attributes'       => array(
			'groupName' => array(
				'type'    => 'string',
				'default' => '',
			),
		),
	) );

	// Register the accordion block (works standalone or inside groups)
	register_block_type( 'lightweight-accordion/lightweight-accordion', array(
		'editor_script'   => 'lightweight-accordion',
		'render_callback' => 'lightweight_accordion_block_handler',
		'uses_context'    => array( 'lightweight-accordion/groupName' ),
	) );
}
