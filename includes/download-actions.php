<?php
/**
 * Download Actions
 *
 * @package     EDD\FreeDownloads\Download\Actions
 * @since       2.0.0
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Process downloads
 *
 * @since       1.0.0
 * @return      void
 */
function edd_free_download_process() {
	// No spammers please!
	if ( ! empty( $_POST['edd_free_download_check'] ) ) {
		wp_die( __( 'Bad spammer, no download!', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
	}

	if ( ! isset( $_POST['edd_free_download_nonce'] ) || ! wp_verify_nonce( $_POST['edd_free_download_nonce'], 'edd_free_download_nonce' ) ) {
		wp_die( __( 'Cheatin&#8217; huh?', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
	}

	if ( ! isset( $_POST['edd_free_download_email'] ) ) {
		wp_die( __( 'An internal error has occurred, please try again or contact support.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ), array( 'back_link' => true ) );
	}

	if ( ! is_user_logged_in() ) {
		// Bypass auto-registration
		if ( edd_get_option( 'edd_free_downloads_bypass_auto_register', false ) && class_exists( 'EDD_Auto_Register' ) ) {
			remove_action( 'edd_auto_register_insert_user', array( EDD_Auto_Register::get_instance(), 'email_notifications' ), 10, 3 );
			remove_action( 'edd_insert_payment', array( EDD_Auto_Register::get_instance(), 'maybe_insert_user' ), 10, 2 );
		}
	}

	if ( edd_get_option( 'edd_free_downloads_user_registration', false ) && ! is_user_logged_in() && ! class_exists( 'EDD_Auto_Register' ) ) {
		// If we are registering a user, make sure the required fields are filled out
		if( edd_get_option( 'edd_free_downloads_user_registration', false ) && ! class_exists( 'EDD_Auto_Register' ) ) {
			if ( ! isset( $_POST['edd_free_download_username'] ) || ! isset( $_POST['edd_free_download_pass'] ) || ! isset( $_POST['edd_free_download_pass2'] ) ) {
				wp_die( __( 'The username and password fields are required, please try again.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ), array( 'back_link' => true ) );
			}
		}

		if ( $_POST['edd_free_download_pass'] != $_POST['edd_free_download_pass2'] ) {
			wp_die( __( 'Password and password confirmation fields don\'t match, please try again,', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ), array( 'back_link' => true ) );
		}

		// Make sure the username doesn't already exist
		$username = trim( $_POST['edd_free_download_username'] );

		if ( username_exists( $username ) ) {
			wp_die( __( 'The specified username already exists, please log in or try again.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ), array( 'back_link' => true ) );
		} elseif ( ! edd_validate_username( $username ) ) {
			// Invalid username
			if ( is_multisite() ) {
				wp_die( __( 'Invalid username. Only lowercase letters (a-z) and numbers are allowed.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ), array( 'back_link' => true ) );
			} else {
				wp_die( __( 'Invalid username.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ), array( 'back_link' => true ) );
			}
		}

		// Make sure the email doesn't already exist
		if ( email_exists( $_POST['edd_free_download_email'] ) ) {
			wp_die( __( 'The specified email has already been used, please log in or try again.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ), array( 'back_link' => true ) );
		}
	}

	$email = sanitize_email( trim( $_POST['edd_free_download_email'] ) );
	$user  = get_user_by( 'email', $email );

	if ( ! is_email( $_POST['edd_free_download_email'] ) || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
		wp_die( __( 'An internal error has occurred, please try again or contact support.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
	}

	// No banned emails please!
	if ( edd_is_email_banned( $email ) ) {
		wp_die( __( 'An internal error has occurred, please try again or contact support.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
	}

	$download_id = isset( $_POST['edd_free_download_id'] ) ? intval( $_POST['edd_free_download_id'] ) : false;
	if ( empty( $download_id ) ) {
		wp_die( __( 'An internal error has occurred, please try again or contact support.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
	}

	$download = get_post( $download_id );

	// Bail if this isn't a valid download
	if ( ! is_object( $download ) ) {
		wp_die( __( 'An internal error has occurred, please try again or contact support.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
	}

	if ( 'download' != $download->post_type ) {
		wp_die( __( 'An internal error has occurred, please try again or contact support.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
	}

	// Bail if this isn't a published download (or the current user can't edit it)
	if ( ! current_user_can( 'edit_post', $download->ID ) && $download->post_status != 'publish' ) {
		wp_die( __( 'An internal error has occurred, please try again or contact support.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
	}

	if ( isset( $_POST['edd_free_download_fname'] ) ) {
		$user_first = sanitize_text_field( $_POST['edd_free_download_fname'] );
	} else {
		$user_first = $user ? $user->first_name : '';
	}

	if ( isset( $_POST['edd_free_download_lname'] ) ) {
		$user_last = sanitize_text_field( $_POST['edd_free_download_lname'] );
	} else {
		$user_last = $user ? $user->last_name : '';
	}

	$payment = new EDD_Payment;
	$payment->first_name = $user_first;
	$payment->last_name  = $user_last;
	$payment->user_id    = $user ? $user->ID : 0;
	$payment->email      = $email;
	$payment->total      = 0;
	$payment->gateway    = 'manual';
	$payment->status     = 'pending';

	$cart_details   = array();
	$price_ids      = isset( $_POST['edd_free_download_price_id'] ) ? $_POST['edd_free_download_price_id'] : false;
	$download_files = array();

	if ( $price_ids ) {

		foreach ( $price_ids as $cart_id => $price_id ) {

			if ( ! edd_is_free_download( $download_id, $price_id ) ) {
				wp_die( __( 'An internal error has occurred, please try again or contact support.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
			}

			$download_files[] = edd_get_download_files( $download_id, $price_id );

			$payment->add_download( $download_id, array(
				'price_id'   => $price_id,
				'item_price' => 0
			) );

		}

	} else {

		if ( ! edd_is_free_download( $download_id ) ) {
			wp_die( __( 'An internal error has occurred, please try again or contact support.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
		}

		$download_files[] = edd_get_download_files( $download_id, false );

		$payment->add_download( $download_id, array(
			'price_id'   => $price_id
		) );

	}

	// Disable purchase emails
	if ( edd_get_option( 'edd_free_downloads_disable_emails', false ) ) {
		remove_action( 'edd_complete_purchase', 'edd_trigger_purchase_receipt', 999 );

		if ( function_exists( 'Receiptful' ) ) {
			remove_action( 'edd_complete_purchase', array( Receiptful()->email, 'send_transactional_email' ) );
		}
	}

	$payment->save();
	$payment->status = 'publish';
	$payment->save();
	$payment->add_note( __( 'Purchased through EDD Free Downloads', 'edd-free-downloads' ) );

	edd_empty_cart();
	$purchase_data['purchase_key'] = $payment->key;
	edd_set_purchase_session( $purchase_data );

	if ( edd_get_option( 'edd_free_downloads_user_registration', false ) && ! is_user_logged_in() && ! class_exists( 'EDD_Auto_Register' ) ) {
		$account = array(
			'user_login' => trim( sanitize_text_field( $_POST['edd_free_download_username'] ) ),
			'user_pass'  => trim( sanitize_text_field( $_POST['edd_free_download_pass'] ) ),
			'user_email' => $email,
			'first_name' => $user_first,
			'last_name'  => $user_last
		);

		edd_register_and_login_new_user( $account );
	}

	$on_complete  = edd_get_option( 'edd_free_downloads_on_complete', 'default' );
	$success_page = edd_get_success_page_uri();

	switch ( $on_complete ) {
		case 'default' :
			$redirect_url = $success_page;
			break;
		case 'redirect' :
			$custom_url   = edd_get_option( 'edd_free_downloads_redirect', false );
			$custom_url   = $custom_url ? esc_url( $custom_url ) : $success_page;
			$redirect_url = $custom_url;
			break;
		case 'auto-download' :
			$redirect_url = add_query_arg( array(
				'edd_action' => 'free_downloads_process_download',
				'payment-id' => $payment_id
			) );
			break;
	}

	$redirect_url = $redirect_url ? $redirect_url : $success_page;

	// Support Conditional Success Redirects
	if ( function_exists( 'edd_csr_is_redirect_active' ) && $redirect_url == $success_page ) {
		if ( edd_csr_is_redirect_active( edd_csr_get_redirect_id( $payment->cart_details[0]['id'] ) ) ) {
			$redirect_id = edd_csr_get_redirect_id( $payment->cart_details[0]['id'] );

			$redirect_url = edd_csr_get_redirect_page_id( $redirect_id );
			$redirect_url = get_permalink( $redirect_url );
		}
	}

	wp_redirect( apply_filters( 'edd_free_downloads_redirect', add_query_arg( 'payment_key', $payment->key, $redirect_url ), $payment_id ) );
	edd_die();
}
add_action( 'edd_free_download_process', 'edd_free_download_process' );


/**
 * Process auto download
 *
 * @since       1.0.8
 * @return      void
 */
function edd_free_downloads_process_auto_download() {
	if ( ! isset( $_GET['payment-id'] ) && ! $_GET['download_id'] ) {
		return;
	}

	if ( ! function_exists( 'edd_get_file_ctype' ) ) {
		require_once EDD_PLUGIN_DIR . 'includes/process-download.php';
	}

	$download_files = array();

	if ( isset( $_GET['payment-id'] ) ) {
		$payment_meta = edd_get_payment_meta( $_GET['payment-id'] );
		$cart         = edd_get_payment_meta_cart_details( $_GET['payment-id'], true );

		if ( $cart ) {
			foreach ( $cart as $key => $item ) {
				$download_id = $item['id'];
				$archive_url = get_post_meta( $download_id, '_edd_free_downloads_file', true );

				if ( $archive_url && $archive_url != '' ) {
					$download_files = array_merge( $download_files, array( basename( $archive_url ) => $archive_url ) );
				} else {
					if ( array_key_exists( 'item_number', $item ) ) {
						$download_files = array_merge( $download_files, edd_free_downloads_get_files( $download_id, $item['item_number']['options']['price_id'] ) );
					} else {
						$download_files = array_merge( $download_files, edd_free_downloads_get_files( $download_id ) );
					}
				}
			}
		}
	} else {
		$download_id = absint( $_GET['download_id'] );
		$price_ids   = sanitize_text_field( $_GET['price_ids'] );
		$archive_url = get_post_meta( $download_id, '_edd_free_downloads_file', true );

		if ( $archive_url && $archive_url != '' ) {
			$download_files = array_merge( $download_files, array( basename( $archive_url ) => $archive_url ) );
		} elseif ( ! edd_is_bundled_product( $download_id ) ) {
			if ( $price_ids != '' ) {
				$price_ids = explode( ',', trim( $price_ids ) );

				foreach ( $price_ids as $price_id ) {
					$download_files = array_merge( $download_files, edd_free_downloads_get_files( $download_id, $price_id ) );
				}
			} else {
				$download_files = array_merge( $download_files, edd_free_downloads_get_files( $download_id ) );
			}
		} else {
			$download_files = array_merge( $download_files, edd_free_downloads_get_files( $download_id ) );
		}
	}

	$download_files = array_unique( $download_files );

	if( is_array( $download_files ) && count( $download_files ) > 0 ) {
		if ( count( $download_files ) > 1 ) {
			$download_url = edd_free_downloads_compress_files( $download_files, $download_id );
			$download_url = str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, $download_url );
		} else {
			$download_url = array_values( $download_files );
			$download_url = $download_url[0];
		}

		edd_free_downloads_download_file( $download_url );
	}
}
add_action( 'edd_free_downloads_process_download', 'edd_free_downloads_process_auto_download' );
