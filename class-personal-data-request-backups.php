<?php // phpcs:ignore - \r\n notice.

/**
 *
 * Plugin Name:       Personal Data Request Backups
 * Description:       Keep an offsite backup of the Personal Data Requests.
 * Version:           1.0
 * Author:            Konstantinos Xenos
 * Author URI:        https://xkon.gr
 * License:           GPLv2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pdr-backups
 * Domain Path:       /languages
 *
 * Copyright (C) 2019 Konstantinos Xenos (https://xkon.gr).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see https://www.gnu.org/licenses/.
 */

// Check that the file is not accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'We\'re sorry, but you can not directly access this file.' );
}

if ( ! class_exists( 'Personal_Data_Request_Backups' ) ) {
	class Personal_Data_Request_Backups {
		/**
		 * Instance.
		 *
		 * @var $instance.
		 */
		private static $instance = null;

		/**
		 * Plugin exports dir.
		 *
		 * @var $pdr_exports_dir;
		 */
		private $pdr_exports_dir;

		/**
		 * Plugin exports URL.
		 *
		 * @var $pdr_exports_url;
		 */
		private $pdr_exports_url;





		/**
		 * Return class instance.
		 */
		public static function get_instance() {
			if ( ! self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		} // public static function get_instance()





		/**
		 * Constructor.
		 */
		public function __construct() {
			// Register the plugin on the Import screen.
			add_action( 'admin_init', array( $this, 'register_importers' ) );

			// Set the cron events.
			$this->setup_crons();
			add_action( 'pdr_auto_export', array( $this, 'export_cron' ) );
			add_action( 'pdr_clean_files', array( $this, 'clean_files' ) );

			// Create and set the uploads dir.
			$this->set_pdr_exports_dir();

			// Ajax actions.
			add_action( 'wp_ajax_pdr-manual-export', array( $this, 'export' ) );
			add_action( 'wp_ajax_pdr-manual-import', array( $this, 'import' ) );
		} // public function __construct()





		/**
		 * Populate options on plugin activation.
		 */
		public static function plugin_activate() {
			$current_user = wp_get_current_user();
			update_option( 'pdr_backups_email', $current_user->user_email );
			update_option( 'pdr_backups_auto_backup', false );
		} // public static function plugin_activate()





		/**
		 * Remove options on plugin deactivation.
		 */
		public static function plugin_deactivate() {
			delete_option( 'pdr_backups_email' );
			delete_option( 'pdr_backups_auto_backup' );

			wp_clear_scheduled_hook( 'pdr_auto_export' );
			wp_clear_scheduled_hook( 'pdr_clean_files' );
		} // public static function plugin_deactivate()





		/**
		 * Check capabilities.
		 */
		public function check_caps() {
			if (
				! current_user_can( 'manage_options' ) ||
				! current_user_can( 'manage_privacy_options' ) ||
				! current_user_can( 'export_others_personal_data' ) ||
				! current_user_can( 'erase_others_personal_data' )
			) {
				wp_die( 'No access.', 'pdr-backups' );
			}
		}





		/**
		 * Create and set pdr_exports_dir.
		 */
		public function set_pdr_exports_dir() {
			// Find WP uploads directory.
			$wp_upload_dir = wp_upload_dir();

			// Populate the plugin path.
			$pdr_exports_dir = wp_normalize_path( trailingslashit( $wp_upload_dir['basedir'] ) . 'pdr-backups/' );
			$pdr_exports_url = wp_normalize_path( trailingslashit( $wp_upload_dir['baseurl'] ) . 'pdr-backups/' );

			// Create the dir if it doesn't exist.
			wp_mkdir_p( $pdr_exports_dir );

			// Protect export folder from browsing.
			$index_file = $pdr_exports_dir . 'index.html';

			if ( ! file_exists( $index_file ) ) {
				$file = fopen( $index_file, 'w' );
				fwrite( $file, '<!-- Silence. -->' );
				fclose( $file );
			}

			// Populate the $pdr_exports_dir var.
			$this->pdr_exports_dir = $pdr_exports_dir;

			// Populate the $pdr_exports_url var.
			$this->pdr_exports_url = $pdr_exports_url;
		}





		/**
		 * Register the plugin on the Import screen.
		 */
		public function register_importers() {
			$this->check_caps();

			register_importer(
				'pdr_backups_importer',
				__( 'Personal Data Request Backups', 'pdr-backups' ),
				__( 'Import &amp; Export Personal Data Requests', 'pdr-backups' ),
				array( $this, 'import_page' )
			);
		} // public function register_importers()





		/**
		 * Setup the daily cron event.
		 */
		public function setup_crons() {
			// Auto backup cron.
			$enabled = get_option( 'pdr_backups_auto_backup' );

			if ( $enabled ) {
				if ( ! wp_next_scheduled( 'pdr_auto_export' ) ) {
					wp_schedule_event( time(), 'daily', 'pdr_auto_export' );
				}
			} else {
				if ( wp_next_scheduled( 'pdr_auto_export' ) ) {
					wp_clear_scheduled_hook( 'pdr_auto_export' );
				}
			}

			// Clean files cron.
			if ( ! wp_next_scheduled( 'pdr_clean_files' ) ) {
				wp_schedule_event( time() + 60 * 60, 'hourly', 'pdr_clean_files' );
			}
		} // public function setup_cron()





		/**
		 * Send export backup to email via cron.
		 */
		public function export_cron() {
			error_log( 'sending mail' );
		} // public function export_cron()





		/**
		 * Clean files.
		 */
		public function clean_files() {
			// Make sure that the folder is there.
			$this->set_pdr_exports_dir();

			// Remove files.
			$files = array_diff( scandir( $this->pdr_exports_dir ), array( '..', '.', 'index.html' ) );

			foreach ( $files as $file ) {
				wp_delete_file( $this->pdr_exports_dir . $file );
			}
		} // public function clean_files()





		/**
		 * Import Screen.
		 */
		public function import_page() {
			$auto_export  = get_option( 'pdr_backups_auto_backup' );
			$export_email = sanitize_email( get_option( 'pdr_backups_email' ) );
			?>
			<div class="wrap pdr-content">
				<h1><?php esc_html_e( 'Personal Data Request Backups', 'pdr-backups' ); ?></h1>
				<p>
					<?php
					echo sprintf(
						// translators: %1$s Links to Export Personal Data screen. %2$s Links to Erase Personal Data screen.
						__( 'When you restore your website to an earlier backup you might lose some of the <a href="%1$s">Personal Data Export</a> &amp; <a href="%2$s">Personal Data Erasure</a> Requests.', 'pdr-backups' ),
						esc_attr( admin_url( 'export-personal-data.php' ) ),
						esc_attr( admin_url( 'erase-personal-data.php' ) )
					);
					?>
				</p>
				<p>
					<?php esc_html_e( 'This creates a problem as you might have newer requests especially for Erasures that will need to be fulfilled again according to the regulations.', 'pdr-backups' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'Exporting &amp; importing the Personal Data equests as a separate backup will help you on keeping always a latest separate copy of the requests for an occasion like that.', 'pdr-backups' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'You can set up an e-mail to receive the attached file of the backup on a daily cron or manually request an additional Export when needed.', 'pdr-backups' ); ?>
				</p>
				<div class="notice notice-warning inline">
					<p>
						<?php _e( '<strong>Important!</strong> By importing an existing JSON file all current Requests that are registered in the database will be removed. Both of the Export Personal Data &amp; Erasure Personal Data request lists will be re-created exactly as they are found in the JSON file.', 'pdr-backups' ); ?>
					</p>
				</div>
				<div class="pdr-forms">
					<div class="form-wrapper">
						<div class="form-content">
							<h2><?php esc_html_e( 'Settings', 'pdr-backups' ); ?></h2>
							<form method="post" id="pdr-settings-form">
								<p>
									<input
										type="checkbox"
										name="enable-auto-export"
										id="enable-auto-export"
										value="<?php echo esc_attr( $auto_export ); ?>"
										<?php checked( $auto_export, '1', true ); ?>
									/>
									<label for="enable-auto-export">
											<?php esc_html_e( 'Enable automated export', 'pdr-backups' ); ?>
									</label>
								</p>
								<p>
									<label for="pdr-email">
										<?php esc_html_e( 'Enter your e-mail address', 'pdr-backups' ); ?>
									</label>
									<input
										type="email"
										name="pdr-email"
										id="pdr-email"
										class="large-text"
										value="<?php echo esc_attr( $export_email ); ?>" />
								</p>
								<p class="form-actions">
									<span class="msg"></span>
									<span class="spinner"></span>
									<input
										type="submit"
										class="button"
										value="<?php esc_html_e( 'Save', 'pdr-backups' ); ?>"
									/>
								</p>
							</form>
						</div>
					</div>
					<div class="form-wrapper">
						<div class="form-content">
							<h2><?php esc_html_e( 'Import', 'pdr-backups' ); ?></h2>
							<form method="post" id="pdr-import-form" enctype="multipart/form-data">
								<p>
									<input
										type="file"
										name="pdr-file"
										id="pdr-file"
									/>
								</p>
								<p class="form-actions">
									<span class="msg"></span>
									<span class="spinner"></span>
									<?php wp_nonce_field( 'pdr_manual_import', 'pdr-manual-import-nonce' ); ?>
									<input
										type="submit"
										class="button"
										value="<?php esc_html_e( 'Import', 'pdr-backups' ); ?>"
									/>
								</p>
							</form>
						</div>
					</div>
					<div class="form-wrapper">
						<div class="form-content">
							<h2><?php esc_html_e( 'Export', 'pdr-backups' ); ?></h2>
							<form method="post" id="pdr-export-form">
								<p>
									<?php esc_html_e( 'You will be prompted to save a file.', 'pdr-backups' ); ?>
								</p>
								<p class="form-actions">
									<span class="msg"></span>
									<span class="spinner"></span>
									<?php wp_nonce_field( 'pdr_manual_export', 'pdr-manual-export-nonce' ); ?>
									<input
										type="submit"
										class="button button-primary"
										value="<?php esc_html_e( 'Export', 'pdr-backups' ); ?>"
									/>
								</p>
							</form>
						</div>
					</div>
				</div>
			</div>

			<!-- styles -->
			<style>
				.pdr-content {
					max-width: 900px;
				}

				.pdr-content .pdr-forms {
					display: grid;
					grid-template-columns: 1fr 1fr 1fr;
					grid-gap: 10px;
				}

				.pdr-content .form-wrapper {
					font-size: 14px;
					margin: 0;
					line-height: 1.4;
				}

				.pdr-content .form-wrapper .form-content {
					position: relative;
					border: 1px solid #ccd0d4;
					box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
					background: #fff;
					padding: 10px;
					height: calc( 100% - 22px );
				}

				.pdr-content .form-wrapper .form-content h2 {
					margin-top: 0;
				}

				.pdr-content .form-wrapper .form-actions {
					padding-bottom: 0;
					margin-bottom: 0;
					text-align: right;
				}

				.pdr-content .form-wrapper .form-actions .spinner {
					float: unset;
					display: none;
				}

				.pdr-content .form-wrapper .form-actions .msg {
					display: inline-block;
					margin-right: 10px;
				}
			</style>

			<!-- scripts -->
			<script>
				( function( $ ) {
					$( '#pdr-settings-form' ).on( 'submit', function( e ) {
						e.preventDefault();
						var spinner = $( this ).find( '.spinner' );

						spinner.css( 'display', 'inline-block' );
						spinner.addClass( 'is-active' );
					});

					$( '#pdr-import-form' ).on( 'submit', function( e ) {
						e.preventDefault();

						var spinner = $( this ).find( '.spinner' ),
							msg = $( this ).find( '.msg' ),
							args = new FormData();

						args.append( 'action', 'pdr-manual-import' );
						args.append( 'pdr_nonce', $( '#pdr-manual-import-nonce' ).val() );
						args.append( 'pdr-file', $( '#pdr-file' )[0].files[0] );

						msg.html( '' );
						spinner.css( 'display', 'inline-block' );
						spinner.addClass( 'is-active' );

						$.ajax({
							url: ajaxurl,
							method: 'POST',
							global: false,
							dataType: 'json',
							contentType: false,
							processData: false,
							data: args,
							success: function( response ) {
								if ( true === response.success ) {
									//window.location = response.data
								} else {
									msg.html( response.data );
									msg.css( 'color', 'red' );
								}

								spinner.css( 'display', 'none' );
								spinner.removeClass( 'is-active' );
							}
						});
					});

					$( '#pdr-export-form' ).on( 'submit', function( e ) {
						e.preventDefault();

						var spinner = $( this ).find( '.spinner' ),
							msg = $( this ).find( '.msg' ),
							args = {
								'action': 'pdr-manual-export',
								'pdr_nonce': $( '#pdr-manual-export-nonce' ).val()
							};

						msg.html( '' );
						spinner.css( 'display', 'inline-block' );
						spinner.addClass( 'is-active' );

						$.ajax({
							url: ajaxurl,
							method: 'POST',
							global: false,
							dataType: 'json',
							data: args,
							success: function( response ) {
								if ( true === response.success ) {
									window.location = response.data
								} else {
									msg.html( response.data );
									msg.css( 'color', 'red' );
								}

								spinner.css( 'display', 'none' );
								spinner.removeClass( 'is-active' );
							}
						});
					});
				} ( jQuery ) );
			</script>
			<?php
		} // public function import_page()





		/**
		 * Handle Import.
		 */
		public function import() {
			// Make checks and error out if something is wrong.
			if ( ! isset( $_POST['pdr_nonce'] ) ) {
				wp_send_json_error( esc_html__( 'pdr_nonce does not exist.', 'pdr-backups' ) );
			}

			$nonce = sanitize_text_field( $_POST['pdr_nonce'] );

			if ( ! wp_verify_nonce( $nonce, 'pdr_manual_import' ) ) {
				wp_send_json_error( esc_html__( 'The nonce could not be verified.', 'pdr-backups' ) );
			}

			if (
				empty( $_FILES['pdr-file']['name'] ) ||
				'json' !== strtolower( pathinfo( $_FILES['pdr-file']['name'], PATHINFO_EXTENSION ) )
			) {
				wp_send_json_error( esc_html__( 'Please upload the json file.', 'pdr-backups' ) );
			}

			// Clean the database from existing requests.
			$this->clean_requests();

			// Read the contents of the .json file.
			$json = file_get_contents( wp_normalize_path( $_FILES['pdr-file']['tmp_name'] ) );

			// Remove the temporary file.
			unset( $_FILES['pdr-file']['tmp_name'] );

			$import_array = json_decode( base64_decode( $json ) );

			// Import Personal Data Exports.
			foreach ( $import_array->exports as $export ) {
				$post = wp_insert_post(
					array(
						'' => '',
					)
				);

				if ( is_wp_error( $post ) ) {
					wp_send_json_error( esc_html__( 'Could not import all Export Requests.', 'pdr-backups' ) );
				}
			}

			// Import Personal Data Erasures.
			foreach ( $import_array->erasures as $erasure ) {
				error_log( print_r( $erasure, true ) );

				if ( is_wp_error( $post ) ) {
					wp_send_json_error( esc_html__( 'Could not import all Export Requests.', 'pdr-backups' ) );
				}
			}

			wp_send_json_success();
		} // public function import()





		/**
		 * Handle Export.
		 */
		public function export() {
			// Make checks and error out if something is wrong.
			if ( ! isset( $_POST['pdr_nonce'] ) ) {
				wp_send_json_error( esc_html__( 'pdr_nonce does not exist.', 'pdr-backups' ) );
			}

			$nonce = sanitize_text_field( $_POST['pdr_nonce'] );

			if ( ! wp_verify_nonce( $nonce, 'pdr_manual_export' ) ) {
				wp_send_json_error( esc_html__( 'The nonce could not be verified.', 'pdr-backups' ) );
			}

			if ( ! class_exists( 'ZipArchive' ) ) {
				wp_send_json_error( esc_html__( 'ZipArchive is not available.', 'pdr-backups' ) );
			}

			// Export Personal Data Exports.
			global $wpdb;

			$exports = array();

			$exports = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT *
					FROM $wpdb->posts
					WHERE post_type = %s
					AND post_name = %s",
					'user_request',
					'export_personal_data'
				),
				ARRAY_A
			);

			// Export Personal Data Erasures.
			$erasures = array();

			$erasures = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT *
					FROM $wpdb->posts
					WHERE post_type = %s
					AND post_name = %s",
					'user_request',
					'remove_personal_data'
				),
				ARRAY_A
			);

			// Merge the requests.
			$requests = array(
				'exports'  => $exports,
				'erasures' => $erasures,
			);

			// Encode the requests and encode them to avoid losing characters.
			$data = base64_encode( wp_json_encode( $requests ) );

			// Export to file.
			$date_time = wp_date( 'dmY-His' );

			$json_file_name = 'personal-data-request-backups-' . $date_time . '.json';
			$json_file_path = $this->pdr_exports_dir . $json_file_name;

			if ( file_exists( $json_file_path ) ) {
				wp_delete_file( $json_file_path );
			}

			$file = fopen( $json_file_path, 'w' );
			fwrite( $file, $data );
			fclose( $file );

			// Zip the exported file.
			$zip_file_name = 'personal-data-request-backups-' . $date_time . '.zip';
			$zip_file_path = $this->pdr_exports_dir . $zip_file_name;

			if ( file_exists( $zip_file_path ) ) {
				wp_delete_file( $zip_file_path );
			}

			$zip = new ZipArchive;
			if ( true === $zip->open( $zip_file_path, ZipArchive::CREATE ) ) {
				if ( ! $zip->addFile( $json_file_path, $json_file_name ) ) {
					wp_send_json_error( esc_html__( 'Unable to zip file.', 'pdr-backups' ) );
				}
				$zip->close();
			} else {
				wp_send_json_error( esc_html__( 'Unable to open zip file.', 'pdr-backups' ) );
			}

			// Delete the .json file since the .zip was created.
			wp_delete_file( $json_file_path );

			// Send the file for download.
			$pdr_file_url = $this->pdr_exports_url . $zip_file_name;

			wp_send_json_success( $pdr_file_url );
		} // public function manual_export()





		/**
		 * Clean the database from existing requests.
		 */
		public function clean_requests() {
			// Remove all existing Export Data Requests.
			global $wpdb;

			$del_exports = array();

			$del_exports = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID
					FROM $wpdb->posts
					WHERE post_type = %s
					AND post_name = %s",
					'user_request',
					'export_personal_data'
				),
				ARRAY_A
			);

			foreach ( $del_exports as $del_export ) {
				wp_delete_post( $del_export['ID'] );
			}

			// Remove all existing Erasure Data Requests.
			$del_erasures = array();

			$del_erasures = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID
					FROM $wpdb->posts
					WHERE post_type = %s
					AND post_name = %s",
					'user_request',
					'remove_personal_data'
				),
				ARRAY_A
			);

			foreach ( $del_erasures as $del_erasure ) {
				wp_delete_post( $del_erasure['ID'] );
			}
		} // public function import()
	} // class Personal_Data_Request_Backups





	/**
	 * Load plugin.
	 */
	add_action(
		'plugins_loaded',
		array(
			'Personal_Data_Request_Backups',
			'get_instance',
		)
	);

	/**
	 * Activation Hook
	 */
	register_activation_hook( __FILE__, array( 'Personal_Data_Request_Backups', 'plugin_activate' ) );

	/**
	 * Dectivation Hook
	 */
	register_deactivation_hook( __FILE__, array( 'Personal_Data_Request_Backups', 'plugin_deactivate' ) );
} // if ! class_exists Personal_Data_Request_Backups
