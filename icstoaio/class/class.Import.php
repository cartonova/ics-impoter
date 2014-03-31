<?php
/**
 * ICS Import
 *
 * @package WordPress
 * @subpackage Importer
 */
if ( class_exists( 'WP_Importer' ) ) {
class ICS_AIO_Importer extends WP_Importer {
	protected $_registry;
    
 	function set_registry(Ai1ec_Registry_Object $registry){
 	  $this->_registry = $registry;
 	}
    // User interface wrapper start
	function icsaio_header() {
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>'.__('Import ICS', 'aio-ics-importer').'</h2>';
	}

	// User interface wrapper end
	function icsaio_footer() {
		echo '</div>';
	}
	
	// Step 1
	function icsaio_message() {
		echo '<p>'.__( 'Choose a ICS (.ICS) file to upload, then click Upload file and import.', 'aio-ics-importer' ).'</p>';
		wp_import_upload_form( add_query_arg('step', 1) );
	}

	// Step 2
	function icsaio_import() {
		$file = wp_import_handle_upload();

		if ( isset( $file['error'] ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'aio-ics-importer' ) . '</strong><br />';
			echo esc_html( $file['error'] ) . '</p>';
			return false;
		} else if ( ! file_exists( $file['file'] ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'aio-ics-importer' ) . '</strong><br />';
			printf( __( 'The export file could not be found at <code>%s</code>. It is likely that this was caused by a permissions problem.', 'aio-ics-importer' ), esc_html( $file['file'] ) );
			echo '</p>';
			return false;
		}
		
		$this->id = (int) $file['id'];
		$this->file = get_attached_file($this->id);
		$result = $this->process_posts();
		if ( is_wp_error( $result ) )
			return $result;
	}
	
	/**
	* Insert post and postmeta using wp_post_helper.
	*
	* More information: https://gist.github.com/4084471
	*
	* @param array $post
	* @param array $meta
	* @param array $terms
	* @param string $thumbnail The uri or path of thumbnail image.
	* @param bool $is_update
	* @return int|false Saved post id. If failed, return false.
	*/
	function save_post($post,$meta,$terms,$thumbnail,$is_update) {
		$ph = new wp_post_helper($post);
		
		foreach ($meta as $key => $value) {
			$is_acf = 0;
			if (function_exists('get_field_object')) {
				if (strpos($key, 'field_') === 0) {
					$fobj = get_field_object($key);
					if (is_array($fobj) && isset($fobj['key']) && $fobj['key'] == $key) {
						$ph->add_field($key,$value);
						$is_acf = 1;
					}
				}
			}
			if (!$is_acf)
				$ph->add_meta($key,$value,true);
		}

		foreach ($terms as $key => $value) {
			$ph->add_terms($key, $value);
		}
		
		if ($thumbnail) $ph->add_media($thumbnail,'','','',true);
		
		if ($is_update)
			$result = $ph->update();
		else
			$result = $ph->insert();
		
		unset($ph);
		
		return $result;
	}
    // process parse csv ind insert posts
	function process_posts() {
      $ical   = new ICal($this->file);
      $parse_data = $ical->events();
      $error = new WP_Error();
      
      if(count($parse_data)){
        foreach($parse_data as $ics_item){
            // Build post array from submitted data.
			$post_status='publish';
            $post = array(
				'post_type'    => AI1EC_POST_TYPE,
				'post_author'  => get_current_user_id(),
				'post_title'   => addslashes($ics_item['SUMMARY']),
				'post_content' => esc_js($ics_item['DESCRIPTION']),
				'post_status'  => $post_status,
			);
            
            $address=($ics_item['LOCATION']?$ics_item['LOCATION']:'').($ics_item['X-DOTCAL-EVENT-CITY']?$ics_item['X-DOTCAL-EVENT-CITY']:'').($ics_item['X-DOTCAL-EVENT-STATE']?$ics_item['X-DOTCAL-EVENT-STATE']:'').($ics_item['X-DOTCAL-EVENT-POSTAL-CODE']?$ics_item['X-DOTCAL-EVENT-POSTAL-CODE']:'');//X-DOTCAL-EVENT-POSTAL-CODE

			// Copy posted event data to new empty event array.
			$event                  = array();
            $event['post']          = $post;
            $event['categories']    = array();        
			$event['tags']          = array();             
			$event['allday']        =  0;
			$event['instant_event'] = 0;
			$event['start']         = trim($ics_item['DTSTART'])? strtotime($ics_item['DTSTART']): '';
			$event['address']       = $address;
		//	$event['show_map']      = isset( $_POST['ai1ec_google_map'] )    ? (bool) $_POST['ai1ec_google_map']          : 0;

			// Set end date
			if( $event['instant_event'] ) {
				$event['end'] = $event['start'] + 1800;
			} else {
				$event['end'] = trim( $ics_item['DTEND'] ) ? strtotime($ics_item['DTEND']) : '';
			}
			$entity = $this->_registry->get( 'model.event', $event );
			$entity->save();
      }
      }
	}   
	// dispatcher
	function dispatch() {
		
        $this->icsaio_header();
		
		if (empty ($_GET['step']))
			$step = 0;
		else
			$step = (int) $_GET['step'];

		switch ($step) {
			case 0 :
				$this->icsaio_message();
				break;
			case 1 :
				check_admin_referer('import-upload');
				set_time_limit(0);

                //$event->save();
                   
				$result = $this->icsaio_import();
				if ( is_wp_error( $result ) )
					echo $result->get_error_message();
				break;
		}
		
		$this->icsaio_footer();
	}
	
}
} // class_exists( 'WP_Importer' )
