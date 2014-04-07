<?php
/*
Plugin Name: ICS Importer
Description: ICS Importer
Version: 1
*/
// Initialize constants
define( 'ICSAIO_PLUGIN_NAME', 'icstoaio' );
define( 'ICSAIO_PATH',        dirname( __FILE__ ) );
define( 'ICSAIO_VERSION',     '1.0.1' );
define( 'ICSAIO_URL',         plugins_url( '', __FILE__ ) );
define( 'ICSAIO_FILE',        __FILE__ );

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';
if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}
// Load Class
require ICSAIO_PATH. '/class/class.iCalReader.php';
require ICSAIO_PATH. '/class/class.Import.php';

//Extends Ai1ec_Base class from AIO
Class IcsAio extends Ai1ec_Base{
	public function __construct( Ai1ec_Registry_Object $registry ) {
		$ICS_AIO_Importer=new ICS_AIO_Importer();
        parent::__construct( $registry );
        $ics_aio_importer = new ICS_AIO_Importer();
        $ics_aio_importer->set_registry($registry);
        register_importer('ics', __('ICS', 'aio-ics-importer'), __('Import event from ics file', 'aio-ics-importer'), array ($ics_aio_importer, 'dispatch'));
	}
}

function icsaio_init( Ai1ec_Registry_Object $registry ) {
	$registry->extension_acknowledge( ICSAIO_PLUGIN_NAME, ICSAIO_PATH );
	load_plugin_textdomain(
		ICSAIO_PLUGIN_NAME,
		false,
		basename( ICSAIO_PATH )
	);
	$IcsAio = new IcsAio($registry);
}
add_action( 'ai1ec_loaded', 'icsaio_init' );
