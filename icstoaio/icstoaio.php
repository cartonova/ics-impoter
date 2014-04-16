<?php
/*
Plugin Name: ICS Importer
Description: ICS Importer
Version: 1.0.1
*/
// Initialize constants
define( 'ICSAIO_PLUGIN_NAME', 'ICS Importer' );
define( 'ICSAIO_PATH',		  dirname( __FILE__ ) );
define( 'ICSAIO_VERSION',	  '1.0.1' );
define( 'ICSAIO_URL',		  plugins_url( '', __FILE__ ) );
define( 'ICSAIO_FILE',		  __FILE__ );

// Load Class
require ICSAIO_PATH. '/class/class.Import.php';
if ( class_exists( 'Ai1ec_Base' ) ) {
	function icsaio_init( Ai1ec_Registry_Object $registry ) {
		$registry->extension_acknowledge( ICSAIO_PLUGIN_NAME, ICSAIO_PATH );
		load_plugin_textdomain(
			ICSAIO_PLUGIN_NAME,
			false,
			basename( ICSAIO_PATH )
		);
		$ics_aio_importer = new ICS_AIO_Importer();
		$ics_aio_importer->set_registry( $registry );
		register_importer(
			'ics',
			__( 'ICS', 'aio-ics-importer' ),
			__( 'Import event from ics file', 'aio-ics-importer' ),
			array( $ics_aio_importer, 'icsaio_dispatch' )
		);
	}
	add_action( 'ai1ec_loaded', 'icsaio_init' );

} else {
	function icsaio_requires_AIO() {
		global $wp_version;
		$plugin = plugin_basename( __FILE__ );
		$plugin_data = get_plugin_data( __FILE__, false );
		if ( is_plugin_active( $plugin ) ) {
			deactivate_plugins( $plugin );
			wp_die( "<strong>".$plugin_data['Name']."</strong> requires <strong>All-in-One Event Calendar 2.0 </strong> or higher, and has been deactivated! Please install and try again.<br /><br />Back to the WordPress <a href='".get_admin_url(null, 'plugins.php')."'>Plugins page</a>." );
		}
	}
	add_action( 'admin_init', 'icsaio_requires_AIO' );
}