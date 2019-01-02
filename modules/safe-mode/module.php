<?php
namespace Elementor\Modules\SafeMode;

use Elementor\Plugin;
use Elementor\Settings;
use Elementor\Tools;
use Elementor\Core\Common\Modules\Ajax\Module as Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Module extends \Elementor\Core\Base\Module {

	const OPTION_ENABLED = 'elementor_safe_mode';
	const MU_PLUGIN_FILE_NAME = 'elementor-safe-mode.php';
	const DOCS_HELPED_URL = 'https://go.elementor.com/safe-mode-helped/';
	const DOCS_DIDNT_HELP_URL = 'https://go.elementor.com/safe-mode-didnt-helped/';
	const DOCS_MU_PLUGINS_URL = 'https://go.elementor.com/safe-mode-mu-plugins/';
	const DOCS_TRY_SAFE_MODE_URL = 'https://go.elementor.com/safe-mode/';

	public function get_name() {
		return 'safe-mode';
	}

	public function register_ajax_actions( Ajax $ajax ) {
		$ajax->register_ajax_action( 'enable_safe_mode', [ $this, 'ajax_enable_safe_mode' ] );
		$ajax->register_ajax_action( 'disable_safe_mode', [ $this, 'disable_safe_mode' ] );
	}

	/**
	 * @param Tools $tools_page
	 */
	public function add_admin_button( $tools_page ) {
		$tools_page->add_fields( Settings::TAB_GENERAL, 'tools', [
			'safe_mode' => [
				'label' => __( 'Safe Mode', 'elementor' ),
				'field_args' => [
					'type' => 'select',
					'std' => $this->is_enabled(),
					'options' => [
						'' => __( 'Disable', 'elementor' ),
						'global' => __( 'Enable', 'elementor' ),

					],
					'desc' => __( 'Safe Mode allows you to troubleshoot issues by only loading the editor, without loading the theme or any other plugin.', 'elementor' ),
				],
			],
		] );
	}

	public function on_update_safe_mode( $value ) {
		if ( 'yes' === $value || 'global' === $value ) {
			$this->enable_safe_mode();
		} else {
			$this->disable_safe_mode();
		}

		return $value;
	}

	public function on_add_safe_mode( $option, $value ) {
		$this->on_update_safe_mode( $value );
	}

	public function ajax_enable_safe_mode( $data ) {
		// It will run `$this->>update_safe_mode`.
		update_option( 'elementor_safe_mode', 'yes' );

		$document = Plugin::$instance->documents->get( $data['editor_post_id'] );

		if ( $document ) {
			return add_query_arg( 'elementor-mode', 'safe', $document->get_edit_url() );
		}

		return false;
	}

	public function enable_safe_mode() {
		WP_Filesystem();

		$allowed_plugins = [
			'elementor' => ELEMENTOR_PLUGIN_BASE,
		];

		if ( defined( 'ELEMENTOR_PRO_PLUGIN_BASE' ) ) {
			$allowed_plugins['elementor_pro'] = ELEMENTOR_PRO_PLUGIN_BASE;
		}

		add_option( 'elementor_safe_mode_allowed_plugins', $allowed_plugins );

		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			wp_mkdir_p( WPMU_PLUGIN_DIR );
			add_option( 'elementor_safe_mode_created_mu_dir', true );
		}

		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			wp_die( __( 'Cannot enable Safe Mode', 'elementor' ) );
		}

		$results = copy_dir( __DIR__ . '/mu-plugin/', WPMU_PLUGIN_DIR );

		if ( is_wp_error( $results ) ) {
			return false;
		}
	}

	public function disable_safe_mode() {
		$file_path = WP_CONTENT_DIR . '/mu-plugins/elementor-safe-mode.php';
		if ( file_exists( $file_path ) ) {
			unlink( $file_path );
		}

		if ( get_option( 'elementor_safe_mode_created_mu_dir' ) ) {
			// It will be removed only if it's empty and don't have other mu-plugins.
			@rmdir( WPMU_PLUGIN_DIR );
		}

		delete_option( 'elementor_safe_mode' );
		delete_option( 'elementor_safe_mode_allowed_plugins' );
		delete_option( 'theme_mods_elementor-safe' );
		delete_option( 'elementor_safe_mode_created_mu_dir' );
	}

	public function filter_preview_url( $url ) {
		return add_query_arg( 'elementor-mode', 'safe', $url );
	}

	public function filter_template() {
		return ELEMENTOR_PATH . 'modules/page-templates/templates/canvas.php';
	}

	public function print_safe_mode_css() {
		?>
		<style>
			.elementor-safe-mode-toast {
				position: absolute;
				z-index: 10000; /* Over the loading layer */
				bottom: 50px;
				right: 50px;
				width: 400px;
				line-height: 30px;
				background: white;
				padding: 25px;
				box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
				border-radius: 5px;
				font-family: Roboto, Arial, Helvetica, Verdana, sans-serif;
			}

			#elementor-try-safe-mode {
				display: none;
			}

			.elementor-safe-mode-toast .elementor-toast-content {
				font-size: 15px;
				line-height: 22px;
				color: #6D7882;
			}

			.elementor-safe-mode-toast .elementor-toast-content a {
				color: #138FFF;
			}

			.elementor-safe-mode-toast .elementor-toast-content hr {
				margin: 15px auto;
				border: 0 none;
				border-top: 1px solid #F1F3F5;
			}

			.elementor-safe-mode-toast header {
				display: flex;
				align-items: center;
				margin-bottom: 15px;
			}

			.elementor-safe-mode-toast .elementor-safe-mode-button {
				display: inline-block;
				font-weight: 500;
				font-size: 12.5px;
				text-transform: uppercase;
				color: white;
				line-height: 33px;
				background: #A4AFB7;
				border-radius: 3px;
				padding: 0 15px;
			}

			#elementor-try-safe-mode .elementor-safe-mode-button {
				background: #39B54A;
			}

			body:not(.rtl) .elementor-safe-mode-toast .elementor-safe-mode-button {
				margin-left: auto;
			}

			body.rtl .elementor-safe-mode-toast .elementor-safe-mode-button {
				margin-right: auto;
			}

			.elementor-safe-mode-toast header i {
				font-size: 25px;
				color: #A4AFB7;
			}

			body:not(.rtl) .elementor-safe-mode-toast header i {
				margin-right: 5px;
			}

			body.rtl .elementor-safe-mode-toast header i {
				margin-left: 5px;
			}

			.elementor-safe-mode-toast header h2 {
				font-size: 18px;
				color: #495157;
			}
		</style>
		<?php
	}

	public function print_safe_mode_notice() {
		echo $this->print_safe_mode_css();
		?>
		<div class="elementor-safe-mode-toast" id="elementor-safe-mode-message">
			<header>
				<i class="eicon-warning"></i>
				<h2><?php echo __( 'Safe Mode ON', 'elementor' ); ?></h2>
				<a class="elementor-safe-mode-button elementor-disable-safe-mode" target="_blank" href="<?php echo $this->get_admin_page_url(); ?>">
					<?php echo __( 'Disable Safe Mode', 'elementor' ); ?>
				</a>
			</header>

			<div class="elementor-toast-content">
				<p>
					<?php echo __( 'Safe Mode has been activated.', 'elementor' ); ?>
				</p>
				<hr>
				<p>
					<?php printf( __( 'Editor loaded successfully? The issue was probably caused by one of your plugins or theme. <a href="%s" target="_blank">Click here</a> to troubleshoot', 'elementor' ), self::DOCS_HELPED_URL ); ?>
				</p>
				<hr>
				<p>
					<?php printf( __( 'Still having loading issues? <a href="%s" target="_blank">Click here</a> to troubleshoot', 'elementor' ), self::DOCS_DIDNT_HELP_URL ); ?>
				</p>
				<?php
				$mu_plugins = wp_get_mu_plugins();
				if ( 1 < count( $mu_plugins ) ) : ?>
					<hr>
					<p>
						<?php printf( __( 'Please note! We couldn\'t deactivate all of your plugins on Safe Mode. Please <a href="%s" target="_blank">read more</a> about this issue.', 'elementor' ), self::DOCS_MU_PLUGINS_URL ); ?>
					</p>
					<?php endif; ?>
			</div>
		</div>

		<script>
			var ElementorSafeMode = function() {
				var attachEvents = function() {
					jQuery( '.elementor-disable-safe-mode' ).on( 'click', function( e ) {
						if ( ! elementorCommon || ! elementorCommon.ajax ) {
							return;
						}

						e.preventDefault();

						elementorCommon.ajax.addRequest(
							'disable_safe_mode', {
								success: function() {
									if ( -1 === location.href.indexOf( 'elementor-mode=safe' ) ) {
										location.reload();
									} else {
										// Need to remove the URL from browser history.
										location.replace( location.href.replace( '&elementor-mode=safe', '' ) );
									}
								},
								error: function() {
									alert( 'An error occurred' );
								},
							},
							true
						);
					} );
				};

				var init = function() {
					attachEvents();
				};

				init();
			};

			new ElementorSafeMode();
		</script>
		<?php
	}

	public function print_try_safe_mode() {
		echo $this->print_safe_mode_css();
		?>
		<div class="elementor-safe-mode-toast" id="elementor-try-safe-mode">
			<header>
				<i class="eicon-warning"></i>
				<h2><?php echo __( 'Can\'t Edit?', 'elementor' ); ?></h2>
				<a class="elementor-safe-mode-button elementor-enable-safe-mode" target="_blank" href="<?php echo $this->get_admin_page_url(); ?>">
					<?php echo __( 'Enable Safe Mode', 'elementor' ); ?>
				</a>
			</header>
			<div class="elementor-toast-content">
				<?php printf( __( 'There’s a problem loading Elementor? Please enable Safe Mode to troubleshoot the problem. <a href="%1$s" target="_blank">%2$s.</a>', 'elementor' ), self::DOCS_TRY_SAFE_MODE_URL, __( 'Learn More', 'elementor' ) ); ?>
			</div>
		</div>

		<script>
			var ElementorTrySafeMode = function() {
				var attachEvents = function() {
					jQuery( '.elementor-enable-safe-mode' ).on( 'click', function( e ) {
						if ( ! elementorCommon || ! elementorCommon.ajax ) {
							return;
						}

						e.preventDefault();

						elementorCommon.ajax.addRequest(
							'enable_safe_mode', {
								data: {
									editor_post_id: '<?php echo Plugin::$instance->editor->get_post_id(); ?>',
								},
								success: function( url ) {
									location.assign( url );
								},
								error: function() {
									alert( 'An error occurred' );
								},
							},
							true
						);
					} );
				};

				var isElementorLoaded = function() {

					if ( 'undefined' === typeof elementor || ! elementor.$preview || ! elementor.$preview[ 0 ] ) {
						return false;
					}

					var previewWindow = elementor.$preview[0].contentWindow;

					if ( ! previewWindow.elementorFrontend ) {
						return false;
					}

					if ( ! elementor.$previewElementorEl.length ) {
						return false;
					}

					return true;
				};

				var showTrySafeModeNotice = function() {
					if ( ! isElementorLoaded() ) {
						jQuery( '#elementor-try-safe-mode' ).show();
					}
				};

				var init = function() {
					setTimeout( showTrySafeModeNotice, 7000 );

					attachEvents();
				};

				init();
			};

			new ElementorTrySafeMode();
		</script>

		<?php
	}

	public function run_safe_mode() {
		remove_action( 'elementor/editor/footer', [ $this, 'print_try_safe_mode' ] );

		// Avoid notices like for comment.php.
		add_filter( 'deprecated_file_trigger_error', '__return_false' );

		add_filter( 'template_include', [ $this, 'filter_template' ], 999 );
		add_filter( 'elementor/document/urls/preview', [ $this, 'filter_preview_url' ] );
		add_action( 'elementor/editor/footer', [ $this, 'print_safe_mode_notice' ] );
	}

	private function is_enabled() {
		return get_option( self::OPTION_ENABLED, '' );
	}

	private function get_admin_page_url() {
		// A fallback URL if the Js doesn't work.
		return Tools::get_url();
	}

	public function plugin_action_links( $actions ) {
		$actions['disable'] = '<a href="' . self::get_admin_page_url() . '">' . __( 'Disable Safe Mode', 'elementor' ) . '</a>';

		return $actions;
	}

	public function __construct() {
		add_action( 'elementor/admin/after_create_settings/elementor-tools', [ $this, 'add_admin_button' ] );
		add_action( 'elementor/ajax/register_actions', [ $this, 'register_ajax_actions' ] );

		$plugin_file = self::MU_PLUGIN_FILE_NAME;
		add_filter( "plugin_action_links_{$plugin_file}", [ $this, 'plugin_action_links' ] );

		// Use pre_update, in order to catch cases that $value === $old_value and it not updated.
		add_filter( 'pre_update_option_elementor_safe_mode', [ $this, 'on_update_safe_mode' ], 10, 2 );
		add_action( 'add_option_elementor_safe_mode', [ $this, 'on_add_safe_mode' ], 10, 2 );

		add_action( 'elementor/safe_mode/init', [ $this, 'run_safe_mode' ] );
		add_action( 'elementor/editor/footer', [ $this, 'print_try_safe_mode' ] );
	}
}