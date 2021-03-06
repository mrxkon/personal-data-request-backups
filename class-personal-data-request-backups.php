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

// If another Personal_Data_Request_Backups class exists don't do anything /shrug.
if ( ! class_exists( 'Personal_Data_Request_Backups' ) ) {
	/**
	 * Personal_Data_Request_Backups class.
	 */
	class Personal_Data_Request_Backups {
		/**
		 * Instance.
		 */
		private static $instance = null;

		/**
		 * Plugin exports dir.
		 */
		private $pdr_exports_dir;

		/**
		 * Plugin exports URL.
		 */
		private $pdr_exports_url;

		/**
		 * User capability.
		 */
		private $user_cap;


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
			// Set user capability.
			$this->check_capability();

			// Create and set the uploads dir.
			$this->set_pdr_exports_dir();

			// Set the cron events.
			$this->setup_crons();
			add_action( 'pdr_cron_backup', array( $this, 'backup_cron' ) );
			add_action( 'pdr_clean_files', array( $this, 'clean_files' ) );

			// Add submenu page.
			add_action( 'admin_menu', array( $this, 'add_submenu' ) );

			// Add Settings link.
			add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'settings_link' ) );

			// Ajax actions.
			add_action( 'wp_ajax_pdr-save-settings', array( $this, 'save_settings' ) );
			add_action( 'wp_ajax_pdr-manual-import', array( $this, 'manual_import' ) );
			add_action( 'wp_ajax_pdr-manual-export', array( $this, 'manual_export' ) );
		} // public function __construct()





		/**
		 * Set user capability.
		 */
		public function check_capability() {
			$cap = '';

			if ( current_user_can( 'export_others_personal_data' ) ) {
				$cap = 'export_others_personal_data';
			}

			if ( current_user_can( 'erase_others_personal_data' ) ) {
				$cap = 'erase_others_personal_data';
			}

			if ( current_user_can( 'manage_privacy_options' ) ) {
				$cap = 'manage_privacy_options';
			}

			if ( current_user_can( 'manage_options' ) ) {
				$cap = 'manage_options';
			}

			$this->user_cap = $cap;
		} // public function check_capability()





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
		} // public function set_pdr_exports_dir()





		/**
		 * Setup the daily cron events.
		 */
		public function setup_crons() {
			// Auto backup cron.
			$enabled = get_option( 'pdr_backups_cron_backup' );

			if ( $enabled ) {
				if ( ! wp_next_scheduled( 'pdr_cron_backup' ) ) {
					wp_schedule_event( time(), 'daily', 'pdr_cron_backup' );
				}
			} else {
				if ( wp_next_scheduled( 'pdr_cron_backup' ) ) {
					wp_clear_scheduled_hook( 'pdr_cron_backup' );
				}
			}

			// Clean files cron.
			if ( ! wp_next_scheduled( 'pdr_clean_files' ) ) {
				wp_schedule_event( time() + 60 * 60, 'hourly', 'pdr_clean_files' );
			}
		} // public function setup_crons()





		/**
		 * Backup cron.
		 */
		public function backup_cron() {
			$export = $this->export();

			$to      = get_option( 'pdr_backups_email' );
			$subject = sprintf(
				// translators: $1%s The site URL.
				esc_html__( 'Personal Data Request Backups - %1$s', 'personal-data-request-backups' ),
				get_site_url()
			);
			$subject = apply_filters( 'pdr_backups_email_subject', $subject );
			$message = sprintf(
				// translators: $1%s The site URL.
				esc_html__( 'Personal Data Request Backups - %1$s', 'personal-data-request-backups' ),
				get_site_url()
			);
			$message = apply_filters( 'pdr_backups_email_message', $message );

			wp_mail(
				$to,
				$subject,
				$message,
				'',
				array(
					$export['file_path'],
				)
			);
		} // public function backup_cron()





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
		 * Add submenu.
		 */
		public function add_submenu() {
			if ( ! empty( $this->user_cap ) ) {
				add_submenu_page(
					'tools.php',
					esc_html__( 'Personal Data Request Backups', 'personal-data-request-backups' ),
					esc_html__( 'Personal Data Request Backups', 'personal-data-request-backups' ),
					$this->user_cap,
					'personal-data-request-backups',
					array( $this, 'pdr_page' ),
					null
				);
			}
		} // public function add_submenu()




		/**
		 * Settings link.
		 */
		public function settings_link( $links ) {
			$links = array_merge(
				array(
					'<a href="' . esc_url( admin_url( '/tools.php?page=personal-data-request-backups' ) ) . '">' . __( 'Settings', 'personal-data-request-backups' ) . '</a>',
				),
				$links
			);

			return $links;
		} // public function settings_link()





		/**
		 * Save settings.
		 */
		public function save_settings() {
			// Make checks and error out if something is wrong.
			if ( ! isset( $_POST['pdr_nonce'] ) ) {
				wp_send_json_error( esc_html__( 'pdr_nonce does not exist.', 'personal-data-request-backups' ) );
			}

			$nonce = sanitize_text_field( $_POST['pdr_nonce'] );

			if ( ! wp_verify_nonce( $nonce, 'pdr_save_settings' ) ) {
				wp_send_json_error( esc_html__( 'The nonce could not be verified.', 'personal-data-request-backups' ) );
			}

			// Cron Backup.
			if ( isset( $_POST['pdr-cron-backup'] ) ) {
				$cron_backup = sanitize_text_field( $_POST['pdr-cron-backup'] );
			}

			if ( 'true' === $cron_backup ) {
				update_option( 'pdr_backups_cron_backup', true );
			} else {
				update_option( 'pdr_backups_cron_backup', false );
			}

			// Clean files.
			if ( isset( $_POST['pdr-clean-files'] ) ) {
				$clean_files = sanitize_text_field( $_POST['pdr-clean-files'] );
			}

			if ( 'true' === $clean_files ) {
				update_option( 'pdr_backups_clean_files', true );
			} else {
				update_option( 'pdr_backups_clean_files', false );
			}

			// Email.
			if ( isset( $_POST['pdr-email-address'] ) ) {
				$email_address = sanitize_email( $_POST['pdr-email-address'] );
			}

			if ( is_email( $email_address ) ) {
				update_option( 'pdr_backups_email', $email_address );
			}

			wp_send_json_success( esc_html__( 'Settings saved!', 'personal-data-request-backups' ) );
		} // public function save_settings()





		/**
		 * Handle Import.
		 */
		public function manual_import() {
			// Make checks and error out if something is wrong.
			if ( ! isset( $_POST['pdr_nonce'] ) ) {
				wp_send_json_error( esc_html__( 'pdr_nonce does not exist.', 'personal-data-request-backups' ) );
			}

			$nonce = sanitize_text_field( $_POST['pdr_nonce'] );

			if ( ! wp_verify_nonce( $nonce, 'pdr_manual_import' ) ) {
				wp_send_json_error( esc_html__( 'The nonce could not be verified.', 'personal-data-request-backups' ) );
			}

			if (
				empty( $_FILES['pdr-file']['name'] ) ||
				'json' !== strtolower( pathinfo( $_FILES['pdr-file']['name'], PATHINFO_EXTENSION ) )
			) {
				wp_send_json_error( esc_html__( 'Please upload the json file.', 'personal-data-request-backups' ) );
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
				$ex_post = wp_insert_post(
					array(
						'post_author'           => $export->post_author,
						'post_date'             => $export->post_date,
						'post_date_gmt'         => $export->post_date_gmt,
						'post_content'          => $export->post_content,
						'post_title'            => $export->post_title,
						'post_excerpt'          => $export->post_excerpt,
						'post_status'           => $export->post_status,
						'comment_status'        => $export->comment_status,
						'ping_status'           => $export->ping_status,
						'post_password'         => $export->post_password,
						'post_name'             => $export->post_name,
						'to_ping'               => $export->to_ping,
						'pinged'                => $export->pinged,
						'post_modified'         => $export->post_modified,
						'post_modified_gmt'     => $export->post_modified_gmt,
						'post_content_filtered' => $export->post_content_filtered,
						'post_parent'           => $export->post_parent,
						'guid'                  => $export->guid,
						'menu_order'            => $export->menu_order,
						'post_type'             => $export->post_type,
						'post_mime_type'        => $export->post_mime_type,
						'comment_count'         => $export->comment_count,
					)
				);

				if ( 0 === $ex_post || is_wp_error( $ex_post ) ) {
					wp_send_json_error( esc_html__( 'Could not import all Export Requests.', 'personal-data-request-backups' ) );
				}
			}

			// Import Personal Data Erasures.
			foreach ( $import_array->erasures as $erasure ) {
				$er_post = wp_insert_post(
					array(
						'post_author'           => $erasure->post_author,
						'post_date'             => $erasure->post_date,
						'post_date_gmt'         => $erasure->post_date_gmt,
						'post_content'          => $erasure->post_content,
						'post_title'            => $erasure->post_title,
						'post_excerpt'          => $erasure->post_excerpt,
						'post_status'           => $erasure->post_status,
						'comment_status'        => $erasure->comment_status,
						'ping_status'           => $erasure->ping_status,
						'post_password'         => $erasure->post_password,
						'post_name'             => $erasure->post_name,
						'to_ping'               => $erasure->to_ping,
						'pinged'                => $erasure->pinged,
						'post_modified'         => $erasure->post_modified,
						'post_modified_gmt'     => $erasure->post_modified_gmt,
						'post_content_filtered' => $erasure->post_content_filtered,
						'post_parent'           => $erasure->post_parent,
						'guid'                  => $erasure->guid,
						'menu_order'            => $erasure->menu_order,
						'post_type'             => $erasure->post_type,
						'post_mime_type'        => $erasure->post_mime_type,
						'comment_count'         => $erasure->comment_count,
					)
				);

				if ( 0 === $er_post || is_wp_error( $ex_post ) ) {
					wp_send_json_error( esc_html__( 'Could not import all Erasure Requests.', 'personal-data-request-backups' ) );
				}
			}

			wp_send_json_success( esc_html__( 'Success!', 'personal-data-request-backups' ) );
		} // public function import()





		/**
		 * Manual Export.
		 */
		public function manual_export() {
			// Make checks and error out if something is wrong.
			if ( ! isset( $_POST['pdr_nonce'] ) ) {
				wp_send_json_error( esc_html__( 'pdr_nonce does not exist.', 'personal-data-request-backups' ) );
			}

			$nonce = sanitize_text_field( $_POST['pdr_nonce'] );

			if ( ! wp_verify_nonce( $nonce, 'pdr_manual_export' ) ) {
				wp_send_json_error( esc_html__( 'The nonce could not be verified.', 'personal-data-request-backups' ) );
			}

			$export = $this->export();

			wp_send_json_success(
				array(
					'file_contents' => file_get_contents( $export['file_path'] ),
					'file_name'     => $export['file_name'],
				)
			);
		} // public function manual_export()





		/**
		 * Handle Export.
		 */
		public function export() {
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

			return array(
				'file_url'  => $this->pdr_exports_url . $json_file_name,
				'file_path' => $this->pdr_exports_dir . $json_file_name,
				'file_name' => $json_file_name,
			);
		} // public function export()





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
		} // public function clean_requests()





		/**
		 * Personal Data Request Backups Page.
		 */
		public function pdr_page() {
			$cron_backup  = get_option( 'pdr_backups_cron_backup' );
			$clean_files  = get_option( 'pdr_backups_clean_files' );
			$export_email = sanitize_email( get_option( 'pdr_backups_email' ) );
			?>
			<!-- Wrapper -->
			<div class="wrap pdr-content">
				<!-- Info -->
				<div class="info">
					<h1><?php esc_html_e( 'Personal Data Request Backups', 'personal-data-request-backups' ); ?></h1>
					<p>
						<?php
						printf(
							// translators: %1$s Links to Export Personal Data screen. %2$s Links to Erase Personal Data screen.
							__( 'When you restore your website to an earlier backup you might lose some of the <a href="%1$s">Personal Data Export</a> &amp; <a href="%2$s">Personal Data Erasure</a> requests.', 'personal-data-request-backups' ),
							esc_attr( admin_url( 'export-personal-data.php' ) ),
							esc_attr( admin_url( 'erase-personal-data.php' ) )
						);
						?>
					</p>
					<p>
						<?php esc_html_e( 'This leads to an issue as you might have newer requests especially for Erasures that will need to be fulfilled again according to the regulations.', 'personal-data-request-backups' ); ?>
					</p>
					<p>
						<?php esc_html_e( 'Keeping a separate backup will help you on having always the latest possible copy of the requests for occasions like that.', 'personal-data-request-backups' ); ?>
					</p>
					<p>
						<?php esc_html_e( 'You can set up an e-mail to receive the backup as an attached file on a daily basis or manually create additional backups.', 'personal-data-request-backups' ); ?>
					</p>
				</div>
				<!-- / Info -->

				<!-- Boxes -->
				<div class="pdr-forms">
					<!-- Settings Box -->
					<div class="form-wrapper">
						<div class="form-content">
							<h2><?php esc_html_e( 'Settings', 'personal-data-request-backups' ); ?></h2>
							<form method="post" id="pdr-settings-form">
								<p>
									<input
										type="checkbox"
										name="pdr-clean-files"
										id="pdr-clean-files"
										value="<?php echo esc_attr( $clean_files ); ?>"
										<?php checked( $clean_files, '1', true ); ?>
									/>
									<label for="pdr-clean-files">
											<?php esc_html_e( 'Remove backup files on plugin deletion', 'personal-data-request-backups' ); ?>
									</label>
								</p>
								<p>
									<input
										type="checkbox"
										name="pdr-cron-backup"
										id="pdr-cron-backup"
										value="<?php echo esc_attr( $cron_backup ); ?>"
										<?php checked( $cron_backup, '1', true ); ?>
									/>
									<label for="pdr-cron-backup">
											<?php esc_html_e( 'Enable automated backups', 'personal-data-request-backups' ); ?>
									</label>
								</p>
								<p>
									<label for="pdr-email-address">
										<?php esc_html_e( 'Enter e-mail address to receive backups', 'personal-data-request-backups' ); ?>
									</label>
									<input
										type="email"
										name="pdr-email-address"
										id="pdr-email-address"
										value="<?php echo esc_attr( $export_email ); ?>" />
								</p>
								<p class="form-actions">
									<span class="msg"></span>
									<span class="spinner"></span>
									<?php wp_nonce_field( 'pdr_save_settings', 'pdr-save-settings-nonce' ); ?>
									<input
										type="submit"
										class="button"
										value="<?php esc_html_e( 'Save', 'personal-data-request-backups' ); ?>"
									/>
								</p>
							</form>
						</div>
					</div>
					<!-- / Settings Box -->

					<!-- Import Box -->
					<div class="form-wrapper">
						<div class="form-content">
							<h2><?php esc_html_e( 'Import', 'personal-data-request-backups' ); ?></h2>
							<p>
								<?php esc_html_e( 'By importing an existing backup all current requests will be removed. Both of the Export  &amp; Erasure request lists will be re-created as they exist in the backup.', 'personal-data-request-backups' ); ?>
							</p>
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
										value="<?php esc_html_e( 'Import', 'personal-data-request-backups' ); ?>"
									/>
								</p>
							</form>
						</div>
					</div>
					<!-- / Import Box -->

					<!-- Backup Box -->
					<div class="form-wrapper">
						<div class="form-content">
							<h2><?php esc_html_e( 'Backup', 'personal-data-request-backups' ); ?></h2>
							<form method="post" id="pdr-export-form">
								<p>
									<?php
									printf(
										// translators: %1$s The backup folder path.
										esc_html__( 'All backups are saved in "%1$s". An hourly cron is set to automatically delete them for security reasons.', 'personal-data-request-backups' ),
										$this->pdr_exports_dir
									)
									?>
								</p>
								<p>
									<?php esc_html_e( 'By pressing "Backup" you will be prompted to save a file.', 'personal-data-request-backups' ); ?>
								</p>
								<p class="form-actions">
									<span class="msg"></span>
									<span class="spinner"></span>
									<?php wp_nonce_field( 'pdr_manual_export', 'pdr-manual-export-nonce' ); ?>
									<input
										type="submit"
										class="button button-primary"
										value="<?php esc_html_e( 'Backup', 'personal-data-request-backups' ); ?>"
									/>
								</p>
							</form>
						</div>
					</div>
					<!-- / Backup Box -->
				</div>
				<!-- / Boxes -->
			</div>
			<!-- / Wrapper -->

			<!-- Styles -->
			<style>
				.pdr-content {
					max-width: 850px;
				}

				.pdr-content .info {
					position: relative;
					border: 1px solid #ccd0d4;
					box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
					background: #fff;
					padding: 10px;
					margin-bottom: 10px;
				}

				.pdr-content .info h1,
				.pdr-content .info p,
				.pdr-content .pdr-forms h2,
				.pdr-content .pdr-forms p {
					margin: 10px 0;
					padding: 0;
				}

				.pdr-content .info h1 {
					margin-top: 0;
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

				.pdr-content .form-wrapper .form-content #pdr-email-address {
					display: block;
					clear: both;
					margin-top: 5px;
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

				@media ( max-width: 1024px ) {
					.pdr-content .pdr-forms {
						grid-template-columns: 1fr;
					}
				}
			</style>

			<!-- Scripts -->
			<script>
				function pdrDownloadFile( data, fileName, type='text/plain;charset=utf-8' ) {
					const a = document.createElement( 'a' );

					a.style.display = 'none';

					document.body.appendChild( a );

					a.href = window.URL.createObjectURL(
						new Blob( [data], { type } )
					);

					a.setAttribute( 'download', fileName );

					a.click();


					window.URL.revokeObjectURL( a.href );
					document.body.removeChild( a );
				}

				( function( $ ) {
					$( '#pdr-settings-form' ).on( 'submit', function( e ) {
						e.preventDefault();

						var cron_backup,
							clean_files,
							spinner = $( this ).find( '.spinner' ),
							msg = $( this ).find( '.msg' );

						if ( $( '#pdr-cron-backup' ).is( ':checked' ) ) {
							cron_backup = true;
						} else {
							cron_backup = false;
						}

						if ( $( '#pdr-clean-files' ).is( ':checked' ) ) {
							clean_files = true;
						} else {
							clean_files = false;
						}

						msg.html( '' );
						spinner.css( 'display', 'inline-block' );
						spinner.addClass( 'is-active' );

						args = {
							'action': 'pdr-save-settings',
							'pdr_nonce': $( '#pdr-save-settings-nonce' ).val(),
							'pdr-cron-backup': cron_backup,
							'pdr-clean-files': clean_files,
							'pdr-email-address': $( '#pdr-email-address' ).val()
						};

						$.ajax({
							url: ajaxurl,
							method: 'POST',
							global: false,
							dataType: 'json',
							data: args,
							success: function( response ) {
								if ( true === response.success ) {
									msg.html( response.data );
									msg.css( 'color', 'green' );
								} else {
									msg.html( response.data );
									msg.css( 'color', 'red' );
								}

								spinner.css( 'display', 'none' );
								spinner.removeClass( 'is-active' );
							}
						});
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
									msg.html( response.data );
									msg.css( 'color', 'green' );
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
									pdrDownloadFile(
										response.data.file_contents,
										response.data.file_name
									);
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
		} // public function pdr_page()





		/**
		 * Populate options on plugin activation.
		 */
		public static function plugin_activate() {
			// Create default options if they don't exist.
			if ( ! get_option( 'pdr_backups_email' ) ) {
				// Get current user.
				$current_user = wp_get_current_user();

				update_option( 'pdr_backups_email', $current_user->user_email );
			}

			if ( ! get_option( 'pdr_backups_cron_backup' ) ) {
				update_option( 'pdr_backups_cron_backup', false );
			}

			if ( ! get_option( 'pdr_backups_clean_files' ) ) {
				update_option( 'pdr_backups_clean_files', false );
			}
		} // public static function plugin_activate()





		/**
		 * Remove options on plugin deactivation.
		 */
		public static function plugin_uninstall() {
			if ( get_option( 'pdr_backups_clean_files' ) ) {
				// Find WP uploads directory.
				$wp_upload_dir = wp_upload_dir();

				// Populate the plugin path.
				$pdr_exports_dir = wp_normalize_path( trailingslashit( $wp_upload_dir['basedir'] ) . 'pdr-backups/' );

				// Remove files.
				$files = array_diff( scandir( $pdr_exports_dir ), array( '..', '.', 'index.html' ) );

				foreach ( $files as $file ) {
					wp_delete_file( $pdr_exports_dir . $file );
				}
			}

			// Remove options.
			delete_option( 'pdr_backups_email' );
			delete_option( 'pdr_backups_cron_backup' );
			delete_option( 'pdr_backups_clean_files' );

			// Remove crons.
			wp_clear_scheduled_hook( 'pdr_cron_backup' );
			wp_clear_scheduled_hook( 'pdr_clean_files' );
		} // public static function plugin_uninstall()
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
	 * Uninstall Hook
	 */
	register_uninstall_hook( __FILE__, array( 'Personal_Data_Request_Backups', 'plugin_uninstall' ) );
} // if ! class_exists Personal_Data_Request_Backups
