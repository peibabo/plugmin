<?php
/**
 * Quick Cache Plugin
 *
 * @package quick_cache\plugin
 * @since 140422 First documented version.
 * @copyright WebSharks, Inc. <http://www.websharks-inc.com>
 * @license GNU General Public License, version 2
 */
namespace quick_cache
{
	if(!defined('WPINC')) // MUST have WordPress.
		exit('Do NOT access this file directly: '.basename(__FILE__));

	require_once dirname(__FILE__).'/includes/share.php';

	if(!class_exists('\\'.__NAMESPACE__.'\\plugin'))
	{
		/**
		 * Quick Cache Plugin
		 *
		 * @package quick_cache\plugin
		 * @since 140422 First documented version.
		 */
		class plugin extends share
		{
			/**
			 * Stub `__FILE__` location.
			 *
			 * @since 140422 First documented version.
			 *
			 * @var string Current `__FILE__` from the stub; NOT from this file.
			 *    Note that Quick Cache has a stub loader that checks for PHP v5.3 compat;
			 *    which is why we have this property. This is the stub `__FILE__`.
			 */
			public $file = '';

			/**
			 * An array of all default option values.
			 *
			 * @since 140422 First documented version.
			 *
			 * @var array Default options array; set by constructor.
			 */
			public $default_options = array();

			/**
			 * Configured option values.
			 *
			 * @since 140422 First documented version.
			 *
			 * @var array Options configured by site owner; set by constructor.
			 */
			public $options = array();

			/**
			 * General capability requirement.
			 *
			 * @since 140422 First documented version.
			 *
			 * @var string WordPress capability required to
			 *    administer QC in any environment; i.e. in multisite or otherwise.
			 */
			public $cap = '';

			/**
			 * Network capability requirement.
			 *
			 * @since 140422 First documented version.
			 *
			 * @var string WordPress capability required to
			 *    administer QC in a multisite network.
			 */
			public $network_cap = '';

			/**
			 * Cache directory.
			 *
			 * @since 140605 Moving to a base directory.
			 *
			 * @var string Cache directory; relative to the configured base directory.
			 */
			public $cache_sub_dir = 'cache';

			/**
			 * Used by methods in this class to help optimize performance.
			 *
			 * @since 140725 Reducing auto-purge overhead.
			 *
			 * @var array An instance-based cache used by methods in this class.
			 */
			public $cache = array();

			/**
			 * Quick Cache plugin constructor.
			 *
			 * @since 140422 First documented version.
			 */
			public function __construct()
			{
				parent::__construct(); // Shared constructor.

				$this->file = preg_replace('/\.inc\.php$/', '.php', __FILE__);

				add_action('after_setup_theme', array($this, 'setup'));
				register_activation_hook($this->file, array($this, 'activate'));
				register_deactivation_hook($this->file, array($this, 'deactivate'));
			}

			/**
			 * Setup the Quick Cache plugin.
			 *
			 * @since 140422 First documented version.
			 */
			public function setup()
			{
				do_action('before__'.__METHOD__, get_defined_vars());

				load_plugin_textdomain($this->text_domain);

				$this->default_options = array(
					'version'                          => $this->version,

					'crons_setup'                      => '0', // `0` or timestamp.

					'enable'                           => '0', // `0|1`.
					'debugging_enable'                 => '1', // `0|1|2` // 2 indicates greater debugging detail.
					'cache_purge_home_page_enable'     => '1', // `0|1`.
					'cache_purge_posts_page_enable'    => '1', // `0|1`.
					'cache_purge_author_page_enable'   => '1', // `0|1`.
					'cache_purge_term_category_enable' => '1', // `0|1`.
					'cache_purge_term_post_tag_enable' => '1', // `0|1`.
					'cache_purge_term_other_enable'    => '0', // `0|1`.
					'allow_browser_cache'              => '0', // `0|1`.

					'base_dir'                         => 'cache/quick-cache', // Relative to `WP_CONTENT_DIR`.
					'cache_max_age'                    => '7 days', // `strtotime()` compatible.

					'get_requests'                     => '0', // `0|1`.
					'feeds_enable'                     => '0', // `0|1`.
					'cache_404_requests'               => '0', // `0|1`.

					'uninstall_on_deactivation'        => '0' // `0|1`.
				); // Default options are merged with those defined by the site owner.
				$options               = (is_array($options = get_option(__NAMESPACE__.'_options'))) ? $options : array();
				if(is_multisite() && is_array($site_options = get_site_option(__NAMESPACE__.'_options')))
					$options = array_merge($options, $site_options); // Multisite network options.

				if(!$options && get_option('ws_plugin__qcache_configured')
				   && is_array($old_options = get_option('ws_plugin__qcache_options')) && $old_options
				) // Before the rewrite. Only if QC was previously configured w/ options.
				{
					$this->options['version'] = '2.3.6'; // Old options.

					if(!isset($options['enable']) && isset($old_options['enabled']))
						$options['enable'] = (string)(integer)$old_options['enabled'];

					if(!isset($options['debugging_enable']) && isset($old_options['enable_debugging']))
						$options['debugging_enable'] = (string)(integer)$old_options['enable_debugging'];

					if(!isset($options['allow_browser_cache']) && isset($old_options['allow_browser_cache']))
						$options['allow_browser_cache'] = (string)(integer)$old_options['allow_browser_cache'];

					if(!isset($options['when_logged_in']) && isset($old_options['dont_cache_when_logged_in']))
						$options['when_logged_in'] = ((string)(integer)$old_options['dont_cache_when_logged_in']) ? '0' : '1';

					if(!isset($options['get_requests']) && isset($old_options['dont_cache_query_string_requests']))
						$options['get_requests'] = ((string)(integer)$old_options['dont_cache_query_string_requests']) ? '0' : '1';

					if(!isset($options['exclude_uris']) && isset($old_options['dont_cache_these_uris']))
						$options['exclude_uris'] = (string)$old_options['dont_cache_these_uris'];

					if(!isset($options['exclude_refs']) && isset($old_options['dont_cache_these_refs']))
						$options['exclude_refs'] = (string)$old_options['dont_cache_these_refs'];

					if(!isset($options['exclude_agents']) && isset($old_options['dont_cache_these_agents']))
						$options['exclude_agents'] = (string)$old_options['dont_cache_these_agents'];

					if(!isset($options['version_salt']) && isset($old_options['version_salt']))
						$options['version_salt'] = (string)$old_options['version_salt'];
				}
				$this->default_options = apply_filters(__METHOD__.'__default_options', $this->default_options, get_defined_vars());
				$this->options         = array_merge($this->default_options, $options); // This considers old options also.
				$this->options         = apply_filters(__METHOD__.'__options', $this->options, get_defined_vars());

				$this->options['base_dir'] = trim($this->options['base_dir'], '\\/'." \t\n\r\0\x0B");
				if(!$this->options['base_dir']) // Security enhancement; NEVER allow this to be empty.
					$this->options['base_dir'] = $this->default_options['base_dir'];

				$this->cap         = apply_filters(__METHOD__.'__cap', 'activate_plugins');
				$this->network_cap = apply_filters(__METHOD__.'__network_cap', 'manage_network_plugins');

				add_action('init', array($this, 'check_advanced_cache'));
				add_action('init', array($this, 'check_blog_paths'));
				add_action('wp_loaded', array($this, 'actions'));

				add_action('admin_init', array($this, 'check_version'));

				add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
				add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

				add_action('all_admin_notices', array($this, 'all_admin_notices'));
				add_action('all_admin_notices', array($this, 'all_admin_errors'));

				add_action('network_admin_menu', array($this, 'add_network_menu_pages'));
				add_action('admin_menu', array($this, 'add_menu_pages'));

				add_action('switch_theme', array($this, 'auto_clear_cache'));
				add_action('wp_create_nav_menu', array($this, 'auto_clear_cache'));
				add_action('wp_update_nav_menu', array($this, 'auto_clear_cache'));
				add_action('wp_delete_nav_menu', array($this, 'auto_clear_cache'));

				add_action('save_post', array($this, 'auto_purge_post_cache'));
				add_action('delete_post', array($this, 'auto_purge_post_cache'));
				add_action('clean_post_cache', array($this, 'auto_purge_post_cache'));
				add_action('post_updated', array($this, 'auto_purge_author_page_cache'), 10, 3);
				add_action('transition_post_status', array($this, 'auto_purge_post_cache_transition'), 10, 3);

				add_action('added_term_relationship', array($this, 'auto_purge_post_terms_cache'), 10, 1);
				add_action('delete_term_relationships', array($this, 'auto_purge_post_terms_cache'), 10, 1);

				add_action('trackback_post', array($this, 'auto_purge_comment_post_cache'));
				add_action('pingback_post', array($this, 'auto_purge_comment_post_cache'));
				add_action('comment_post', array($this, 'auto_purge_comment_post_cache'));
				add_action('transition_comment_status', array($this, 'auto_purge_comment_transition'), 10, 3);

				add_action('create_term', array($this, 'auto_clear_cache'));
				add_action('edit_terms', array($this, 'auto_clear_cache'));
				add_action('delete_term', array($this, 'auto_clear_cache'));

				add_action('add_link', array($this, 'auto_clear_cache'));
				add_action('edit_link', array($this, 'auto_clear_cache'));
				add_action('delete_link', array($this, 'auto_clear_cache'));

				add_filter('enable_live_network_counts', array($this, 'update_blog_paths'));

				add_filter('plugin_action_links_'.plugin_basename($this->file), array($this, 'add_settings_link'));

				add_filter('cron_schedules', array($this, 'extend_cron_schedules'));
				if((integer)$this->options['crons_setup'] < 1382523750)
				{
					wp_clear_scheduled_hook('_cron_'.__NAMESPACE__.'_cleanup');
					wp_schedule_event(time() + 60, 'daily', '_cron_'.__NAMESPACE__.'_cleanup');

					$this->options['crons_setup'] = (string)time();
					update_option(__NAMESPACE__.'_options', $this->options); // Blog-specific.
					if(is_multisite()) update_site_option(__NAMESPACE__.'_options', $this->options);
				}
				add_action('_cron_'.__NAMESPACE__.'_cleanup', array($this, 'purge_cache'));

				do_action('after__'.__METHOD__, get_defined_vars());
				do_action(__METHOD__.'_complete', get_defined_vars());
			}

			/**
			 * WordPress database instance.
			 *
			 * @since 140422 First documented version.
			 *
			 * @return \wpdb Reference for IDEs.
			 */
			public function wpdb() // Shortcut for other routines.
			{
				return $GLOBALS['wpdb'];
			}

			/**
			 * Plugin activation hook.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to {@link \register_activation_hook()}
			 */
			public function activate()
			{
				$this->setup(); // Setup routines.

				if(!$this->options['enable'])
					return; // Nothing to do.

				$this->add_wp_cache_to_wp_config();
				$this->add_advanced_cache();
				$this->update_blog_paths();
				$this->auto_clear_cache();
			}

			/**
			 * Check current plugin version that installed in WP.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to `admin_init` hook.
			 */
			public function check_version()
			{
				$current_version = $prev_version = $this->options['version'];
				if(version_compare($current_version, $this->version, '>='))
					return; // Nothing to do; we've already upgraded them.

				$current_version = $this->options['version'] = $this->version;
				update_option(__NAMESPACE__.'_options', $this->options); // Updates version.
				if(is_multisite()) update_site_option(__NAMESPACE__.'_options', $this->options);

				require_once dirname(__FILE__).'/includes/version-specific-upgrade.php';
				new version_specific_upgrade($prev_version);

				if($this->options['enable']) // Recompile.
				{
					$this->add_wp_cache_to_wp_config();
					$this->add_advanced_cache();
					$this->update_blog_paths();
				}
				$this->wipe_cache(); // Always wipe the cache; no exceptions.

				$this->enqueue_notice(__('<strong>Quick Cache:</strong> detected a new version of itself. Recompiling w/ latest version... wiping the cache... all done :-)', $this->text_domain), '', TRUE);
			}

			/**
			 * Plugin deactivation hook.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to {@link \register_deactivation_hook()}
			 */
			public function deactivate()
			{
				$this->remove_wp_cache_from_wp_config();
				$this->remove_advanced_cache();
				$this->clear_cache();

				if(!$this->options['uninstall_on_deactivation'])
					return; // Nothing to do here.

				$this->delete_advanced_cache();

				delete_option(__NAMESPACE__.'_options');
				if(is_multisite()) // Delete network options too.
					delete_site_option(__NAMESPACE__.'_options');

				delete_option(__NAMESPACE__.'_notices');
				delete_option(__NAMESPACE__.'_errors');

				wp_clear_scheduled_hook('_cron_'.__NAMESPACE__.'_cleanup');
			}

			/**
			 * Current request is for a pro version preview?
			 *
			 * @since 140422 First documented version.
			 *
			 * @return boolean TRUE if the current request is for a pro preview.
			 */
			public function is_pro_preview()
			{
				static $is;
				if(isset($is)) return $is;

				if(!empty($_REQUEST[__NAMESPACE__.'_pro_preview']))
					return ($is = TRUE);

				return ($is = FALSE);
			}

			/**
			 * URL to a Quick Cache plugin file.
			 *
			 * @since 140422 First documented version.
			 *
			 * @param string $file Optional file path; relative to plugin directory.
			 * @param string $scheme Optional URL scheme; defaults to the current scheme.
			 *
			 * @return string URL to plugin directory; or to the specified `$file` if applicable.
			 */
			public function url($file = '', $scheme = '')
			{
				if(!isset(static::$static[__FUNCTION__]['plugin_dir']))
					static::$static[__FUNCTION__]['plugin_dir'] = rtrim(plugin_dir_url($this->file), '/');
				$plugin_dir =& static::$static[__FUNCTION__]['plugin_dir'];

				$url = $plugin_dir.(string)$file;

				if($scheme) // A specific URL scheme?
					$url = set_url_scheme($url, (string)$scheme);

				return apply_filters(__METHOD__, $url, get_defined_vars());
			}

			/**
			 * Plugin action handler.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to `wp_loaded` hook.
			 */
			public function actions()
			{
				if(!empty($_REQUEST[__NAMESPACE__]))
					require_once dirname(__FILE__).'/includes/actions.php';
			}

			/**
			 * Adds CSS for administrative menu pages.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to `admin_enqueue_scripts` hook.
			 */
			public function enqueue_admin_styles()
			{
				if(empty($_GET['page']) || strpos($_GET['page'], __NAMESPACE__) !== 0)
					return; // Nothing to do; NOT a plugin page in the administrative area.

				$deps = array(); // Plugin dependencies.

				wp_enqueue_style(__NAMESPACE__, $this->url('/client-s/css/menu-pages.min.css'), $deps, $this->version, 'all');
			}

			/**
			 * Adds JS for administrative menu pages.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to `admin_enqueue_scripts` hook.
			 */
			public function enqueue_admin_scripts()
			{
				if(empty($_GET['page']) || strpos($_GET['page'], __NAMESPACE__) !== 0)
					return; // Nothing to do; NOT a plugin page in the administrative area.

				$deps = array('jquery'); // Plugin dependencies.

				wp_enqueue_script(__NAMESPACE__, $this->url('/client-s/js/menu-pages.min.js'), $deps, $this->version, TRUE);
			}

			/**
			 * Creates network admin menu pages.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to `network_admin_menu` hook.
			 */
			public function add_network_menu_pages()
			{
				add_menu_page(__('Quick Cache', $this->text_domain), __('Quick Cache', $this->text_domain),
				              $this->network_cap, __NAMESPACE__, array($this, 'menu_page_options'),
				              $this->url('/client-s/images/menu-icon.png'));
			}

			/**
			 * Creates admin menu pages.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to `admin_menu` hook.
			 */
			public function add_menu_pages()
			{
				if(is_multisite()) return; // Multisite networks MUST use network admin area.

				add_menu_page(__('Quick Cache', $this->text_domain), __('Quick Cache', $this->text_domain),
				              $this->cap, __NAMESPACE__, array($this, 'menu_page_options'),
				              $this->url('/client-s/images/menu-icon.png'));
			}

			/**
			 * Adds link(s) to Quick Cache row on the WP plugins page.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to `plugin_action_links_'.plugin_basename($this->file)` filter.
			 *
			 * @param array $links An array of the existing links provided by WordPress.
			 *
			 * @return array Revised array of links.
			 */
			public function add_settings_link($links)
			{
				$links[] = '<a href="options-general.php?page='.urlencode(__NAMESPACE__).'">'.__('Settings', $this->text_domain).'</a>';
				$links[] = '<br/><a href="'.esc_attr(add_query_arg(urlencode_deep(array('page' => __NAMESPACE__, __NAMESPACE__.'_pro_preview' => '1')), self_admin_url('/admin.php'))).'">'.__('Preview Pro Features', $this->text_domain).'</a>';
				$links[] = '<a href="'.esc_attr('http://www.websharks-inc.com/product/'.str_replace('_', '-', __NAMESPACE__).'/').'" target="_blank">'.__('Upgrade', $this->text_domain).'</a>';

				return apply_filters(__METHOD__, $links, get_defined_vars());
			}

			/**
			 * Loads the admin menu page options.
			 *
			 * @since 140422 First documented version.
			 *
			 * @see add_network_menu_pages()
			 * @see add_menu_pages()
			 */
			public function menu_page_options()
			{
				require_once dirname(__FILE__).'/includes/menu-pages.php';
				$menu_pages = new menu_pages();
				$menu_pages->options();
			}

			/**
			 * Render admin notices; across all admin dashboard views.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to `all_admin_notices` hook.
			 */
			public function all_admin_notices()
			{
				if(($notices = (is_array($notices = get_option(__NAMESPACE__.'_notices'))) ? $notices : array()))
				{
					$notices = $updated_notices = array_unique($notices); // De-dupe.

					foreach(array_keys($updated_notices) as $_key) if(strpos($_key, 'persistent-') !== 0)
						unset($updated_notices[$_key]); // Leave persistent notices; ditch others.
					unset($_key); // Housekeeping after updating notices.

					update_option(__NAMESPACE__.'_notices', $updated_notices);
				}
				if(current_user_can($this->cap)) foreach($notices as $_key => $_notice)
				{
					$_dismiss = ''; // Initialize empty string; e.g. reset value on each pass.
					if(strpos($_key, 'persistent-') === 0) // A dismissal link is needed in this case?
					{
						$_dismiss_css = 'display:inline-block; float:right; margin:0 0 0 15px; text-decoration:none; font-weight:bold;';
						$_dismiss     = add_query_arg(urlencode_deep(array(__NAMESPACE__ => array('dismiss_notice' => array('key' => $_key)), '_wpnonce' => wp_create_nonce())));
						$_dismiss     = '<a style="'.esc_attr($_dismiss_css).'" href="'.esc_attr($_dismiss).'">'.__('dismiss &times;', $this->text_domain).'</a>';
					}
					echo apply_filters(__METHOD__.'__notice', '<div class="updated"><p>'.$_notice.$_dismiss.'</p></div>', get_defined_vars());
				}
				unset($_key, $_notice, $_dismiss_css, $_dismiss); // Housekeeping.
			}

			/**
			 * Enqueue an administrative notice.
			 *
			 * @since 140605 Adding enqueue notice/error methods.
			 *
			 * @param string  $notice HTML markup containing the notice itself.
			 *
			 * @param string  $persistent_key Optional. A unique key which identifies a particular type of persistent notice.
			 *    This defaults to an empty string. If this is passed, the notice is persistent; i.e. it continues to be displayed until dismissed by the site owner.
			 *
			 * @param boolean $push_to_top Optional. Defaults to a `FALSE` value.
			 *    If `TRUE`, the notice is pushed to the top of the stack; i.e. displayed above any others.
			 */
			public function enqueue_notice($notice, $persistent_key = '', $push_to_top = FALSE)
			{
				$notice         = (string)$notice;
				$persistent_key = (string)$persistent_key;

				$notices = get_option(__NAMESPACE__.'_notices');
				if(!is_array($notices)) $notices = array();

				if($persistent_key) // A persistent notice?
				{
					if(strpos($persistent_key, 'persistent-') !== 0)
						$persistent_key = 'persistent-'.$persistent_key;

					if($push_to_top) // Push this notice to the top?
						$notices = array($persistent_key => $notice) + $notices;
					else $notices[$persistent_key] = $notice;
				}
				else if($push_to_top) // Push to the top?
					array_unshift($notices, $notice);

				else $notices[] = $notice; // Default behavior.

				update_option(__NAMESPACE__.'_notices', $notices);
			}

			/**
			 * Render admin errors; across all admin dashboard views.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to `all_admin_notices` hook.
			 */
			public function all_admin_errors()
			{
				if(($errors = (is_array($errors = get_option(__NAMESPACE__.'_errors'))) ? $errors : array()))
				{
					$errors = $updated_errors = array_unique($errors); // De-dupe.

					foreach(array_keys($updated_errors) as $_key) if(strpos($_key, 'persistent-') !== 0)
						unset($updated_errors[$_key]); // Leave persistent errors; ditch others.
					unset($_key); // Housekeeping after updating notices.

					update_option(__NAMESPACE__.'_errors', $updated_errors);
				}
				if(current_user_can($this->cap)) foreach($errors as $_key => $_error)
				{
					$_dismiss = ''; // Initialize empty string; e.g. reset value on each pass.
					if(strpos($_key, 'persistent-') === 0) // A dismissal link is needed in this case?
					{
						$_dismiss_css = 'display:inline-block; float:right; margin:0 0 0 15px; text-decoration:none; font-weight:bold;';
						$_dismiss     = add_query_arg(urlencode_deep(array(__NAMESPACE__ => array('dismiss_error' => array('key' => $_key)), '_wpnonce' => wp_create_nonce())));
						$_dismiss     = '<a style="'.esc_attr($_dismiss_css).'" href="'.esc_attr($_dismiss).'">'.__('dismiss &times;', $this->text_domain).'</a>';
					}
					echo apply_filters(__METHOD__.'__error', '<div class="error"><p>'.$_error.$_dismiss.'</p></div>', get_defined_vars());
				}
				unset($_key, $_error, $_dismiss_css, $_dismiss); // Housekeeping.
			}

			/**
			 * Enqueue an administrative error.
			 *
			 * @since 140605 Adding enqueue notice/error methods.
			 *
			 * @param string  $error HTML markup containing the error itself.
			 *
			 * @param string  $persistent_key Optional. A unique key which identifies a particular type of persistent error.
			 *    This defaults to an empty string. If this is passed, the error is persistent; i.e. it continues to be displayed until dismissed by the site owner.
			 *
			 * @param boolean $push_to_top Optional. Defaults to a `FALSE` value.
			 *    If `TRUE`, the error is pushed to the top of the stack; i.e. displayed above any others.
			 */
			public function enqueue_error($error, $persistent_key = '', $push_to_top = FALSE)
			{
				$error          = (string)$error;
				$persistent_key = (string)$persistent_key;

				$errors = get_option(__NAMESPACE__.'_errors');
				if(!is_array($errors)) $errors = array();

				if($persistent_key) // A persistent notice?
				{
					if(strpos($persistent_key, 'persistent-') !== 0)
						$persistent_key = 'persistent-'.$persistent_key;

					if($push_to_top) // Push this notice to the top?
						$errors = array($persistent_key => $error) + $errors;
					else $errors[$persistent_key] = $error;
				}
				else if($push_to_top) // Push to the top?
					array_unshift($errors, $error);

				else $errors[] = $error; // Default behavior.

				update_option(__NAMESPACE__.'_errors', $errors);
			}

			/**
			 * Extends WP-Cron schedules.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to `cron_schedules` filter.
			 *
			 * @param array $schedules An array of the current schedules.
			 *
			 * @return array Revised array of WP-Cron schedules.
			 */
			public function extend_cron_schedules($schedules)
			{
				$schedules['every15m'] = array('interval' => 900, 'display' => __('Every 15 Minutes', $this->text_domain));

				return apply_filters(__METHOD__, $schedules, get_defined_vars());
			}

			/**
			 * Wipes out all cache files in the cache directory.
			 *
			 * @since 140422 First documented version.
			 *
			 * @param boolean $manually Defaults to a `FALSE` value.
			 *    Pass as TRUE if the wipe is done manually by the site owner.
			 *
			 * @param string  $also_wipe_dir Defaults to an empty string.
			 *    By default (i.e. when this is empty) we only wipe {@link $cache_sub_dir} files.
			 *    WARNING: if this is passed, EVERYTHING inside this directory is deleted recursively;
			 *       in addition to deleting all of the {@link $cache_sub_dir} files.
			 *
			 * @return integer Total files wiped by this routine (if any).
			 *
			 * @throws \exception If a wipe failure occurs.
			 */
			public function wipe_cache($manually = FALSE, $also_wipe_dir = '')
			{
				$counter = 0; // Initialize.

				// @TODO When set_time_limit() is disabled by PHP configuration, display a warning message to users upon plugin activation.
				@set_time_limit(1800); // In case of HUGE sites w/ a very large directory. Errors are ignored in case `set_time_limit()` is disabled.

				/** @var $_dir_file \RecursiveDirectoryIterator For IDEs. */
				if(is_dir($cache_dir = $this->cache_dir())) foreach($this->dir_regex_iteration($cache_dir, '/.+/') as $_dir_file)
				{
					if(($_dir_file->isFile() || $_dir_file->isLink()) && strpos($_dir_file->getSubPathname(), '/') !== FALSE)
						// Don't delete files in the immediate directory; e.g. `qc-advanced-cache` or `.htaccess`, etc.
						// Actual `http|https/...` cache files are nested. Files in the immediate directory are for other purposes.
						if(!unlink($_dir_file->getPathname())) // Throw exception if unable to delete.
							throw new \exception(sprintf(__('Unable to wipe file: `%1$s`.', $this->text_domain), $_dir_file->getPathname()));
						else $counter++; // Increment counter for each file we wipe.

					else if($_dir_file->isDir()) // Directories are last in the iteration.
						if(!rmdir($_dir_file->getPathname())) // Throw exception if unable to delete.
							throw new \exception(sprintf(__('Unable to wipe dir: `%1$s`.', $this->text_domain), $_dir_file->getPathname()));
				}
				unset($_dir_file); // Just a little housekeeping.

				/** @var $_dir_file \RecursiveDirectoryIterator For IDEs. */
				if($also_wipe_dir && is_dir($also_wipe_dir)) foreach($this->dir_regex_iteration($also_wipe_dir, '/.+/') as $_dir_file)
				{
					if(($_dir_file->isFile() || $_dir_file->isLink()))
						if(!unlink($_dir_file->getPathname())) // Throw exception if unable to delete.
							throw new \exception(sprintf(__('Unable to also wipe file: `%1$s`.', $this->text_domain), $_dir_file->getPathname()));
						else $counter++; // Increment counter for each file we wipe.

					else if($_dir_file->isDir())
						if(!rmdir($_dir_file->getPathname())) // Throw exception if unable to delete.
							throw new \exception(sprintf(__('Unable to also wipe dir: `%1$s`.', $this->text_domain), $_dir_file->getPathname()));
				}
				unset($_dir_file); // Just a little housekeeping.

				return apply_filters(__METHOD__, $counter, get_defined_vars());
			}

			/**
			 * Clears cache files for the current blog.
			 *
			 * @since 140422 First documented version.
			 *
			 * @param boolean $manually Defaults to a `FALSE` value.
			 *    Pass as TRUE if the clearing is done manually by the site owner.
			 *
			 * @return integer Total files cleared by this routine (if any).
			 *
			 * @throws \exception If a clearing failure occurs.
			 */
			public function clear_cache($manually = FALSE)
			{
				$counter = 0; // Initialize.

				if(!is_dir($cache_dir = $this->cache_dir()))
					return $counter; // Nothing to do.

				$url                          = 'http://'.$_SERVER['HTTP_HOST'].$this->host_base_dir_tokens();
				$cache_path_no_scheme_quv_ext = $this->build_cache_path($url, '', '', $this::CACHE_PATH_NO_SCHEME | $this::CACHE_PATH_NO_PATH_INDEX | $this::CACHE_PATH_NO_QUV | $this::CACHE_PATH_NO_EXT);
				$regex                        = '/^'.preg_quote($cache_dir, '/'). // Consider all schemes; all paths; and all possible variations.
				                                '\/[^\/]+\/'.preg_quote($cache_path_no_scheme_quv_ext, '/').
				                                '(?:\/index)?[.\/]/';

				// @TODO When set_time_limit() is disabled by PHP configuration, display a warning message to users upon plugin activation
				@set_time_limit(1800); // In case of HUGE sites w/ a very large directory. Errors are ignored in case `set_time_limit()` is disabled.

				/** @var $_dir_file \RecursiveDirectoryIterator For IDEs. */
				foreach($this->dir_regex_iteration($cache_dir, $regex) as $_dir_file)
				{
					if(($_dir_file->isFile() || $_dir_file->isLink()) && strpos($_dir_file->getSubpathname(), '/') !== FALSE)
						// Don't delete files in the immediate directory; e.g. `qc-advanced-cache` or `.htaccess`, etc.
						// Actual `http|https/...` cache files are nested. Files in the immediate directory are for other purposes.
						if(!unlink($_dir_file->getPathname())) // Throw exception if unable to delete.
							throw new \exception(sprintf(__('Unable to clear file: `%1$s`.', $this->text_domain), $_dir_file->getPathname()));
						else $counter++; // Increment counter for each file we purge.

					else if($_dir_file->isDir()) // Directories are last in the iteration.
						if(!rmdir($_dir_file->getPathname())) // Throw exception if unable to delete.
							throw new \exception(sprintf(__('Unable to clear dir: `%1$s`.', $this->text_domain), $_dir_file->getPathname()));
				}
				unset($_dir_file); // Just a little housekeeping.

				return apply_filters(__METHOD__, $counter, get_defined_vars());
			}

			/**
			 * Purges expired cache files for the current blog.
			 *
			 * @since 140422 First documented version.
			 *
			 * @return integer Total files purged by this routine (if any).
			 *
			 * @throws \exception If a purge failure occurs.
			 */
			public function purge_cache()
			{
				$counter = 0; // Initialize.

				if(!is_dir($cache_dir = $this->cache_dir()))
					return $counter; // Nothing to do.

				$max_age = strtotime('-'.$this->options['cache_max_age']);

				// @TODO When set_time_limit() is disabled by PHP configuration, display a warning message to users upon plugin activation
				@set_time_limit(1800); // In case of HUGE sites w/ a very large directory. Errors are ignored in case `set_time_limit()` is disabled.

				/** @var $_file \RecursiveDirectoryIterator For IDEs. */
				foreach($this->dir_regex_iteration($cache_dir, '/.+/') as $_file) if($_file->isFile() || $_file->isLink())
				{
					if($_file->getMTime() < $max_age && strpos($_file->getSubpathname(), '/') !== FALSE)
						// Don't delete files in the immediate directory; e.g. `qc-advanced-cache` or `.htaccess`, etc.
						// Actual `http|https/...` cache files are nested. Files in the immediate directory are for other purposes.
						if(!unlink($_file->getPathname())) // Throw exception if unable to delete.
							throw new \exception(sprintf(__('Unable to purge file: `%1$s`.', $this->text_domain), $_file->getPathname()));
						else $counter++; // Increment counter for each file we purge.
				}
				unset($_file); // Just a little housekeeping.

				return apply_filters(__METHOD__, $counter, get_defined_vars());
			}

			/**
			 * Automatically wipes out all cache files in the cache directory.
			 *
			 * @since 140422 First documented version.
			 *
			 * @return integer Total files wiped by this routine (if any).
			 *
			 * @note Unlike many of the other `auto_` methods, this one is NOT currently attached to any hooks.
			 *    This is called upon whenever QC options are saved and/or restored though.
			 */
			public function auto_wipe_cache()
			{
				$counter = 0; // Initialize.

				if(isset($this->cache[__FUNCTION__]))
					return $counter; // Already did this.
				$this->cache[__FUNCTION__] = -1;

				if(!$this->options['enable'])
					return $counter; // Nothing to do.

				$counter = $this->wipe_cache();

				if($counter && is_admin()) // Change notifications cannot be turned off in the lite version.
					$this->enqueue_notice('<img src="'.esc_attr($this->url('/client-s/images/wipe.png')).'" style="float:left; margin:0 10px 0 0; border:0;" />'.
					                      __('<strong>Quick Cache:</strong> detected significant changes. Found cache files (auto-wiping).', $this->text_domain));

				return apply_filters(__METHOD__, $counter, get_defined_vars());
			}

			/**
			 * Automatically clears all cache files for the current blog.
			 *
			 * @attaches-to `switch_theme` hook.
			 *
			 * @attaches-to `wp_create_nav_menu` hook.
			 * @attaches-to `wp_update_nav_menu` hook.
			 * @attaches-to `wp_delete_nav_menu` hook.
			 *
			 * @attaches-to `create_term` hook.
			 * @attaches-to `edit_terms` hook.
			 * @attaches-to `delete_term` hook.
			 *
			 * @attaches-to `add_link` hook.
			 * @attaches-to `edit_link` hook.
			 * @attaches-to `delete_link` hook.
			 *
			 * @since 140422 First documented version.
			 *
			 * @return integer Total files cleared by this routine (if any).
			 *
			 * @note This is also called upon during plugin activation.
			 */
			public function auto_clear_cache()
			{
				$counter = 0; // Initialize.

				if(isset($this->cache[__FUNCTION__]))
					return $counter; // Already did this.
				$this->cache[__FUNCTION__] = -1;

				if(!$this->options['enable'])
					return $counter; // Nothing to do.

				$counter = $this->clear_cache();

				if($counter && is_admin()) // Change notifications cannot be turned off in the lite version.
					$this->enqueue_notice('<img src="'.esc_attr($this->url('/client-s/images/clear.png')).'" style="float:left; margin:0 10px 0 0; border:0;" />'.
					                      __('<strong>Quick Cache:</strong> detected changes. Found cache files for this site (auto-clearing).', $this->text_domain));

				return apply_filters(__METHOD__, $counter, get_defined_vars());
			}

			/**
			 * Automatically purge cache files for a particular post.
			 *
			 * @attaches-to `save_post` hook.
			 * @attaches-to `delete_post` hook.
			 * @attaches-to `clean_post_cache` hook.
			 *
			 * @since 140422 First documented version.
			 *
			 * @param integer $id A WordPress post ID.
			 * @param bool    $force Defaults to a `FALSE` value.
			 *    Pass as TRUE if purge should be done for `draft`, `pending`,
			 *    `future`, or `trash` post statuses.
			 *
			 * @return integer Total files purged by this routine (if any).
			 *
			 * @throws \exception If a purge failure occurs.
			 *
			 * @note This is also called upon by other routines which listen for
			 *    events that are indirectly associated with a post ID.
			 *
			 * @see auto_purge_comment_post_cache()
			 * @see auto_purge_post_cache_transition()
			 */
			public function auto_purge_post_cache($id, $force = FALSE)
			{
				$id = (integer)$id;

				$counter          = 0; // Initialize.
				$enqueued_notices = 0; // Initialize.

				if(isset($this->cache[__FUNCTION__][$id][(integer)$force]))
					return $counter; // Already did this.
				$this->cache[__FUNCTION__][$id][(integer)$force] = -1;

				if(isset(static::$static['___allow_auto_purge_post_cache']) && static::$static['___allow_auto_purge_post_cache'] === FALSE)
				{
					static::$static['___allow_auto_purge_post_cache'] = TRUE; // Reset state.
					return $counter; // Nothing to do.
				}

				if(!$this->options['enable'])
					return $counter; // Nothing to do.

				if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
					return $counter; // Nothing to do.

				if(!is_dir($cache_dir = $this->cache_dir()))
					return $counter; // Nothing to do.

				if(!($permalink = get_permalink($id)))
					return $counter; // Nothing we can do.

				if(!($post_status = get_post_status($id)))
					return $counter; // Nothing to do.

				if($post_status === 'auto-draft')
					return $counter; // Nothing to do.

				if($post_status === 'draft' && !$force)
					return $counter; // Nothing to do.

				if($post_status === 'pending' && !$force)
					return $counter; // Nothing to do.

				if($post_status === 'future' && !$force)
					return $counter; // Nothing to do.

				if($post_status === 'trash' && !$force)
					return $counter; // Nothing to do.

				if(($type = get_post_type($id)) && ($type = get_post_type_object($type)) && !empty($type->labels->singular_name))
					$type_singular_name = $type->labels->singular_name; // Singular name for the post type.
				else $type_singular_name = __('Post', $this->text_domain); // Default value.

				$cache_path_no_scheme_quv_ext = $this->build_cache_path($permalink, '', '', $this::CACHE_PATH_NO_SCHEME | $this::CACHE_PATH_NO_PATH_INDEX | $this::CACHE_PATH_NO_QUV | $this::CACHE_PATH_NO_EXT);
				$regex                        = '/^'.preg_quote($cache_dir, '/'). // Consider all schemes; all path paginations; and all possible variations.
				                                '\/[^\/]+\/'.preg_quote($cache_path_no_scheme_quv_ext, '/').
				                                '(?:\/index)?(?:\.|\/(?:page|comment\-page)\/[0-9]+[.\/])/';

				/** @var $_file \RecursiveDirectoryIterator For IDEs. */
				foreach($this->dir_regex_iteration($cache_dir, $regex) as $_file) if($_file->isFile() || $_file->isLink())
				{
					if(strpos($_file->getSubpathname(), '/') === FALSE) continue;
					// Don't delete files in the immediate directory; e.g. `qc-advanced-cache` or `.htaccess`, etc.
					// Actual `http|https/...` cache files are nested. Files in the immediate directory are for other purposes.

					if(!unlink($_file->getPathname())) // Throw exception if unable to delete.
						throw new \exception(sprintf(__('Unable to auto-purge file: `%1$s`.', $this->text_domain), $_file->getPathname()));
					$counter++; // Increment counter for each file purge.

					if($enqueued_notices || !is_admin())
						continue; // Stop here; we already issued a notice, or this notice is N/A.

					$this->enqueue_notice('<img src="'.esc_attr($this->url('/client-s/images/clear.png')).'" style="float:left; margin:0 10px 0 0; border:0;" />'.
					                      sprintf(__('<strong>Quick Cache:</strong> detected changes. Found cache file(s) for %1$s ID: <code>%2$s</code> (auto-purging).', $this->text_domain), $type_singular_name, $id));
					$enqueued_notices++; // Notice counter.
				}
				unset($_file); // Just a little housekeeping.

				$counter += $this->auto_purge_xml_sitemaps_cache(); // If enabled and necessary.
				$counter += $this->auto_purge_home_page_cache(); // If enabled and necessary.
				$counter += $this->auto_purge_posts_page_cache(); // If enabled and necessary.
				$counter += $this->auto_purge_post_terms_cache($id, $force); // If enabled and necessary.

				return apply_filters(__METHOD__, $counter, get_defined_vars());
			}

			/**
			 * Automatically purge cache files for a particular post when transitioning
			 *    from `publish` or `private` post status to `draft`, `future`, `private`, or `trash`.
			 *
			 * @attaches-to `transition_post_status` hook.
			 *
			 * @since 140605 First documented version.
			 *
			 * @param string   $new_status New post status.
			 * @param string   $old_status Old post status.
			 * @param \WP_Post $post Post object.
			 *
			 * @return integer Total files purged by this routine (if any).
			 *
			 * @throws \exception If a purge failure occurs.
			 *
			 * @note This is also called upon by other routines which listen for
			 *    events that are indirectly associated with a post ID.
			 *
			 * @see auto_purge_post_cache()
			 */
			public function auto_purge_post_cache_transition($new_status, $old_status, \WP_Post $post)
			{
				$new_status = (string)$new_status;
				$old_status = (string)$old_status;

				$counter = 0; // Initialize.

				if(isset($this->cache[__FUNCTION__][$new_status][$old_status][$post->ID]))
					return $counter; // Already did this.
				$this->cache[__FUNCTION__][$new_status][$old_status][$post->ID] = -1;

				if(!$this->options['enable'])
					return $counter; // Nothing to do.

				if($old_status !== 'publish' && $old_status !== 'private')
					return $counter; // Nothing to do. We MUST be transitioning FROM one of these statuses.

				if($new_status === 'draft' || $new_status === 'future' || $new_status === 'private' || $new_status === 'trash')
					$counter = $this->auto_purge_post_cache($post->ID, TRUE);

				return apply_filters(__METHOD__, $counter, get_defined_vars());
			}

			/**
			 * Automatically purges cache files related to XML sitemaps.
			 *
			 * @since 140725 Working to improve compatibility with sitemaps.
			 *
			 * @return integer Total files purged by this routine (if any).
			 *
			 * @throws \exception If a purge failure occurs.
			 *
			 * @note Unlike many of the other `auto_` methods, this one is NOT currently
			 *    attached to any hooks. However, it is called upon by {@link auto_purge_post_cache()}.
			 *
			 * @see auto_purge_post_cache()
			 */
			public function auto_purge_xml_sitemaps_cache()
			{
				$counter          = 0; // Initialize.
				$enqueued_notices = 0; // Initialize.

				if(isset($this->cache[__FUNCTION__]))
					return $counter; // Already did this.
				$this->cache[__FUNCTION__] = -1;

				if(!$this->options['enable'])
					return $counter; // Nothing to do.

				if(!is_dir($cache_dir = $this->cache_dir()))
					return $counter; // Nothing to do.

				$_this                        = $this; // Needed in the closure below.
				$patterns                     = '(?:'.implode('|', array_map(function ($pattern) use ($_this)
					{
						$pattern = $_this->build_cache_path(home_url('/'.trim($pattern, '/')), '', '', // Convert to a cache path w/ possible wildcards.
						                                    $_this::CACHE_PATH_ALLOW_WILDCARDS | $_this::CACHE_PATH_NO_SCHEME | $_this::CACHE_PATH_NO_HOST
						                                    | $_this::CACHE_PATH_NO_PATH_INDEX | $_this::CACHE_PATH_NO_QUV | $_this::CACHE_PATH_NO_EXT);
						return preg_replace('/\\\\\*/', '.*?', preg_quote($pattern, '/')); // Wildcards.

					}, preg_split('/['."\r\n".']+/', '/sitemap*.xml', NULL, PREG_SPLIT_NO_EMPTY))).')';
				$cache_path_no_scheme_quv_ext = $this->build_cache_path(home_url('/'), '', '', $this::CACHE_PATH_NO_SCHEME | $this::CACHE_PATH_NO_PATH_INDEX | $this::CACHE_PATH_NO_QUV | $this::CACHE_PATH_NO_EXT);
				$regex                        = '/^'.preg_quote($cache_dir, '/'). // Consider all schemes; all path paginations; and all possible variations.
				                                '\/[^\/]+\/'.preg_quote($cache_path_no_scheme_quv_ext, '/').
				                                '\/'.$patterns.'\./';

				/** @var $_file \RecursiveDirectoryIterator For IDEs. */
				foreach($this->dir_regex_iteration($cache_dir, $regex) as $_file) if($_file->isFile() || $_file->isLink())
				{
					if(strpos($_file->getSubpathname(), '/') === FALSE) continue;
					// Don't delete files in the immediate directory; e.g. `qc-advanced-cache` or `.htaccess`, etc.
					// Actual `http|https/...` cache files are nested. Files in the immediate directory are for other purposes.

					if(!unlink($_file->getPathname())) // Throw exception if unable to delete.
						throw new \exception(sprintf(__('Unable to auto-purge XML sitemap file: `%1$s`.', $this->text_domain), $_file->getPathname()));
					$counter++; // Increment counter for each file purge.

					if($enqueued_notices || !is_admin()) continue; // Stop here; we already issued a notice, or this notice is N/A.

					$this->enqueue_notice('<img src="'.esc_attr($this->url('/client-s/images/clear.png')).'" style="float:left; margin:0 10px 0 0; border:0;" />'.
					                      __('<strong>Quick Cache:</strong> detected changes. Found XML sitemaps (auto-purging).', $this->text_domain));
					$enqueued_notices++; // Notice counter.
				}
				unset($_file); // Just a little housekeeping.

				return apply_filters(__METHOD__, $counter, get_defined_vars());
			}

			/**
			 * Automatically purges cache files for the home page.
			 *
			 * @since 140422 First documented version.
			 *
			 * @return integer Total files purged by this routine (if any).
			 *
			 * @throws \exception If a purge failure occurs.
			 *
			 * @note Unlike many of the other `auto_` methods, this one is NOT currently
			 *    attached to any hooks. However, it is called upon by {@link auto_purge_post_cache()}.
			 *
			 * @see auto_purge_post_cache()
			 */
			public function auto_purge_home_page_cache()
			{
				$counter          = 0; // Initialize.
				$enqueued_notices = 0; // Initialize.

				if(isset($this->cache[__FUNCTION__]))
					return $counter; // Already did this.
				$this->cache[__FUNCTION__] = -1;

				if(!$this->options['enable'])
					return $counter; // Nothing to do.

				if(!$this->options['cache_purge_home_page_enable'])
					return $counter; // Nothing to do.

				if(!is_dir($cache_dir = $this->cache_dir()))
					return $counter; // Nothing to do.

				$cache_path_no_scheme_quv_ext = $this->build_cache_path(home_url('/'), '', '', $this::CACHE_PATH_NO_SCHEME | $this::CACHE_PATH_NO_PATH_INDEX | $this::CACHE_PATH_NO_QUV | $this::CACHE_PATH_NO_EXT);
				$regex                        = '/^'.preg_quote($cache_dir, '/'). // Consider all schemes; all path paginations; and all possible variations.
				                                '\/[^\/]+\/'.preg_quote($cache_path_no_scheme_quv_ext, '/').
				                                '(?:\/index)?(?:\.|\/(?:page|comment\-page)\/[0-9]+[.\/])/';

				/** @var $_file \RecursiveDirectoryIterator For IDEs. */
				foreach($this->dir_regex_iteration($cache_dir, $regex) as $_file) if($_file->isFile() || $_file->isLink())
				{
					if(strpos($_file->getSubpathname(), '/') === FALSE) continue;
					// Don't delete files in the immediate directory; e.g. `qc-advanced-cache` or `.htaccess`, etc.
					// Actual `http|https/...` cache files are nested. Files in the immediate directory are for other purposes.

					if(!unlink($_file->getPathname())) // Throw exception if unable to delete.
						throw new \exception(sprintf(__('Unable to auto-purge file: `%1$s`.', $this->text_domain), $_file->getPathname()));
					$counter++; // Increment counter for each file purge.

					if($enqueued_notices || !is_admin())
						continue; // Stop here; we already issued a notice, or this notice is N/A.

					$this->enqueue_notice('<img src="'.esc_attr($this->url('/client-s/images/clear.png')).'" style="float:left; margin:0 10px 0 0; border:0;" />'.
					                      __('<strong>Quick Cache:</strong> detected changes. Found cache file(s) for the designated "Home Page" (auto-purging).', $this->text_domain));
					$enqueued_notices++; // Notice counter.
				}
				unset($_file); // Just a little housekeeping.

				return apply_filters(__METHOD__, $counter, get_defined_vars());
			}

			/**
			 * Automatically purges cache files for the posts page.
			 *
			 * @since 140422 First documented version.
			 *
			 * @return integer Total files purged by this routine (if any).
			 *
			 * @throws \exception If a purge failure occurs.
			 *
			 * @note Unlike many of the other `auto_` methods, this one is NOT currently
			 *    attached to any hooks. However, it is called upon by {@link auto_purge_post_cache()}.
			 *
			 * @see auto_purge_post_cache()
			 */
			public function auto_purge_posts_page_cache()
			{
				$counter          = 0; // Initialize.
				$enqueued_notices = 0; // Initialize.

				if(isset($this->cache[__FUNCTION__]))
					return $counter; // Already did this.
				$this->cache[__FUNCTION__] = -1;

				if(!$this->options['enable'])
					return $counter; // Nothing to do.

				if(!$this->options['cache_purge_posts_page_enable'])
					return $counter; // Nothing to do.

				if(!is_dir($cache_dir = $this->cache_dir()))
					return $counter; // Nothing to do.

				$show_on_front  = get_option('show_on_front');
				$page_for_posts = get_option('page_for_posts');

				if(!in_array($show_on_front, array('posts', 'page'), TRUE))
					return $counter; // Nothing we can do in this case.

				if($show_on_front === 'page' && !$page_for_posts)
					return $counter; // Nothing we can do.

				if($show_on_front === 'posts') $posts_page = home_url('/');
				else if($show_on_front === 'page') $posts_page = get_permalink($page_for_posts);
				if(empty($posts_page)) return $counter; // Nothing we can do.

				$cache_path_no_scheme_quv_ext = $this->build_cache_path($posts_page, '', '', $this::CACHE_PATH_NO_SCHEME | $this::CACHE_PATH_NO_PATH_INDEX | $this::CACHE_PATH_NO_QUV | $this::CACHE_PATH_NO_EXT);
				$regex                        = '/^'.preg_quote($cache_dir, '/'). // Consider all schemes; all path paginations; and all possible variations.
				                                '\/[^\/]+\/'.preg_quote($cache_path_no_scheme_quv_ext, '/').
				                                '(?:\/index)?(?:\.|\/(?:page|comment\-page)\/[0-9]+[.\/])/';

				/** @var $_file \RecursiveDirectoryIterator For IDEs. */
				foreach($this->dir_regex_iteration($cache_dir, $regex) as $_file) if($_file->isFile() || $_file->isLink())
				{
					if(strpos($_file->getSubpathname(), '/') === FALSE) continue;
					// Don't delete files in the immediate directory; e.g. `qc-advanced-cache` or `.htaccess`, etc.
					// Actual `http|https/...` cache files are nested. Files in the immediate directory are for other purposes.

					if(!unlink($_file->getPathname())) // Throw exception if unable to delete.
						throw new \exception(sprintf(__('Unable to auto-purge file: `%1$s`.', $this->text_domain), $_file->getPathname()));
					$counter++; // Increment counter for each file purge.

					if($enqueued_notices || !is_admin())
						continue; // Stop here; we already issued a notice, or this notice is N/A.

					$this->enqueue_notice('<img src="'.esc_attr($this->url('/client-s/images/clear.png')).'" style="float:left; margin:0 10px 0 0; border:0;" />'.
					                      __('<strong>Quick Cache:</strong> detected changes. Found cache file(s) for the designated "Posts Page" (auto-purging).', $this->text_domain));
					$enqueued_notices++; // Notice counter.
				}
				unset($_file); // Just a little housekeeping.

				return apply_filters(__METHOD__, $counter, get_defined_vars());
			}

			/**
			 * Automatically purges cache files for the author page(s).
			 *
			 * @attaches-to `post_updated` hook.
			 *
			 * @since 140605 First documented version.
			 *
			 * @param integer  $post_ID A WordPress post ID.
			 * @param \WP_Post $post_after WP_Post object following the update.
			 * @param \WP_Post $post_before WP_Post object before the update.
			 *
			 * @return integer Total files purged by this routine (if any).
			 *
			 * @throws \exception If a purge failure occurs.
			 *
			 * @note If the author for the post is being changed, both the previous author
			 *       and current author pages are purged, if the post status is applicable.
			 *
			 */
			public function auto_purge_author_page_cache($post_ID, \WP_Post $post_after, \WP_Post $post_before)
			{
				$post_ID = (integer)$post_ID;

				$counter          = 0; // Initialize.
				$enqueued_notices = 0; // Initialize.

				$authors          = array(); // Initialize.
				$authors_to_purge = array(); // Initialize.

				if(isset($this->cache[__FUNCTION__][$post_ID][$post_after->ID][$post_before->ID]))
					return $counter; // Already did this.
				$this->cache[__FUNCTION__][$post_ID][$post_after->ID][$post_before->ID] = -1;

				if(!$this->options['enable'])
					return $counter; // Nothing to do.

				if(!$this->options['cache_purge_author_page_enable'])
					return $counter; // Nothing to do.

				if(!is_dir($cache_dir = $this->cache_dir()))
					return $counter; // Nothing to do.

				/*
				 * If we're changing the post author AND
				 *    the previous post status was either 'published' or 'private'
				 * then clear the author page for both authors.
				 *
				 * Else if the old post status was 'published' or 'private' OR
				 *    the new post status is 'published' or 'private'
				 * then clear the author page for the current author.
				 *
				 * Else return the counter; post status does not warrant purging author page cache.
				 */
				if($post_after->post_author !== $post_before->post_author &&
				   ($post_before->post_status === 'publish' || $post_before->post_status === 'private')
				)
				{
					$authors[] = (integer)$post_before->post_author;
					$authors[] = (integer)$post_after->post_author;
				}
				else if(($post_before->post_status === 'publish' || $post_before->post_status === 'private') ||
				        ($post_after->post_status === 'publish' || $post_after->post_status === 'private')
				)
					$authors[] = (integer)$post_after->post_author;

				else return $counter; // Nothing to do in this scenario.

				foreach($authors as $_author_id) // Get author posts URL and display name.
				{
					$authors_to_purge[$_author_id]['posts_url']    = get_author_posts_url($_author_id);
					$authors_to_purge[$_author_id]['display_name'] = get_the_author_meta('display_name', $_author_id);
				}
				unset($_author_id); // Housekeeping.

				foreach($authors_to_purge as $_author)
				{
					$cache_path_no_scheme_quv_ext = $this->build_cache_path($_author['posts_url'], '', '', $this::CACHE_PATH_NO_SCHEME | $this::CACHE_PATH_NO_PATH_INDEX | $this::CACHE_PATH_NO_QUV | $this::CACHE_PATH_NO_EXT);
					$regex                        = '/^'.preg_quote($cache_dir, '/'). // Consider all schemes; all path paginations; and all possible variations.
					                                '\/[^\/]+\/'.preg_quote($cache_path_no_scheme_quv_ext, '/').
					                                '(?:\/index)?(?:\.|\/(?:page|comment\-page)\/[0-9]+[.\/])/';

					/** @var $_file \RecursiveDirectoryIterator For IDEs. */
					foreach($this->dir_regex_iteration($cache_dir, $regex) as $_file) if($_file->isFile() || $_file->isLink())
					{
						if(strpos($_file->getSubpathname(), '/') === FALSE) continue;
						// Don't delete files in the immediate directory; e.g. `qc-advanced-cache` or `.htaccess`, etc.
						// Actual `http|https/...` cache files are nested. Files in the immediate directory are for other purposes.

						if(!unlink($_file->getPathname())) // Throw exception if unable to delete.
							throw new \exception(sprintf(__('Unable to auto-purge file: `%1$s`.', $this->text_domain), $_file->getPathname()));
						$counter++; // Increment counter for each file purge.

						if(!is_admin()) continue; // Stop here; this notice is N/A.

						$this->enqueue_notice('<img src="'.esc_attr($this->url('/client-s/images/clear.png')).'" style="float:left; margin:0 10px 0 0; border:0;" />'.
						                      sprintf(__('<strong>Quick Cache:</strong> detected changes. Found cache files for Author Page: <code>%1$s</code> (auto-purging).', $this->text_domain), esc_html($_author['display_name'])));
						$enqueued_notices++; // Notice counter.
					}
				}
				unset($_file, $_author); // Just a little housekeeping.

				return apply_filters(__METHOD__, $counter, get_defined_vars());
			}

			/**
			 * Automatically purge cache files for terms associated with a post.
			 *
			 * @attaches-to `added_term_relationship` hook.
			 * @attaches-to `delete_term_relationships` hook.
			 *
			 * @since 140605 First documented version.
			 *
			 * @param integer $id A WordPress post ID.
			 * @param bool    $force Defaults to a `FALSE` value.
			 *    Pass as TRUE if purge should be done for `draft`, `pending`,
			 *    or `future` post statuses.
			 *
			 * @return integer Total files purged by this routine (if any).
			 *
			 * @throws \exception If a purge failure occurs.
			 *
			 * @note In addition to the hooks this is attached to, it is also
			 *    called upon by {@link auto_purge_post_cache()}.
			 *
			 * @see auto_purge_post_cache()
			 */
			public function auto_purge_post_terms_cache($id, $force = FALSE)
			{
				$id = (integer)$id;

				$counter          = 0; // Initialize.
				$enqueued_notices = 0; // Initialize.

				if(isset($this->cache[__FUNCTION__][$id][(integer)$force]))
					return $counter; // Already did this.
				$this->cache[__FUNCTION__][$id][(integer)$force] = -1;

				if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
					return $counter; // Nothing to do.

				if(!$this->options['enable'])
					return $counter; // Nothing to do.

				if(!$this->options['cache_purge_term_category_enable'] &&
				   !$this->options['cache_purge_term_post_tag_enable'] &&
				   !$this->options['cache_purge_term_other_enable']
				) return $counter; // Nothing to do.

				if(!is_dir($cache_dir = $this->cache_dir()))
					return $counter; // Nothing to do.

				$post_status = get_post_status($id); // Cache this.

				if($post_status === 'auto-draft')
					return $counter; // Nothing to do.

				if($post_status === 'draft' && !$force)
					return $counter; // Nothing to do.

				if($post_status === 'pending' && !$force)
					return $counter; // Nothing to do.

				if($post_status === 'future' && !$force)
					return $counter; // Nothing to do.

				/*
				 * Build an array of available taxonomies for this post (as taxonomy objects)
				 */
				$taxonomies = get_object_taxonomies(get_post($id), 'objects');
				if(!is_array($taxonomies)) return $counter; // Nothing to do

				/*
				 * Build an array of terms associated with this post for each taxonomy.
				 * Also save taxonomy label information for Dashboard messaging later.
				 */
				$terms           = array();
				$taxonomy_labels = array();
				foreach($taxonomies as $_taxonomy)
				{
					// Check if this is a term we should purge
					if($_taxonomy->name === 'category' && !$this->options['cache_purge_term_category_enable'])
						continue;
					if($_taxonomy->name === 'post_tag' && !$this->options['cache_purge_term_post_tag_enable'])
						continue;
					if($_taxonomy->name !== 'category' && $_taxonomy->name !== 'post_tag' && !$this->options['cache_purge_term_other_enable'])
						continue;

					if(is_array($_terms = wp_get_post_terms($id, $_taxonomy->name)))
					{
						$terms = array_merge($terms, $_terms);

						// Improve Dashboard messaging by getting the Taxonomy label (e.g., "Tag" instead of "post_tag")
						// If we don't have a Singular Name for this taxonomy, use the taxonomy name itself
						if(empty($_taxonomy->labels->singular_name) || $_taxonomy->labels->singular_name === '')
							$taxonomy_labels[$_taxonomy->name] = $_taxonomy->name;
						else
							$taxonomy_labels[$_taxonomy->name] = $_taxonomy->labels->singular_name;
					}
				}
				unset($_taxonomy, $_terms);
				if(empty($terms)) return $counter; // Nothing to do.

				/*
				 * Build an array of terms with Term Names, Permalinks, and associated Taxonomy labels
				 */
				$terms_to_purge = array();
				$_i             = 0;
				foreach($terms as $_term)
				{
					if(($_link = get_term_link($_term)))
					{
						$terms_to_purge[$_i]['permalink'] = $_link; // E.g., "http://jason.websharks-inc.net/category/uncategorized/"
						$terms_to_purge[$_i]['term_name'] = $_term->name; // E.g., "Uncategorized"
						if(!empty($taxonomy_labels[$_term->taxonomy])) // E.g., "Tag" or "Category"
							$terms_to_purge[$_i]['taxonomy_label'] = $taxonomy_labels[$_term->taxonomy];
						else
							$terms_to_purge[$_i]['taxonomy_label'] = $_term->taxonomy; // e.g., "post_tag" or "category"
					}
					$_i++; // Array index counter.
				}
				unset($_term, $_link, $_i);
				if(empty($terms_to_purge)) return $counter; // Nothing to do.

				foreach($terms_to_purge as $_term)
				{
					$cache_path_no_scheme_quv_ext = $this->build_cache_path($_term['permalink'], '', '', $this::CACHE_PATH_NO_SCHEME | $this::CACHE_PATH_NO_PATH_INDEX | $this::CACHE_PATH_NO_QUV | $this::CACHE_PATH_NO_EXT);
					$regex                        = '/^'.preg_quote($cache_dir, '/'). // Consider all schemes; all path paginations; and all possible variations.
					                                '\/[^\/]+\/'.preg_quote($cache_path_no_scheme_quv_ext, '/').
					                                '(?:\/index)?(?:\.|\/(?:page|comment\-page)\/[0-9]+[.\/])/';

					/** @var $_file \RecursiveDirectoryIterator For IDEs. */
					foreach($this->dir_regex_iteration($cache_dir, $regex) as $_file) if($_file->isFile() || $_file->isLink())
					{
						if(strpos($_file->getSubpathname(), '/') === FALSE) continue;
						// Don't delete files in the immediate directory; e.g. `qc-advanced-cache` or `.htaccess`, etc.
						// Actual `http|https/...` cache files are nested. Files in the immediate directory are for other purposes.

						if(!unlink($_file->getPathname())) // Throw exception if unable to delete.
							throw new \exception(sprintf(__('Unable to auto-purge file: `%1$s`.', $this->text_domain), $_file->getPathname()));
						$counter++; // Increment counter for each file purge.

						if($enqueued_notices > 100 || !is_admin())
							continue; // Stop here; we're at our max number of notices or this notice is N/A.

						$this->enqueue_notice('<img src="'.esc_attr($this->url('/client-s/images/clear.png')).'" style="float:left; margin:0 10px 0 0; border:0;" />'.
						                      sprintf(__('<strong>Quick Cache:</strong> detected changes. Found cache files for %1$s: <code>%2$s</code> (auto-purging).', $this->text_domain), $_term['taxonomy_label'], $_term['term_name']));
						$enqueued_notices++; // Notice counter.
					}
				}
				unset($_term, $_file); // Just a little housekeeping.

				return apply_filters(__METHOD__, $counter, get_defined_vars());
			}

			/**
			 * Automatically purges cache files for a post associated with a particular comment.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to `trackback_post` hook.
			 * @attaches-to `pingback_post` hook.
			 * @attaches-to `comment_post` hook.
			 *
			 * @param integer $id A WordPress comment ID.
			 *
			 * @return integer Total files purged by this routine (if any).
			 *
			 * @see auto_purge_post_cache()
			 */
			public function auto_purge_comment_post_cache($id)
			{
				$id = (integer)$id;

				$counter = 0; // Initialize.

				if(isset($this->cache[__FUNCTION__][$id]))
					return $counter; // Already did this.
				$this->cache[__FUNCTION__][$id] = -1;

				if(!$this->options['enable'])
					return $counter; // Nothing to do.

				if(!is_object($comment = get_comment($id)))
					return $counter; // Nothing we can do.

				if(empty($comment->comment_post_ID))
					return $counter; // Nothing we can do.

				if($comment->comment_approved === 'spam' || $comment->comment_approved === '0')
				{
					static::$static['___allow_auto_purge_post_cache'] = FALSE; // Don't allow next `auto_purge_post_cache()` call to clear post cache.
					return $counter; // Don't allow spam to clear cache.
				}

				$counter = $this->auto_purge_post_cache($comment->comment_post_ID);

				return apply_filters(__METHOD__, $counter, get_defined_vars());
			}

			/**
			 * Automatically purges cache files for a post associated with a particular comment.
			 *
			 * @since 140711 First documented version.
			 *
			 * @attaches-to `transition_comment_status` hook.
			 *
			 * @param string   $new_status New comment status.
			 * @param string   $old_status Old comment status.
			 * @param \WP_Post $comment Comment object.
			 *
			 * @return integer Total files purged by this routine (if any).
			 *
			 * @throws \exception If a purge failure occurs.
			 *
			 * @note This is also called upon by other routines which listen for
			 *    events that are indirectly associated with a comment ID.
			 *
			 * @see auto_purge_comment_post_cache()
			 */
			public function auto_purge_comment_transition($new_status, $old_status, $comment)
			{
				$counter = 0; // Initialize.

				if(!$this->options['enable'])
					return $counter; // Nothing to do.

				if(!is_object($comment))
					return $counter; // Nothing we can do.

				if(empty($comment->comment_post_ID))
					return $counter; // Nothing we can do.

				if($old_status === 'approved' || ($old_status === 'unapproved' && $new_status === 'approved'))
				{
					$counter = $this->auto_purge_post_cache($comment->comment_post_ID);
				}
				else
				{
					static::$static['___allow_auto_purge_post_cache'] = FALSE; // Don't allow next `auto_purge_post_cache()` call to clear post cache.
					return $counter; // Don't allow Unapproved comments not being Approved to clear cache.
				}

				return apply_filters(__METHOD__, $counter, get_defined_vars());
			}

			/**
			 * This constructs an absolute server directory path (no trailing slashes);
			 *    which is always nested into {@link \WP_CONTENT_DIR} and the configured `base_dir` option value.
			 *
			 * @since 140605 Moving to a base directory structure.
			 *
			 * @param string $rel_dir_file A sub-directory or file; relative location please.
			 *
			 * @return string The full absolute server path to `$rel_dir_file`.
			 *
			 * @throws \exception If `base_dir` is empty when this method is called upon;
			 *    i.e. if you attempt to call upon this method before {@link setup()} runs.
			 */
			public function wp_content_dir_to($rel_dir_file)
			{
				$rel_dir_file = trim((string)$rel_dir_file, '\\/'." \t\n\r\0\x0B");

				if(empty($this->options['base_dir'])) // Security enhancement; NEVER allow this to be empty.
					throw new \exception(__('Doing it wrong! Missing `base_dir` option value. MUST call this method after `setup()`.', $this->text_domain));

				$wp_content_dir_to = WP_CONTENT_DIR.'/'.$this->options['base_dir'];
				if(isset($rel_dir_file[0])) $wp_content_dir_to .= '/'.$rel_dir_file;

				return apply_filters(__METHOD__, $wp_content_dir_to, get_defined_vars());
			}

			/**
			 * This constructs a relative/base directory path (no leading/trailing slashes).
			 *    Always relative to {@link \WP_CONTENT_DIR}. Depends on the configured `base_dir` option value.
			 *
			 * @since 140605 Moving to a base directory structure.
			 *
			 * @param string $rel_dir_file A sub-directory or file; relative location please.
			 *
			 * @return string The relative/base directory path to `$rel_dir_file`.
			 *
			 * @throws \exception If `base_dir` is empty when this method is called upon;
			 *    i.e. if you attempt to call upon this method before {@link setup()} runs.
			 */
			public function basepath_to($rel_dir_file)
			{
				$rel_dir_file = trim((string)$rel_dir_file, '\\/'." \t\n\r\0\x0B");

				if(empty($this->options['base_dir'])) // Security enhancement; NEVER allow this to be empty.
					throw new \exception(__('Doing it wrong! Missing `base_dir` option value. MUST call this method after `setup()`.', $this->text_domain));

				$basepath_to = $this->options['base_dir'];
				if(isset($rel_dir_file[0])) $basepath_to .= '/'.$rel_dir_file;

				return apply_filters(__METHOD__, $basepath_to, get_defined_vars());
			}

			/**
			 * Finds absolute server path to `/wp-config.php` file.
			 *
			 * @since 140422 First documented version.
			 *
			 * @return string Absolute server path to `/wp-config.php` file;
			 *    else an empty string if unable to locate the file.
			 */
			public function find_wp_config_file()
			{
				if(is_file($abspath_wp_config = ABSPATH.'wp-config.php'))
					$wp_config_file = $abspath_wp_config;

				else if(is_file($dirname_abspath_wp_config = dirname(ABSPATH).'/wp-config.php'))
					$wp_config_file = $dirname_abspath_wp_config;

				else $wp_config_file = ''; // Unable to find `/wp-config.php` file.

				return apply_filters(__METHOD__, $wp_config_file, get_defined_vars());
			}

			/**
			 * Adds `define('WP_CACHE', TRUE);` to the `/wp-config.php` file.
			 *
			 * @since 140422 First documented version.
			 *
			 * @return string The new contents of the updated `/wp-config.php` file;
			 *    else an empty string if unable to add the `WP_CACHE` constant.
			 */
			public function add_wp_cache_to_wp_config()
			{
				if(!$this->options['enable'])
					return ''; // Nothing to do.

				if(!($wp_config_file = $this->find_wp_config_file()))
					return ''; // Unable to find `/wp-config.php`.

				if(!is_readable($wp_config_file)) return ''; // Not possible.
				if(!($wp_config_file_contents = file_get_contents($wp_config_file)))
					return ''; // Failure; could not read file.

				if(preg_match('/define\s*\(\s*([\'"])WP_CACHE\\1\s*,\s*(?:\-?[1-9][0-9\.]*|TRUE|([\'"])(?:[^0\'"]|[^\'"]{2,})\\2)\s*\)\s*;/i', $wp_config_file_contents))
					return $wp_config_file_contents; // It's already in there; no need to modify this file.

				if(!($wp_config_file_contents = $this->remove_wp_cache_from_wp_config()))
					return ''; // Unable to remove previous value.

				if(!($wp_config_file_contents = preg_replace('/^\s*(\<\?php|\<\?)\s+/i', '${1}'."\n"."define('WP_CACHE', TRUE);"."\n", $wp_config_file_contents, 1)))
					return ''; // Failure; something went terribly wrong here.

				if(strpos($wp_config_file_contents, "define('WP_CACHE', TRUE);") === FALSE)
					return ''; // Failure; unable to add; unexpected PHP code.

				if(defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS)
					return ''; // We may NOT edit any files.

				if(!is_writable($wp_config_file)) return ''; // Not possible.
				if(!file_put_contents($wp_config_file, $wp_config_file_contents))
					return ''; // Failure; could not write changes.

				return apply_filters(__METHOD__, $wp_config_file_contents, get_defined_vars());
			}

			/**
			 * Removes `define('WP_CACHE', TRUE);` from the `/wp-config.php` file.
			 *
			 * @since 140422 First documented version.
			 *
			 * @return string The new contents of the updated `/wp-config.php` file;
			 *    else an empty string if unable to remove the `WP_CACHE` constant.
			 */
			public function remove_wp_cache_from_wp_config()
			{
				if(!($wp_config_file = $this->find_wp_config_file()))
					return ''; // Unable to find `/wp-config.php`.

				if(!is_readable($wp_config_file)) return ''; // Not possible.
				if(!($wp_config_file_contents = file_get_contents($wp_config_file)))
					return ''; // Failure; could not read file.

				if(!preg_match('/([\'"])WP_CACHE\\1/i', $wp_config_file_contents))
					return $wp_config_file_contents; // Already gone.

				if(preg_match('/define\s*\(\s*([\'"])WP_CACHE\\1\s*,\s*(?:0|FALSE|NULL|([\'"])0?\\2)\s*\)\s*;/i', $wp_config_file_contents))
					return $wp_config_file_contents; // It's already disabled; no need to modify this file.

				if(!($wp_config_file_contents = preg_replace('/define\s*\(\s*([\'"])WP_CACHE\\1\s*,\s*(?:\-?[0-9\.]+|TRUE|FALSE|NULL|([\'"])[^\'"]*\\2)\s*\)\s*;/i', '', $wp_config_file_contents)))
					return ''; // Failure; something went terribly wrong here.

				if(preg_match('/([\'"])WP_CACHE\\1/i', $wp_config_file_contents))
					return ''; // Failure; perhaps the `/wp-config.php` file contains syntax we cannot remove safely.

				if(defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS)
					return ''; // We may NOT edit any files.

				if(!is_writable($wp_config_file)) return ''; // Not possible.
				if(!file_put_contents($wp_config_file, $wp_config_file_contents))
					return ''; // Failure; could not write changes.

				return apply_filters(__METHOD__, $wp_config_file_contents, get_defined_vars());
			}

			/**
			 * Checks to make sure the `qc-advanced-cache` file still exists;
			 *    and if it doesn't, the `advanced-cache.php` is regenerated automatically.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to `init` hook.
			 *
			 * @note This runs so that remote deployments which completely wipe out an
			 *    existing set of website files (like the AWS Elastic Beanstalk does) will NOT cause Quick Cache
			 *    to stop functioning due to the lack of an `advanced-cache.php` file, which is generated by Quick Cache.
			 *
			 *    For instance, if you have a Git repo with all of your site files; when you push those files
			 *    to your website to deploy them, you most likely do NOT have the `advanced-cache.php` file.
			 *    Quick Cache creates this file on its own. Thus, if it's missing (and QC is active)
			 *    we simply regenerate the file automatically to keep Quick Cache running.
			 */
			public function check_advanced_cache()
			{
				if(!$this->options['enable'])
					return; // Nothing to do.

				if(!empty($_REQUEST[__NAMESPACE__]))
					return; // Skip on plugin actions.

				$cache_dir = $this->cache_dir(); // Current cache directory.

				if(!is_file($cache_dir.'/qc-advanced-cache'))
					$this->add_advanced_cache();
			}

			/**
			 * Creates and adds the `advanced-cache.php` file.
			 *
			 * @since 140422 First documented version.
			 *
			 * @note Many of the Quick Cache option values become PHP Constants in the `advanced-cache.php` file.
			 *    We take an option key (e.g. `version_salt`) and prefix it with `quick_cache_`.
			 *    Then we convert it to uppercase (e.g. `QUICK_CACHE_VERSION_SALT`) and wrap
			 *    it with double percent signs to form a replacement codes.
			 *    ex: `%%QUICK_CACHE_VERSION_SALT%%`
			 *
			 * @note There are a few special options considered by this routine which actually
			 *    get converted to regex patterns before they become replacement codes.
			 *
			 * @note In the case of a version salt, a PHP syntax is performed also.
			 *
			 * @return boolean|null `TRUE` on success. `FALSE` or `NULL` on failure.
			 *    A special `NULL` return value indicates success with a single failure
			 *    that is specifically related to the `qc-advanced-cache` file.
			 */
			public function add_advanced_cache()
			{
				if(!$this->remove_advanced_cache())
					return FALSE; // Still exists.

				$cache_dir               = $this->cache_dir(); // Current cache directory.
				$advanced_cache_file     = WP_CONTENT_DIR.'/advanced-cache.php';
				$advanced_cache_template = dirname(__FILE__).'/includes/advanced-cache.tpl.php';

				if(is_file($advanced_cache_file) && !is_writable($advanced_cache_file))
					return FALSE; // Not possible to create.

				if(!is_file($advanced_cache_file) && !is_writable(dirname($advanced_cache_file)))
					return FALSE; // Not possible to create.

				if(!is_file($advanced_cache_template) || !is_readable($advanced_cache_template))
					return FALSE; // Template file is missing; or not readable.

				if(!($advanced_cache_contents = file_get_contents($advanced_cache_template)))
					return FALSE; // Template file is missing; or is not readable.

				$possible_advanced_cache_constant_key_values = array_merge(
					$this->options, // The following additional keys are dynamic.
					array('cache_dir' => $this->basepath_to($this->cache_sub_dir)
					));
				foreach($possible_advanced_cache_constant_key_values as $_option => $_value)
				{
					$_value = (string)$_value; // Force string.

					switch($_option) // Some values need tranformations.
					{
						default: // Default case handler.

							$_value = "'".$this->esc_sq($_value)."'";

							break; // Break switch handler.
					}
					$advanced_cache_contents = // Fill replacement codes.
						str_ireplace(array("'%%".__NAMESPACE__.'_'.$_option."%%'",
						                   "'%%".str_ireplace('_cache', '', __NAMESPACE__).'_'.$_option."%%'"),
						             $_value, $advanced_cache_contents);
				}
				unset($_option, $_value, $_values, $_response); // Housekeeping.

				// Make it possible for the `advanced-cache.php` handler to find the plugin directory reliably.
				$advanced_cache_contents = str_ireplace("'%%".__NAMESPACE__."_PLUGIN_FILE%%'", // e.g. `QUICK_CACHE_PLUGIN_FILE`.
				                                        "'".$this->esc_sq($this->file)."'", $advanced_cache_contents);

				// Ignore; this is created by Quick Cache; and we don't need to obey in this case.
				#if(defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS)
				#	return FALSE; // We may NOT edit any files.

				if(!file_put_contents($advanced_cache_file, $advanced_cache_contents))
					return FALSE; // Failure; could not write file.

				if(!is_dir($cache_dir))
					mkdir($cache_dir, 0775, TRUE);

				if(is_writable($cache_dir) && !is_file($cache_dir.'/.htaccess'))
					file_put_contents($cache_dir.'/.htaccess', $this->htaccess_deny);

				if(!is_file($cache_dir.'/.htaccess'))
					return NULL; // Failure; could not write .htaccess file. Special return value (NULL) in this case.

				if(!is_dir($cache_dir) || !is_writable($cache_dir) || !file_put_contents($cache_dir.'/qc-advanced-cache', time()))
					return NULL; // Failure; could not write cache entry. Special return value (NULL) in this case.

				return TRUE; // All done :-)
			}

			/**
			 * Removes the `advanced-cache.php` file.
			 *
			 * @since 140422 First documented version.
			 *
			 * @return boolean `TRUE` on success. `FALSE` on failure.
			 *
			 * @note The `advanced-cache.php` file is NOT actually deleted by this routine.
			 *    Instead of deleting the file, we simply empty it out so that it's `0` bytes in size.
			 *
			 *    The reason for this is to preserve any file permissions set by the site owner.
			 *    If the site owner previously allowed this specific file to become writable, we don't want to
			 *    lose that permission by deleting the file; forcing the site owner to do it all over again later.
			 *
			 *    An example of where this is useful is when a site owner deactivates the QC plugin,
			 *    but later they decide that QC really is the most awesome plugin in the world and they turn it back on.
			 *
			 * @see delete_advanced_cache()
			 */
			public function remove_advanced_cache()
			{
				$advanced_cache_file = WP_CONTENT_DIR.'/advanced-cache.php';

				if(!is_file($advanced_cache_file)) return TRUE; // Already gone.

				if(is_readable($advanced_cache_file) && filesize($advanced_cache_file) === 0)
					return TRUE; // Already gone; i.e. it's empty already.

				if(!is_writable($advanced_cache_file)) return FALSE; // Not possible.

				// Ignore; this is created by Quick Cache; and we don't need to obey in this case.
				#if(defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS)
				#	return FALSE; // We may NOT edit any files.

				/* Empty the file only. This way permissions are NOT lost in cases where
					a site owner makes this specific file writable for Quick Cache. */
				if(file_put_contents($advanced_cache_file, '') !== 0)
					return FALSE; // Failure.

				return TRUE; // Removal success.
			}

			/**
			 * Deletes the `advanced-cache.php` file.
			 *
			 * @since 140422 First documented version.
			 *
			 * @return boolean `TRUE` on success. `FALSE` on failure.
			 *
			 * @note The `advanced-cache.php` file is deleted by this routine.
			 *
			 * @see remove_advanced_cache()
			 */
			public function delete_advanced_cache()
			{
				$advanced_cache_file = WP_CONTENT_DIR.'/advanced-cache.php';

				if(!is_file($advanced_cache_file)) return TRUE; // Already gone.

				// Ignore; this is created by Quick Cache; and we don't need to obey in this case.
				#if(defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS)
				#	return FALSE; // We may NOT edit any files.

				if(!is_writable($advanced_cache_file) || !unlink($advanced_cache_file))
					return FALSE; // Not possible; or outright failure.

				return TRUE; // Deletion success.
			}

			/**
			 * Checks to make sure the `qc-blog-paths` file still exists;
			 *    and if it doesn't, the `qc-blog-paths` file is regenerated automatically.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to `init` hook.
			 *
			 * @note This runs so that remote deployments which completely wipe out an
			 *    existing set of website files (like the AWS Elastic Beanstalk does) will NOT cause Quick Cache
			 *    to stop functioning due to the lack of a `qc-blog-paths` file, which is generated by Quick Cache.
			 *
			 *    For instance, if you have a Git repo with all of your site files; when you push those files
			 *    to your website to deploy them, you most likely do NOT have the `qc-blog-paths` file.
			 *    Quick Cache creates this file on its own. Thus, if it's missing (and QC is active)
			 *    we simply regenerate the file automatically to keep Quick Cache running.
			 */
			public function check_blog_paths()
			{
				if(!$this->options['enable'])
					return; // Nothing to do.

				if(!is_multisite()) return; // N/A.

				if(!empty($_REQUEST[__NAMESPACE__]))
					return; // Skip on plugin actions.

				$cache_dir = $this->cache_dir(); // Current cache directory.

				if(!is_file($cache_dir.'/qc-blog-paths'))
					$this->update_blog_paths();
			}

			/**
			 * Creates and/or updates the `qc-blog-paths` file.
			 *
			 * @since 140422 First documented version.
			 *
			 * @attaches-to `enable_live_network_counts` filter.
			 *
			 * @param mixed $enable_live_network_counts Optional, defaults to a `NULL` value.
			 *
			 * @return mixed The value of `$enable_live_network_counts` (passes through).
			 *
			 * @note While this routine is attached to a WP filter, we also call upon it directly at times.
			 */
			public function update_blog_paths($enable_live_network_counts = NULL)
			{
				$value = // This hook actually rides on a filter.
					$enable_live_network_counts; // Filter value.

				if(!$this->options['enable'])
					return $value; // Nothing to do.

				if(!is_multisite()) return $value; // N/A.

				$cache_dir = $this->cache_dir(); // cache dir.

				if(!is_dir($cache_dir))
					mkdir($cache_dir, 0775, TRUE);

				if(is_writable($cache_dir) && !is_file($cache_dir.'/.htaccess'))
					file_put_contents($cache_dir.'/.htaccess', $this->htaccess_deny);

				if(is_dir($cache_dir) && is_writable($cache_dir))
				{
					$paths = // Collect child blog paths from the WordPress database.
						$this->wpdb()->get_col("SELECT `path` FROM `".esc_sql($this->wpdb()->blogs)."` WHERE `deleted` <= '0'");

					foreach($paths as &$_path) // Strip base; these need to match `$host_dir_token`.
						$_path = '/'.ltrim(preg_replace('/^'.preg_quote($this->host_base_token(), '/').'/', '', $_path), '/');
					unset($_path); // Housekeeping.

					file_put_contents($cache_dir.'/qc-blog-paths', serialize($paths));
				}
				return $value; // Pass through untouched (always).
			}
		}

		/**
		 * Used internally by other Quick Cache classes as an easy way to reference
		 *    the core {@link plugin} class instance for Quick Cache.
		 *
		 * @since 140422 First documented version.
		 *
		 * @return plugin Class instance.
		 */
		function plugin() // Easy reference.
		{
			return $GLOBALS[__NAMESPACE__];
		}

		/**
		 * A global reference to the Quick Cache plugin.
		 *
		 * @since 140422 First documented version.
		 *
		 * @var plugin $GLOBALS [__NAMESPACE__]
		 */
		$GLOBALS[__NAMESPACE__] = new plugin(); // New plugin instance.
		/*
		 * API class inclusion; depends on {@link $GLOBALS[__NAMESPACE__]}.
		 */
		require_once dirname(__FILE__).'/includes/api-class.php';
	}
	else add_action('all_admin_notices', function () // Do NOT load in this case.
	{
		echo '<div class="error"><p>'. // Running multiple versions of this plugin at same time.
		     __('Please disable the LITE version of Quick Cache before you activate the PRO version.',
		        str_replace('_', '-', __NAMESPACE__)).'</p></div>';
	});
}