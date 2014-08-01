<?php
/**
 * ICS Import
 *
 * @package WordPress
 * @subpackage Importer
 */
 //ini_set('display_errors',1);
 //error_reporting(E_ALL);
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
        $new_events= $this->_add_vcalendar_events_to_db();
		if (!empty($new_events)){
		  printf( __( '<b>You have imported %d Events</b>', 'aio-ics-importer' ), count($new_events) );
		  foreach($new_events as $evnts){

                echo '<table><tr>';
                echo '<td>'.$evnts['title'].'</td>';
                echo '<td><a target="_blank" href="'.get_edit_post_link($evnts['eid']).'"> / Edit</a></td>';
                echo '</tr></table>';
		  }
		}else{
		  printf( __( '<b>No new Event have imported </b>', 'aio-ics-importer' ));
		}
            
            
	}
	/**
	 * add_vcalendar_events_to_db method
	 *
	 * Process vcalendar instance - add events to database
	 *
	 * @param vcalendar $v              Calendar to retrieve data from
	 * @param stdClass  $feed           Instance of feed (see Ai1ecIcs plugin)
	 * @param string    $comment_status WP comment status: 'open' or 'closed'
	 * @param int       $do_show_map    Map display status (DB boolean: 0 or 1)
	 *
	 * @return int Count of events added to database
	 */
	protected function _add_vcalendar_events_to_db() {
	   $source=file_get_contents($this->file);
       $v = $this->_registry->get('vcalendar');
       $v->parse( $source);

		$feed           = 'null';
		$comment_status = 'open';
		$do_show_map    = 1;
		$count = 0;
        
		$v->sort();
		// Reverse the sort order, so that RECURRENCE-IDs are listed before the
		// defining recurrence events, and therefore take precedence during
		// caching.
		$v->components = array_reverse( $v->components );

		// TODO: select only VEVENT components that occur after, say, 1 month ago.
		// Maybe use $v->selectComponents(), which takes into account recurrence

		// Fetch default timezone in case individual properties don't define it
		$timezone = $v->getProperty( 'X-WR-TIMEZONE' );
		$timezone = (string)$timezone[1];
        $new_events=array();
		// go over each event
		while ( $e = $v->getComponent( 'vevent' ) ) {
			// Event data array.
			$data = array();
			// =====================
			// = Start & end times =
			// =====================
			$start = $e->getProperty( 'dtstart', 1, true );
			$end   = $e->getProperty( 'dtend',   1, true );
			// For cases where a "VEVENT" calendar component
			// specifies a "DTSTART" property with a DATE value type but none
			// of "DTEND" nor "DURATION" property, the event duration is taken to
			// be one day.  For cases where a "VEVENT" calendar component
			// specifies a "DTSTART" property with a DATE-TIME value type but no
			// "DTEND" property, the event ends on the same calendar date and
			// time of day specified by the "DTSTART" property.
			if ( empty( $end ) )  {
				// #1 if duration is present, assign it to end time
				$end = $e->getProperty( 'duration', 1, true, true );
				if ( empty( $end ) ) {
					// #2 if only DATE value is set for start, set duration to 1 day
					if ( ! isset( $start['value']['hour'] ) ) {
						$end = array(
							'value' => array(
								'year'  => $start['value']['year'],
								'month' => $start['value']['month'],
								'day'   => $start['value']['day'] + 1,
								'hour'  => 0,
								'min'   => 0,
								'sec'   => 0,
							),
						);
						if ( isset( $start['value']['tz'] ) ) {
							$end['value']['tz'] = $start['value']['tz'];
						}
					} else {
						// #3 set end date to start time
						$end = $start;
					}
				}
			}

			$categories = $e->getProperty( "CATEGORIES", false, true );
			$imported_cat = array();
			// If the user chose to preserve taxonomies during import, add categories.
			if( $categories && $feed->keep_tags_categories ) {
				$imported_cat = $this->_add_categories_and_tags(
						$categories['value'],
						$imported_cat,
						false,
						true
				);
			}
            $imported_tags = array();
            /*
			$feed_categories = $feed->feed_category;
			if( ! empty( $feed_categories ) ) {
				$imported_cat = $this->_add_categories_and_tags(
						$feed_categories,
						$imported_cat,
						false,
						false
				);
			}
            
			$tags = $e->getProperty( "X-TAGS", false, true );


			
			// If the user chose to preserve taxonomies during import, add tags.
			if( $tags && $feed->keep_tags_categories ) {
				$imported_tags = $this->_add_categories_and_tags(
						$tags[1]['value'],
						$imported_tags,
						true,
						true
				);
			}
			$feed_tags = $feed->feed_tags;
			if( ! empty( $feed_tags ) ) {
				$imported_tags = $this->_add_categories_and_tags(
						$feed_tags,
						$imported_tags,
						true,
						true
				);
			}
            */
			// Event is all-day if no time components are defined
			$allday = $this->_is_timeless( $start['value'] ) &&
				$this->_is_timeless( $end['value'] );
			// Also check the proprietary MS all-day field.
			$ms_allday = $e->getProperty( 'X-MICROSOFT-CDO-ALLDAYEVENT' );
			if ( ! empty( $ms_allday ) && $ms_allday[1] == 'TRUE' ) {
				$allday = true;
			}

			$start = $this->_time_array_to_datetime( $start, $timezone );
			$end   = $this->_time_array_to_datetime( $end,   $timezone );

			if ( false === $start || false === $end ) {
				throw new Ai1ec_Parse_Exception(
					'Failed to parse one or more dates given timezone "' .
					var_export( $timezone, true ) . '"'
				);
				continue;
			}

			// If all-day, and start and end times are equal, then this event has
			// invalid end time (happens sometimes with poorly implemented iCalendar
			// exports, such as in The Event Calendar), so set end time to 1 day
			// after start time.
			if ( $allday && $start->format() === $end->format() ) {
				$end->adjust_day( +1 );
			}

			$data += compact( 'start', 'end', 'allday' );

			// =======================================
			// = Recurrence rules & recurrence dates =
			// =======================================
			if ( $rrule = $e->createRrule() ) {
				$rrule = explode( ':', $rrule );
				$rrule = trim( end( $rrule ) );
			}

			if ( $exrule = $e->createExrule() ) {
				$exrule = explode( ':', $exrule );
				$exrule = trim( end( $exrule ) );
			}

			if ( $rdate = $e->createRdate() ) {
				$rdate = explode( ':', $rdate );
				$rdate = trim( end( $rdate ) );
			}


			// ===================
			// = Exception dates =
			// ===================
			$exdate_array = array();
			if ( $exdates = $e->createExdate() ){
				// We may have two formats:
				// one exdate with many dates ot more EXDATE rules
				$exdates = explode( "EXDATE", $exdates );
				foreach ( $exdates as $exd ) {
					if ( empty( $exd ) ) {
						continue;
					}
					$exdate_array[] = trim( end( explode( ':', $exd ) ) );
				}
			}
			// This is the local string.
			$exdate_loc = implode( ',', $exdate_array );
			$gmt_exdates = array();
			// Now we convert the string to gmt. I must do it here
			// because EXDATE:date1,date2,date3 must be parsed
			if( ! empty( $exdate_loc ) ) {
				foreach ( explode( ',', $exdate_loc ) as $date ) {
					// If the date is > 8 char that's a datetime, we just want the
					// date part for the exclusion rules
					if ( strlen( $date ) > 8 ) {
						$date = substr( $date, 0, 8 );
					}
					$gmt_exdates[] = $this->_exception_dates_to( $date, true );
				}
			}
			$exdate = implode( ',', $gmt_exdates );

			// ========================
			// = Latitude & longitude =
			// ========================
			$latitude = $longitude = NULL;
			$geo_tag  = $e->getProperty( 'geo' );
			if ( is_array( $geo_tag ) ) {
				if (
				isset( $geo_tag['latitude'] ) &&
				isset( $geo_tag['longitude'] )
				) {
					$latitude  = (float)$geo_tag['latitude'];
					$longitude = (float)$geo_tag['longitude'];
				}
			} else if ( ! empty( $geo_tag ) && false !== strpos( $geo_tag, ';' ) ) {
				list( $latitude, $longitude ) = explode( ';', $geo_tag, 2 );
				$latitude  = (float)$latitude;
				$longitude = (float)$longitude;
			}
			unset( $geo_tag );
			if ( NULL !== $latitude ) {
				$data += compact( 'latitude', 'longitude' );
				// Check the input coordinates checkbox, otherwise lat/long data
				// is not present on the edit event page
				$data['show_coordinates'] = 1;
			}

			// ===================
			// = Venue & address =
			// ===================
			$address = $venue = '';
			$location = $e->getProperty( 'location' );
            $extra_address=array();
            
            if($location)
                $extra_address= explode(',',stripslashes($location));
                            
            //city
            $values=$e->getProperty('X-PROP',4, false);
            if($values[0]=='X-DOTCAL-EVENT-CITY' && trim($values[1])){
                $extra_address[]=$values[1];
            }
            //state
            $values=$e->getProperty('X-PROP',5, false);
            if($values[0]=='X-DOTCAL-EVENT-STATE' && $values[1]){
                $extra_address[]=$values[1];
            }      
            //zipcode      
            $values=$e->getProperty('X-PROP',7, false);
            $is_zip=false;
            if($values[0]=='X-DOTCAL-EVENT-POSTAL-CODE' && $values[1]){
                $is_zip=true;
                $extra_address[]=$values[1];
            }          
            //country      
            $values=$e->getProperty('X-PROP',6, false);
            if($values[0]=='X-DOTCAL-EVENT-COUNTRY' && $values[1]){
                if($is_zip)
                    $extra_address[]=array_pop($extra_address).' '.$values[1];
                else
                    $extra_address[]=$values[1];
            }     
               
             
            $location='';
            if(count($extra_address)){
               $extra_address = array_map('trim', $extra_address);
               $extra_address=array_unique($extra_address);
               $location=implode(', ',$extra_address);
            }          
			$matches = array();
			// This regexp matches a venue / address in the format
			// "venue @ address" or "venue - address".
			preg_match( '/\s*(.*\S)\s+[\-@]\s+(.*)\s*/', $location, $matches );
			// if there is no match, it's not a combined venue + address
			if ( empty( $matches ) ) {
				// if there is a comma, probably it's an address
				if ( false === strpos( $location, ',' ) ) {
					$venue = $location;
				} else {
					$address = $location;
				}
			} else {
				$venue = isset( $matches[1] ) ? $matches[1] : '';
				$address = isset( $matches[2] ) ? $matches[2] : '';
			}
			// =====================================================
			// = Set show map status based on presence of location =
			// =====================================================
			if (
				1 === $do_show_map &&
				NULL === $latitude &&
				empty( $address )
			) {
				$do_show_map = 0;
			}

			// ==================
			// = Cost & tickets =
			// ==================
			$cost       = $e->getProperty( 'X-COST' );
			$cost       = $cost ? $cost[1] : '';
			$ticket_url = $e->getProperty( 'X-TICKETS-URL' );
			$ticket_url = $ticket_url ? $ticket_url[1] : '';

			// ===============================
			// = Contact name, phone, e-mail =
			// ===============================
			$organizer = $e->getProperty( 'organizer' );
			if (
				'MAILTO:' === substr( $organizer, 0, 7 ) &&
				false === strpos( $organizer, '@' )
			) {
				$organizer = substr( $organizer, 7 );
			}
			$contact = $e->getProperty( 'contact' );
			$elements = explode( ';', $contact, 4 );
			foreach ( $elements as $el ) {
				$el = trim( $el );
				// Detect e-mail address.
				if ( false !== strpos( $el, '@' ) ) {
					$data['contact_email'] = $el;
				}
				// Detect URL.
				elseif ( false !== strpos( $el, '://' ) ) {
					$data['contact_url']   = $el;
				}
				// Detect phone number.
				elseif ( preg_match( '/\d/', $el ) ) {
					$data['contact_phone'] = $el;
				}
				// Default to name.
				else {
					$data['contact_name']  = $el;
				}
			}
			if ( ! isset( $data['contact_name'] ) || ! $data['contact_name'] ) {
				// If no contact name, default to organizer property.
				$data['contact_name']    = $organizer;
			}

			// Store yet-unsaved values to the $data array.
			$data += array(
				'recurrence_rules'  => $rrule,
				'exception_rules'   => $exrule,
				'recurrence_dates'  => $rdate,
				'exception_dates'   => $exdate,
				'venue'             => $venue,
				'address'           => $address,
				'cost'              => $cost,
				'ticket_url'        => $ticket_url,
				'show_map'          => $do_show_map,
				'ical_feed_url'     => (is_object($feed)?$feed->feed_url:get_bloginfo('url')),
				'ical_source_url'   => $e->getProperty( 'url' ),
				'ical_organizer'    => $organizer,
				'ical_contact'      => $contact,
				'ical_uid'          => $e->getProperty( 'uid' ),
				'categories'        => array_keys( $imported_cat ),
				'tags'              => array_keys( $imported_tags ),
				'feed'              => $feed,
				'post'              => array(
					'post_status'       => 'publish',
						'comment_status'    => $comment_status,
						'post_type'         => AI1EC_POST_TYPE,
						'post_author'       => 1,
						'post_title'        => $e->getProperty( 'summary' ),
						'post_content'      => stripslashes(
							str_replace(
								'\n',
								"\n",
								$e->getProperty( 'description' )
							)
						),
				),
			);

			// Create event object.
			$event = $this->_registry->get( 'model.event', $data );

			// TODO: when singular events change their times in an ICS feed from one
			// import to another, the matching_event_id is null, which is wrong. We
			// want to match that event that previously had a different time.
			// However, we also want the function to NOT return a matching event in
			// the case of recurring events, and different events with different
			// RECURRENCE-IDs... ponder how to solve this.. may require saving the
			// RECURRENCE-ID as another field in the database.
			$recurrence = $event->get( 'recurrence_rules' );
			$matching_event_id = $this->_registry->get( 'model.search' )
				->get_matching_event_id(
					$event->get( 'ical_uid' ),
					$event->get( 'ical_feed_url' ),
					$event->get( 'start' ),
					! empty( $recurrence )
				);
            //$new_events=array();
			if ( null === $matching_event_id ) {
				// =================================================
				// = Event was not found, so store it and the post =
				// =================================================
				
                $post_id=$event->save();
                $new_events[]=array('eid'=>$post_id,'title'=>$e->getProperty('summary'));
                
   	            $count++;
			} else {
				// ======================================================
				// = Event was found, let's store the new event details =
				// ======================================================

				// Update the post
				$post               = get_post( $matching_event_id );

				if ( null !== $post ) {
					$post->post_title   = $event->get( 'post' )->post_title;
					$post->post_content = $event->get( 'post' )->post_content;
					wp_update_post( $post );

					// Update the event
					$event->set( 'post_id', $matching_event_id );
					$event->set( 'post',    $post );
					$event->save( true );
				}

			}
		
		}
		return $new_events;
	}
    
    /**
	 * _is_timeless method
	 *
	 * Check if date-time specification has no (empty) time component.
	 *
	 * @param array $datetime Datetime array returned by iCalcreator
	 *
	 * @return bool Timelessness
	 */
	protected function _is_timeless( array $datetime ) {
		$timeless = true;
		foreach ( array( 'hour', 'min', 'sec' ) as $field ) {
			$timeless &= (
					isset( $datetime[$field] ) &&
					0 != $datetime[$field]
			)
			? false
			: true;
		}
		return $timeless;
	}
    /**
	 * time_array_to_timestamp function
	 *
	 * Converts time array to time string.
	 * Passed array: Array( 'year', 'month', 'day', ['hour', 'min', 'sec', ['tz']] )
	 * Return int: UNIX timestamp in GMT
	 *
	 * @param array  $time         iCalcreator time property array (*full* format expected)
	 * @param string $def_timezone Default time zone in case not defined in $time
	 *
	 * @return int UNIX timestamp
	 **/
	protected function _time_array_to_datetime( array $time, $def_timezone ) {
		$timezone = '';
		if ( isset( $time['params']['TZID'] ) ) {
			$timezone = $time['params']['TZID'];
		} elseif (
				isset( $time['value']['tz'] ) &&
				'Z' === $time['value']['tz']
		) {
			$timezone = 'UTC';
		}
		if ( empty( $timezone ) ) {
			$timezone = $def_timezone;
		}

		$date_time = $this->_registry->get( 'date.time' );

		if ( ! empty( $timezone ) ) {
			$parser   = $this->_registry->get( 'date.timezone' );
			$timezone = $parser->get_name( $timezone );
			if ( false === $timezone ) {
				return false;
			}
			$date_time->set_timezone( $timezone );
		}

		$date_time->set_date(
			$time['value']['year'],
			$time['value']['month'],
			$time['value']['day']
		);

		if ( isset( $time['value']['hour'] ) ) {
			$date_time->set_time(
				$time['value']['hour'],
				$time['value']['min'],
				$time['value']['sec']
			);
		}

		return $date_time;
	}       
    /**
	 * exception_dates_to function
	 *
	 * @return string
	 **/
	protected function _exception_dates_to( $exception_dates, $to_gmt = false ) {
		// trigger_error( "need to implement this", E_USER_ERROR );
	}  
	// dispatcher
	function icsaio_dispatch() {
		
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
