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
		 * Return class instance.
		 */
		public static function get_instance() {
			if ( ! self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 */
		public function __construct() {
			// Register the plugin on the Import screen.
			add_action( 'admin_init', array( $this, 'register_importers' ) );

			// Set the cron for the email exports.
			$this->setup_cron();
			add_action( 'pdr_auto_export_cron', array( $this, 'export_cron' ) );
		}

		/**
		 * Populate options on plugin activation.
		 */
		public static function plugin_activate() {
			$current_user = wp_get_current_user();
			update_option( 'pdr_backups_email', $current_user->user_email );
			update_option( 'pdr_backups_auto_backup', false );
		}

		/**
		 * Remove options on plugin deactivation.
		 */
		public static function plugin_deactivate() {
			delete_option( 'pdr_backups_email' );
			delete_option( 'pdr_backups_auto_backup' );
		}

		/**
		 * Register the plugin on the Import screen.
		 */
		public function register_importers() {
			if (
				current_user_can( 'manage_options' ) ||
				current_user_can( 'manage_privacy_options' ) ||
				current_user_can( 'export_others_personal_data' ) ||
				current_user_can( 'erase_others_personal_data' )
			) {
				register_importer(
					'pdr_backups_importer',
					__( 'Personal Data Request Backups', 'pdr-backups' ),
					__( 'Import &amp; Export Personal Data Requests', 'pdr-backups' ),
					array( $this, 'importer' )
				);
			}
		}

		/**
		 * Setup the daily cron event.
		 */
		public function setup_cron() {
			$enabled = get_option( 'pdr_backups_auto_backup' );

			if ( $enabled ) {
				if ( ! wp_next_scheduled( 'pdr_auto_export_cron' ) ) {
					wp_schedule_event( time(), 'daily', 'pdr_auto_export_cron' );
				}
			} else {
				if ( wp_next_scheduled( 'pdr_auto_export_cron' ) ) {
					wp_clear_scheduled_hook( 'pdr_auto_export_cron' );
				}
			}
		}

		/**
		 * Send export backup to email via cron.
		 */
		public function export_cron() {
			error_log( 'sending mail' );
		}

		/**
		 * Settings Screen.
		 */
		public function importer() {
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
					<?php esc_html_e( 'Exporting &amp; Importing Requests as a separate backup will help you on keeping always a latest separate copy of the requests for an occasion like that.', 'pdr-backups' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'You can set up an e-mail to receive the attached file of the backup on a daily cron or manually request an aditional Export when needed.', 'pdr-backups' ); ?>
				</p>
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
							<form method="post" id="pdr-import-form">
								<p>
									<input
										type="file"
										name="pdr-file"
										id="pdr-file"
									/>
								</p>
								<p class="form-actions">
									<span class="spinner"></span>
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
									<span class="spinner"></span>
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
				}
			</style>

			<!-- scripts -->
			<script>
				( function( $ ) {
					$( '#pdr-settings-form' ).on( 'submit', function( e ){
						e.preventDefault();
						console.log( 'settings!' );
					});

					$( '#pdr-import-form' ).on( 'submit', function( e ){
						e.preventDefault();
						console.log( 'import!' );
					});

					$( '#pdr-export-form' ).on( 'submit', function( e ){
						e.preventDefault();
						console.log( 'export!' );
					});
				} ( jQuery ) );
			</script>
			<?php
		}

	}

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
}
