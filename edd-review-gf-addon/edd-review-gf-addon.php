<?php
/*
Plugin Name: EDD Reviews GF Addon
Plugin URI: https://tychesoftwares.com/
Description: Adding GF submission to EDD Reviews
Author: Tyche Softwares
Author URI: http://tychesoftwares.com/
Text Domain: edd-gf
Domain Path: /languages/
Version: 1.0
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * EDD_GF_Integration Class
 *
 * @package	EDD_GF_Integration
 * @since	1.0
 * @version	1.0
 * @author 	Tyche Softwares
 */
class EDD_GF_Integration {

	/**
	 * Constructor Function
	 *
	 * @since 1.0
	 * 
	 */
	public function __construct() {
		
		add_action( 'gform_after_submission', 	array( $this, 'set_post_content' ), 10, 2 );
		add_action( 'init', 					array( $this, 'dxt_eddreview_script' ) );
		add_action( 'admin_enqueue_scripts',  	array( $this, 'tyche_edd_review_enqueue_scripts_js' ) );
		add_action( 'edd_update_review'    ,  	array( $this, 'tyche_update_review_data' ) );
	}

	/**
	 * Creating comment for EDD Review when submitting form.
	 *
	 * @since 1.0
	 * @return void
	 */

	public function set_post_content( $entry, $form ) {		
	 	
	    $comment_author_ip = $entry['ip']; // IP of the user's machine
		$comment_author_ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', $comment_author_ip );

		$review_content = $entry['6']; // Body of Review
		$comment_type   = 'edd_review'; // Comment Type
		$comment_agent  = $entry['user_agent']; // User Agent

		
		/**
		 * Getting Download ID for the plugin selected when submitting the review.
		 */

		$comment_post_ID = '';

		switch ( $entry['4'] ) {
			case 'bkap':
				$comment_post_ID = 22; // Booking & Appointment Plugin for WooCommerce  
				break;
			case 'ac':
				$comment_post_ID = 20; // Abandoned Cart Pro
				break;
			case 'orddd':
				$comment_post_ID = 16; // Order Delivery Date Pro
				break;
			case 'pdd':
				$comment_post_ID = 238877; // Product Delivery Date Pro
				break;
			case 'dw':
				$comment_post_ID = 286317; // Deposits for WooCommerce
				break;
		}

		/**
		 * Getting user infomration.
		 * If the user is logged in user then its data will be fetched from system 
		 * else only those data will be assieged which are subbmitted by reviewer.
		 */

		$user_id = '';
		
		if( ( isset( $entry['created_by'] ) && $entry['created_by'] != "" ) || email_exists( $entry['2'] ) ){
			
			if ( isset( $entry['created_by'] ) && $entry['created_by'] != "" ) {
				$user_id 	= $entry['created_by'];
			}else{
				$user_id 	= email_exists( $entry['2'] );
			}
			
			$user = get_user_by( 'ID', $user_id );

			if ( empty( $user->display_name ) ) {
				$user->display_name = $user->user_login;
			}

			$review_author       = wp_slash( $user->display_name );
			$review_author_email = wp_slash( $user->user_email );
			$review_author_url   = wp_slash( $user->user_url );

		}else{
			$review_author       = isset( $entry['1'] )  ? trim( $entry['1']  ) : null;
			$review_author_email = isset( $entry['2'] ) ? trim( $entry['2'] ) : null;
			$review_author_url   = "";
			
			$review_author       = wp_slash( sanitize_text_field( wp_filter_nohtml_kses( esc_html( $review_author ) ) ) );
			$review_author_email = wp_slash( sanitize_text_field( wp_filter_nohtml_kses( esc_html( $review_author_email ) ) ) );
		}

		/**
		 * Getting rating infromation.
		 */

		$rating = 0;

		if ( isset( $entry['10'] ) && $entry['10'] != "" ){

			switch ( $entry['10'] ) {
				case 'poor':
					$rating = 1;
			 		$review_title = __( "Poor Plugin", "edd-gf" );
					break;
				case 'average':
					$rating = 2;
			 		$review_title = __( "Average Plugin", "edd-gf" );
					break;
				case 'good':
					$rating = 3;
			 		$review_title = __( "Good Plugin", "edd-gf" );
					break;
				case 'very_good':
					$rating = 4;
			 		$review_title = __( "Very Good Plugin", "edd-gf" );
					break;
				case 'excellent':
					$rating = 5;
			 		$review_title = __( "Excellent Plugin", "edd-gf" );
					break;
			}
		}

		/**
		 * Image url of the reviewer.
		 */

		$img_url = '';
		if ( isset( $entry['6'] ) && $entry['6'] != "" ){
			$img_url = $entry['6'];
		}

		/**
		 * Preparing arguments which is to be passed to create a comment(review).
		 */

		$args = apply_filters( 'edd_reviews_insert_review_args', array(
			'comment_post_ID'      => $comment_post_ID,
			'comment_author'       => $review_author,
			'comment_author_email' => $review_author_email,
			'comment_author_url'   => $review_author_url,
			'comment_content'      => $review_content,
			'comment_type'         => $comment_type,
			'comment_parent'       => '',
			'comment_author_IP'    => $comment_author_ip,
			'comment_agent'        => isset( $comment_agent ) ? substr( $comment_agent, 0, 254 ) : '',
			'user_id'              => $user_id,
			'comment_date'         => current_time( 'mysql' ),
			'comment_date_gmt'     => current_time( 'mysql', 1 ),
			'comment_approved'     => 1
		) );

		$comment_allowed 	= wp_allow_comment( $args );
		$args 				= apply_filters( 'preprocess_comment', $args );
		$review_id 			= wp_insert_comment( wp_filter_comment( $args ) );

		/**
		 * Comment meta to be inserted when review (comment) is created.
		 */

		add_comment_meta( $review_id, 'edd_rating', $rating );
		add_comment_meta( $review_id, 'edd_review_title', $review_title );
		add_comment_meta( $review_id, 'edd_review_approved', $comment_allowed );

		/**
		 * Additional Comment meta to store image url and category related information
		 */

		add_comment_meta( $review_id, 'edd_review_source', 546 );
		add_comment_meta( $review_id, 'edd_review_categories', '' );
		add_comment_meta( $review_id, 'edd_review_generic', '' );
		add_comment_meta( $review_id, 'edd_review_img_url', $img_url );

		/**
		 * Add review metadata to the $args so it can be passed to the notification email
		 */

		$args['id']              = $review_id;
		$args['rating' ]         = $rating;
		$args['review_title']    = $review_title;
		$args['review_approved'] = $comment_allowed;		

		update_post_meta( $comment_post_ID, 'edd_reviews_average_rating', EDD_Reviews::average_rating( false, $comment_post_ID ) );	
	}

	/**
	 * Function to convert testimonial data to EDD Reviews
	 *
	 * @since 1.0
	 */

	public function dxt_eddreview_script(){	
		
		$status = get_option( 'convert_testimonial_to_review' );
		
		if ( !$status && $status != 'done' ) {
			
			$args = array( 
	            'post_type'         => array( 'testimonial' ), 
	            'posts_per_page'    => -1,
	            'post_status'       => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit' )
	        );
	        
	        $testimonial 			= get_posts( $args );
	        $comment_type 			= "edd_review";
	        $rating 				= 5;
	    	$review_author_email  	= $review_author_url = "";
	    	$comment_agent 			= $_SERVER['HTTP_USER_AGENT'];
	    	$comment_author_ip 		= ":::1";
	    	$user_id 				= '';

	    	/**
			 * Array to add dynamic string to for review title.
			 */

	    	$array_review_string 	= array(
							    		'Great Plugin',
							    		'Amazing Support',
							    		'Superb WooCommerce Extension',
							    		'Good plugin',
							    		'Fantastic plugins and support',
							    		'Fantastic support team',
							    		'Great customer service',
							    		'Highly recommended plugins'
			    		 			   );

	    	/**
			 * Array to get download id based on category of review.
			 * Key is Download ID and Value is Category Id of Testimonial.
			 */

	    	$list_of_all_categories = array( 
							    		'22' 		=> '443',
							    		'20' 		=> '444',
							    		'16' 		=> '442',
							    		'238877' 	=> '450',
							    		/*'286317' 	=> '399', */
							    		'132' 		=> '452', 
							    		'238916' 	=> '451',
							    		'207914'    => '445',
							    		'302237' 	=> '459', // export
							    		'302239' 	=> '453' // delivery notes
						    		  );

	    	/**
			 * Array to get source based on category of review.
			 */

	    	$list_of_all_sources 	= array( 
		    							'272' => '272', // fb
							    		'276' => '276', // g+
							    		'274' => '274', // twit
							    		'394' => '394', // .org
							    		'273' => '273', //review
							    		'271' => '271' // testimonial
						    		  );
	    	
	    	$comment_post_ID		= "";

	    	$list_of_all_d_id 		= array( 
	                                    '22' => '22', // bkap
	                                    '16' => '16', // ordd
	                                    '20' => '20' // ac
                                  	  );

	        foreach ( $testimonial as $key => $value ) {
	        	
	        	$review_author 		= $value->post_title;
	        	$t_date	 			= $value->post_date;
	        	$random_key 		= array_rand( $array_review_string );
				$review_title 		= $array_review_string[ $random_key ];        	
	        	$review_content 	= $value->post_content;
	        	$category 			= get_the_terms( $value->ID, 'testimonial-category' );
	        	$category_ids 		= array();
	        	$matchedkey 		= '';
	        	$source_key 		= '';
	        	$floating   		= '';

	        	if ( !empty( $category ) ) {
	        		
	        		foreach ( $category as $ckey => $cvalue ) {
	        			array_push( $category_ids, $cvalue->term_id );
	        			
	        			if ( $matchedkey == '' ) {
		        			if( in_array( $cvalue->term_id, $list_of_all_categories ) ) {
		        				$comment_post_ID = array_search ( $cvalue->term_id, $list_of_all_categories );    				
		        			}
	        			}
	        			if ( $source_key == '' ) {
	        				if( in_array( $cvalue->term_id, $list_of_all_sources ) ) {
		        				$source_key = array_search ( $cvalue->term_id, $list_of_all_sources );    				
		        			}
	        			}
	        			if ( $floating == '' ) {
                            if( $cvalue->term_id == 456 ) {
                                $floating = 'on';                   
                            }
                        }       			
	        		}
	        	} else {
	        		continue;
	        	}
	        	if ( $comment_post_ID == "" ) {                    
                    $random_d_key         = array_rand( $list_of_all_d_id );
                    $comment_post_ID     = $list_of_all_d_id[ $random_d_key ];
                }
	        	$args = apply_filters( 	'edd_reviews_insert_review_args', 
	        								array(
												'comment_post_ID'      => $comment_post_ID,
												'comment_author'       => $review_author,
												'comment_author_email' => $review_author_email,
												'comment_author_url'   => $review_author_url,
												'comment_content'      => $review_content,
												'comment_type'         => $comment_type,
												'comment_parent'       => '',
												'comment_author_IP'    => $comment_author_ip,
												'comment_agent'        => isset( $comment_agent ) ? substr( $comment_agent, 0, 254 ) : '',
												'user_id'              => $user_id,
												'comment_date'         => $t_date,
												'comment_date_gmt'     => $t_date,
												'comment_approved'     => 1
											) 
	        			);

				$comment_allowed 	= wp_allow_comment( $args );
				$args 				= apply_filters( 'preprocess_comment', $args );
				$review_id 			= wp_insert_comment( wp_filter_comment( $args ) );
				$img_url 			= "";

				if ( get_the_post_thumbnail_url( $value->ID ) ) {
					$img_url = get_the_post_thumbnail_url( $value->ID );
				}

				add_comment_meta( $review_id, 'edd_rating', $rating );
				add_comment_meta( $review_id, 'edd_review_title', $review_title );
				add_comment_meta( $review_id, 'edd_review_approved', $comment_allowed );
				// Additional fields.
				add_comment_meta( $review_id, 'edd_review_source', $source_key );
				add_comment_meta( $review_id, 'edd_review_categories', $category_ids );
				add_comment_meta( $review_id, 'edd_review_generic', $floating );
				add_comment_meta( $review_id, 'edd_review_img_url', $img_url );
				
				// Add review metadata to the $args so it can be passed to the notification email
				$args['id']              = $review_id;
				$args['rating' ]         = $rating;
				$args['review_title']    = $review_title;
				$args['review_approved'] = $comment_allowed;		
				
				update_post_meta( $comment_post_ID, 'edd_reviews_average_rating', EDD_Reviews::average_rating( false, $comment_post_ID ) );
			}	
			
			update_option( 'convert_testimonial_to_review', 'done' );	
        }
	}

	/**
	 * This function will include the scrip file on the edit review page. 
	 * It will add the tyche review data meta box on the review edit page.
	 *
	 * @since: 1.0
	 */

	public function tyche_edd_review_enqueue_scripts_js () {

		if ( isset( $_GET [ 'page'] ) &&  isset( $_GET [ 'edit'] ) && "true" == $_GET [ 'edit'] && "edd-reviews" == $_GET [ 'page'] ) {

			$review_id = absint( $_GET['r'] );
			$review    = get_comment( $review_id, OBJECT );

			$tyche_get_review_metabox_html = EDD_GF_Integration::tyche_get_review_meta_box_data ( $review ); 

			wp_register_script( 'edd-reviews-add-fields' , plugins_url() . '/edd-review-gf-addon/assets/js/admin/edd-reviews-add-fields.js' );
        	wp_enqueue_script( 'edd-reviews-add-fields' );

        	$tyche_review_data = array(
        			'tyche_review_data' => $tyche_get_review_metabox_html,
        		); 
			wp_localize_script( 'edd-reviews-add-fields', 'tyche_params', $tyche_review_data );
    	}
	}

	/**
	 * This function will create the html required for the tyche review meta box.
	 *
	 * @since: 1.0
	 * @return: string
	 */

	public function tyche_get_review_meta_box_data ( $review ) {
		
		$tyche_review_source  = get_comment_meta( $review->comment_ID, 'edd_review_source', true );
		$tyche_review_img_url = get_comment_meta( $review->comment_ID, 'edd_review_img_url', true );
		$tyche_review_generic = get_comment_meta( $review->comment_ID, 'edd_review_generic', true );

		$tyche_review_generic_checked = '';
		
		if ( "on" == $tyche_review_generic ) {
			$tyche_review_generic_checked = "checked";
		}

		if ( $tyche_review_source == '' ) {
			$tyche_review_source = array();			
		}

		if ( $tyche_review_source != '' && !is_array( $tyche_review_source) ) {
			$tyche_review_source = explode(' ', $tyche_review_source);
		}
		
		$tyche_list_of_category = 	array( 	''    => 'Select a source',
											'273' => 'Review',
											'272' => 'Facebook',
											'276' => 'Google+',
											'271' => 'Testimonial',
											'274' => 'Twitter',
											'394' => 'WordPress.org',
											'546' => 'Website' 
									);

		$tyche_selected_resource = '';
		
		foreach ( $tyche_list_of_category as $tyche_list_of_category_key => $tyche_list_of_category_value ) {

			if ( count( $tyche_review_source ) && in_array( $tyche_list_of_category_key, $tyche_review_source ) ) {
				$tyche_selected_resource = $tyche_list_of_category_key;
			}
		}

		$tyche_review_source_drop_down = '';
		
		foreach ( $tyche_list_of_category as $key => $value ) {
            $sel = '';
            if ( $key == $tyche_selected_resource ) {
                $sel = __( ' selected ', 'edd-reviews' );
            }
            $tyche_review_source_drop_down .= "<option value=" . $key . " $sel> " . __( $value, "edd-reviews" ) . " </option>";
        }

		$tyche_review_image_url      =  esc_attr($review->edd_review_img_url);

		$tyche_review_source_text    = __( 'Review Source:', 'edd-reviews' );
		$tyche_review_image_url_text = __( 'Image URL:', 'edd-reviews' );
		$tyche_review_generic_text   = __( 'Is Ganeric Review:', 'edd-reviews' );
		
		$tyche_review_meta_box = "<div id='namediv' class='stuffbox tyche_review'>
			<div class='inside'>
				<fieldset>
					<legend class='edit-comment-author'>Tyche Review Data</legend>
					<table class='form-table editcomment'>
						<tbody>
							<tr>
								<td class='first'><label for='tyche_review_edd_source'>$tyche_review_source_text</label></td>
								<td>
									
									<select id='edd_review_source' name='edd_review_source' >
										$tyche_review_source_drop_down
		                            </select>
		                            <?php

									?>
								</td>
							</tr>

							<tr>
								<td class='first'><label for='tyche_review_edd_image_url'>$tyche_review_image_url_text</label>
								</td>
								<td>
									<input type='url' id='edd_review_img_url' name='edd_review_img_url' size='30' class='code' value=$tyche_review_img_url > </input>
								</td>
							</tr>

							<tr>
								<td class='first'><label for='tyche_ganeric_review_edd'> $tyche_review_generic_text </label>
								</td>
								<td>
									<input type ='checkbox' id='edd_review_generic' name='edd_review_generic' value='on' style='width: 0%' $tyche_review_generic_checked > </input>
								</td>
							</tr>
						</tbody>
					</table>
				</fieldset>
			</div><!-- /.inside -->
		</div>";

		return $tyche_review_meta_box;
	}

	/**
	 * This function will update the tyche review data in the comment meta.
	 * 
	 * @since: 1.0
	 */
	public function tyche_update_review_data () {
		$review_id = absint( $_REQUEST['r'] );
		
		if ( isset( $_POST['edd_review_source'] ) ) {
			$updated_edd_review_source = sanitize_text_field( $_POST['edd_review_source'] );
			update_comment_meta( $review_id, 'edd_review_source', $updated_edd_review_source );
		}

		if ( isset( $_POST['edd_review_generic'] ) ) {
			$updated_edd_review_generic = sanitize_text_field( $_POST['edd_review_generic'] );
			update_comment_meta( $review_id, 'edd_review_generic', $updated_edd_review_generic );
		} else {
			$updated_edd_review_generic = "off";
			update_comment_meta( $review_id, 'edd_review_generic', $updated_edd_review_generic );
		}

		if ( isset( $_POST['edd_review_img_url'] ) ) {
			$updated_edd_review_img_url = esc_url( $_POST['edd_review_img_url'] );
			update_comment_meta( $review_id, 'edd_review_img_url', $updated_edd_review_img_url );
		}
	}		
}
$edd_gf_integration = new EDD_GF_Integration();