<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

return apply_filters( 'customizer-gateway-setting-fields', array(
	'enabled' => array(
		'title' => __( 'Enable', 'woocommerce-pace-gateway' ),
		'label' => ' ',
		'type'  => 'checkbox',
		'description' => '',
		'default' => 'no'
	),
	'sandBox' => array(
		'title' => __( 'Enable Playground', 'woocommerce-pace-gateway' ),
		'label' => ' ',
		'type'  => 'checkbox',
		'description' => '',
		'default' => 'no'
	),
	'title' => array(
		'title'       => __( 'Title', 'woocommerce-pace-gateway' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-pace-gateway' ),
		'default'     => __( 'Pay with Pace', 'woocommerce-pace-gateway' ),
		'desc_tip'    => true,
	),
	'description' => array(
		'title'       => __( 'Description', 'woocommerce-pace-gateway' ),
		'type'        => 'textarea',
		'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-pace-gateway' ),
		'default'     => __( 'Pay with your order via Pace Gateway.', 'woocommerce-pace-gateway' ),
		'desc_tip'    => true,
	),
	'note' => array(
		'title'		  => __( 'API Credentials', 'woocommerce-pace-gateway' ),
		'type'		  => 'title',
		'description' => $this->display_admin_settings_payment_methods_note()
	),
	'client_id' => array(
		'title'		  => __( 'Client ID', 'woocommerce-pace-gateway' ),
		'type'		  => 'text',
		'description' => 'Get your Client ID from your Pace account. Invalid values will be rejected.',
		'default'	  => '',
		'desc_tip'	  => true
	),
	'client_secret' => array(
		'title'		  => __( 'Client Secret', 'woocommerce-pace-gateway' ),
		'type'		  => 'password',
		'description' => 'Get your Client Secret from your Pace account. Invalid values will be rejected.',
		'default'	  => '',
		'desc_tip'	  => true
	),
	'sandbox_client_id' => array(
		'title'		  => __( 'Playground Client ID', 'woocommerce-pace-gateway' ),
		'type'		  => 'text',
		'description' => 'The Playground Client ID. This use for testmode payment.',
		'default'	  => '',
		'desc_tip'	  => true
	),
	'sandbox_client_secret' => array(
		'title'		  => __( 'Playground Client Secret', 'woocommerce-pace-gateway' ),
		'type'		  => 'password',
		'description' => 'The Playground Client Secret. This use for testmode payment.',
		'default'	  => '',
		'desc_tip'	  => true
	),
	'customize_note' => array(
		'title'		  => __( 'Pace Widgets', 'woocommerce-pace-gateway' ),
		'type'		  => 'title',
	),

	'single_widget_section' => array(
		'title' => __( 'Product Widget', 'woocommerce-pace-gateway' ),
		'type'  => 'title'
	),
	//single
	'enable_single_widget' => array(
		'title' => __( 'Enable', 'woocommerce-pace-gateway' ),
		'label' => ' ',
		'type'  => 'checkbox',
		'description' => '',
		'default' => 'no'
	),
	'single_theme_config_color' => array(
		'title' => __( 'Theme color', 'woocommerce-pace-gateway' ),
		'label' => __( ' ', 'woocommerce-pace-gateway' ),
		'type'  => 'select',
		'default' => 'light',
		'options' => array(
			'dark'     => __( 'Dark', 'woocommerce' ),
			'light' => __( 'Light', 'woocommerce' ),
		)
	),
	'single_text_primary_color' => array(
		'title' => __( 'Text primary color', 'woocommerce-pace-gateway' ),
		'type'  => 'text',
		'default' => 'black'

	),
	'single_text_second_color' => array(
		'title' => __( 'Text secondary color', 'woocommerce-pace-gateway' ),
		'type'  => 'text',
		'default' => '#74705e'

	),
	'single_fontsize' => array(
		'title' => __( 'Font Size', 'woocommerce-pace-gateway' ),
		'type'  => 'text',
		'default' => '13'

	),
	'single_widget_style' => array(
		'title' => __( 'Widget style', 'woocommerce-pace-gateway' ),
		'type'  => 'text',
		'desc_tip' => true,
		'description' => 'Singular product - Widget styles customize. Separated each prop by commas.'
	),
	'space_multiple' => array(
		'type'		  => 'title',
	),
	'multiple_widget_section' => array(
		'title' => __( 'Product Catalog Widget', 'woocommerce-pace-gateway' ),
		'type'  => 'title'
	),
	//multiple
	'enable_multiple_widget' => array(
		'title' => __( 'Enable', 'woocommerce-pace-gateway' ),
		'label' => ' ',
		'type'  => 'checkbox',
		'description' => '',
		'default' => 'no'
	),
	'multiple_theme_config_color' => array(
		'title' => __( 'Theme color', 'woocommerce-pace-gateway' ),
		'label' => __( ' ', 'woocommerce-pace-gateway' ),
		'type'  => 'select',
		'default' => '',
			'options' => array(
				''      => __( 'Please select theme color', 'woocommerce' ),
				'dark'  => __( 'Dark', 'woocommerce' ),
				'light' => __( 'Light', 'woocommerce' ),
			),
		
	),
	'multiple_text_color' => array(
		'title' => __( 'Text primary color', 'woocommerce-pace-gateway' ),
		'type'  => 'text',
		'default' => '#74705e'

	),
	'multiple_fontsize' => array(
		'title' => __( 'Font Size	', 'woocommerce-pace-gateway' ),
		'type'  => 'text',
		'default'=> "13"

	),
	'multiple_widget_style' => array(
		'title' => __( 'Widget style', 'woocommerce-pace-gateway' ),
		'type'  => 'text',
		'desc_tip' => true,
		'description' => 'Multiple products - Widget styles customize. Separated each prop by commas.'
	),
	'space_multiple' => array(
		'type'		  => 'title',
	),
	'checkout_widget_section' => array(
		'title' => __( 'Checkout Widget', 'woocommerce-pace-gateway' ),
		'type'  => 'title'
	),
	//checkout
	'enable_checkout_widget' => array(
		'title' => __( 'Enable', 'woocommerce-pace-gateway' ),
		'label' => ' ',
		'type'  => 'checkbox',
		'description' => '',
		'default' => 'no'
	),
	'checkout_text_primary_color' => array(
		'title' => __( 'Text primary color', 'woocommerce-pace-gateway' ),
		'type'  => 'text',

	),
	'checkout_text_second_color' => array(
		'title' => __( 'Text secondary color', 'woocommerce-pace-gateway' ),
		'type'  => 'text',

	),
	'checkout_text_timeline_color' => array(
		'title' => __( 'Text timeline Color', 'woocommerce-pace-gateway' ),
		'type'  => 'text',

	),
	'checkout_background_color' => array(
		'title' => __( 'Background Color', 'woocommerce-pace-gateway' ),
		'type'  => 'text',

	),
	'checkout_foreground_color' => array(
		'title' => __( 'Foreground Color', 'woocommerce-pace-gateway' ),
		'type'  => 'text',
	),
	'checkout_fontsize' => array(
		'title' => __( 'Fontsize', 'woocommerce-pace-gateway' ),
		'type'  => 'text',
	),
	'enable_fallback' => array(
		'title' => __( 'Enable Fallback Widget', 'woocommerce-pace-gateway' ),
		'label' => __( ' ', 'woocommerce-pace-gateway' ),
		'type'  => 'checkbox',
		'description' => '',
		'default' => 'no'
	),
) );