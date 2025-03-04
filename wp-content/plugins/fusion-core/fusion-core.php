<?php
/**
 * Plugin Name: Fusion Core
 * Plugin URI: http://theme-fusion.com
 * Description: ThemeFusion Core Plugin for ThemeFusion Themes
 * Version: 3.8.2
 * Author: ThemeFusion
 * Author URI: http://theme-fusion.com
 *
 * @package Fusion-Core
 * @subpackage Core
 */

// Plugin version.
if ( ! defined( 'FUSION_CORE_VERSION' ) ) {
	define( 'FUSION_CORE_VERSION', '3.8.2' );
}

// Plugin Folder Path.
if ( ! defined( 'FUSION_CORE_PATH' ) ) {
	define( 'FUSION_CORE_PATH', wp_normalize_path( dirname( __FILE__ ) ) );
}

// Plugin Folder URL.
if ( ! defined( 'FUSION_CORE_URL' ) ) {
	define( 'FUSION_CORE_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! class_exists( 'FusionCore_Plugin' ) ) {
	/**
	 * The main fusion-core class.
	 */
	class FusionCore_Plugin {

		/**
		 * Plugin version, used for cache-busting of style and script file references.
		 *
		 * @since   1.0.0
		 * @var  string
		 */
		const VERSION = FUSION_CORE_VERSION;

		/**
		 * Instance of the class.
		 *
		 * @static
		 * @access protected
		 * @since 1.0.0
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * JS folder URL.
		 *
		 * @static
		 * @access public
		 * @since 3.0.3
		 * @var string
		 */
		public static $js_folder_url;

		/**
		 * JS folder path.
		 *
		 * @static
		 * @access public
		 * @since 3.0.3
		 * @var string
		 */
		public static $js_folder_path;


		/**
		 * Initialize the plugin by setting localization and loading public scripts
		 * and styles.
		 *
		 * @access private
		 * @since 1.0.0
		 */
		private function __construct() {
			self::$js_folder_url  = FUSION_CORE_URL . 'js/min';
			self::$js_folder_path = FUSION_CORE_PATH . '/js/min';

			add_action( 'after_setup_theme', array( $this, 'load_fusion_core_text_domain' ) );
			add_action( 'after_setup_theme', array( $this, 'add_image_size' ) );

			// Load scripts & styles.
			if ( ! is_admin() ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
				add_filter( 'fusion_dynamic_css_final', array( $this, 'scripts_dynamic_css' ) );
			}

			// Register custom post-types and taxonomies.
			add_action( 'init', array( $this, 'register_post_types' ) );

			// Admin menu tweaks.
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );

			// Provide single portfolio template via filter.
			add_filter( 'single_template', array( $this, 'portfolio_single_template' ) );

			// Check if Fusion Core has been updated.  Delay until after theme is available.
			add_action( 'after_setup_theme', array( $this, 'versions_compare' ) );

			// Exclude post type from Events Calendar.
			add_filter( 'tribe_tickets_settings_post_types', array( $this, 'fusion_core_exclude_post_type' ) );

			// Set Fusion Builder dependencies.
			add_filter( 'fusion_builder_option_dependency', array( $this, 'set_builder_dependencies' ), 10, 3 );

			// Map Fusion Builder descriptions.
			add_filter( 'fusion_builder_map_descriptions', array( $this, 'map_builder_descriptions' ), 10, 1 );
		}

		/**
		 * Register the plugin text domain.
		 *
		 * @access public
		 * @return void
		 */
		public function load_fusion_core_text_domain() {
			load_plugin_textdomain( 'fusion-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Return an instance of this class.
		 *
		 * @static
		 * @access public
		 * @since 1.0.0
		 * @return object  A single instance of the class.
		 */
		public static function get_instance() {

			// If the single instance hasn't been set yet, set it now.
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;

		}

		/**
		 * Gets the value of a theme option.
		 *
		 * @static
		 * @access public
		 * @since 3.0
		 * @param string|null  $option The option.
		 * @param string|false $subset The sub-option in case of an array.
		 */
		public static function get_option( $option = null, $subset = false ) {

			$value = '';
			// If Avada is installed, use it to get the theme-option.
			if ( class_exists( 'Avada' ) ) {
				$value = Avada()->settings->get( $option, $subset );
			}
			return apply_filters( 'fusion_core_get_option', $value, $option, $subset );

		}

		/**
		 * Returns a cached query.
		 * If the query is not cached then it caches it and returns the result.
		 *
		 * @static
		 * @access public
		 * @param string|array $args Same as in WP_Query.
		 * @return object
		 */
		public static function fusion_core_cached_query( $args ) {

			// Make sure cached queries are not language agnostic.
			if ( class_exists( 'Fusion_Multilingual' ) ) {
				if ( is_array( $args ) ) {
					$args['fusion_lang'] = Fusion_Multilingual::get_active_language();
				} else {
					$args .= '&fusion_lang=' . Fusion_Multilingual::get_active_language();
				}
			}

			$query_id = md5( maybe_serialize( $args ) );
			$query    = wp_cache_get( $query_id, 'avada' );
			if ( false === $query ) {
				$query = new WP_Query( $args );
				wp_cache_set( $query_id, $query, 'avada' );
			}
			return $query;

		}

		/**
		 * Returns array of available fusion sliders.
		 *
		 * @access public
		 * @since 3.1.6
		 * @param string $add_select_slider_label Sets a "Add Slider" label at the beginning of the array.
		 * @return array
		 */
		public static function get_fusion_sliders( $add_select_slider_label = '' ) {
			$slides_array = array();

			if ( $add_select_slider_label ) {
				$slides_array[''] = esc_html( $add_select_slider_label );
			}

			$slides = array();
			$slides = get_terms( 'slide-page' );
			if ( $slides && ! isset( $slides->errors ) ) {
				$slides = maybe_unserialize( $slides );
				foreach ( $slides as $key => $val ) {
					$slides_array[ $val->slug ] = $val->name . ' (#' . $val->term_id . ')';
				}
			}
			return $slides_array;
		}

		/**
		 * Add image sizes.
		 *
		 * @access  public
		 */
		public function add_image_size() {
			add_image_size( 'portfolio-full', 940, 400, true );
			add_image_size( 'portfolio-one', 540, 272, true );
			add_image_size( 'portfolio-two', 460, 295, true );
			add_image_size( 'portfolio-three', 300, 214, true );
			add_image_size( 'portfolio-five', 177, 142, true );
		}

		/**
		 * Enqueues scripts.
		 *
		 * @access public
		 */
		public function scripts() {

			// If we're using a CSS to file compiler there's no need to enqueue separate file.
			// It will be added directly to the compiled CSS (@see scripts_dynamic_css method).
			if ( class_exists( 'Fusion_Settings' ) ) {
				global $fusion_settings;
				if ( ! $fusion_settings ) {
					$fusion_settings = Fusion_Settings::get_instance();
				}

				if ( 'off' !== $fusion_settings->get( 'css_cache_method' ) ) {
					return;
				}
			}

			wp_enqueue_style( 'fusion-core-style', plugins_url( 'css/style.min.css', __FILE__ ) );
		}

		/**
		 * Adds styles to the compiled dynamic-css.
		 *
		 * @access public
		 * @since 3.1.5
		 * @param string $original_styles The compiled dynamic-css styles.
		 * @return string The dynamic-css with extra css apended if needed.
		 */
		public function scripts_dynamic_css( $original_styles ) {
			global $fusion_settings;

			if ( ! $fusion_settings ) {
				$fusion_settings = Fusion_Settings::get_instance();
			}

			if ( 'off' !== $fusion_settings->get( 'css_cache_method' ) ) {
				$wp_filesystem = Fusion_Helper::init_filesystem();
				// Stylesheet ID: fusion-core-style.
				return $wp_filesystem->get_contents( FUSION_CORE_PATH . '/css/style.min.css' ) . $original_styles;
			}

			return $original_styles;
		}

		/**
		 * Register custom post types.
		 *
		 * @access public
		 * @since 3.1.0
		 */
		public function register_post_types() {

			global $fusion_settings;
			if ( ! $fusion_settings ) {
				$fusion_settings_array = array(
					'portfolio_slug' => 'portfolio-items',
					'status_eslider' => '1',
				);
				if ( class_exists( 'Fusion_Settings' ) ) {
					$fusion_settings = Fusion_Settings::get_instance();

					$fusion_settings_array = array(
						'portfolio_slug' => $fusion_settings->get( 'portfolio_slug' ),
						'status_eslider' => $fusion_settings->get( 'status_eslider' ),
					);
				}
			} else {
				$fusion_settings_array = array(
					'portfolio_slug' => $fusion_settings->get( 'portfolio_slug' ),
					'status_eslider' => $fusion_settings->get( 'status_eslider' ),
				);
			}

			$permalinks = get_option( 'avada_permalinks' );

			// Portfolio.
			register_post_type(
				'avada_portfolio',
				array(
					'labels'      => array(
						'name'                     => _x( 'Portfolio', 'Post Type General Name', 'fusion-core' ),
						'singular_name'            => _x( 'Portfolio', 'Post Type Singular Name', 'fusion-core' ),
						'add_new_item'             => _x( 'Add New Portfolio Post', 'fusion-core' ),
						'edit_item'                => _x( 'Edit Portfolio Post', 'fusion-core' ),
						'item_published'           => __( 'Portfolio published.', 'fusion-core' ),
						'item_published_privately' => __( 'Portfolio published privately.', 'fusion-core' ),
						'item_reverted_to_draft'   => __( 'Portfolio reverted to draft.', 'fusion-core' ),
						'item_scheduled'           => __( 'Portfolio scheduled.', 'fusion-core' ),
						'item_updated'             => __( 'Portfolio updated.', 'fusion-core' ),
					),
					'public'      => true,
					'has_archive' => true,
					'rewrite'     => array(
						'slug' => $fusion_settings_array['portfolio_slug'],
					),
					'supports'    => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'revisions', 'custom-fields', 'page-attributes', 'post-formats' ),
					'can_export'  => true,
				)
			);

			register_taxonomy(
				'portfolio_category',
				'avada_portfolio',
				array(
					'hierarchical' => true,
					'label'        => esc_attr__( 'Portfolio Categories', 'fusion-core' ),
					'query_var'    => true,
					'rewrite'      => array(
						'slug'       => empty( $permalinks['portfolio_category_base'] ) ? _x( 'portfolio_category', 'slug', 'fusion-core' ) : $permalinks['portfolio_category_base'],
						'with_front' => false,
					),
				)
			);

			register_taxonomy(
				'portfolio_skills',
				'avada_portfolio',
				array(
					'hierarchical' => true,
					'label'        => esc_attr__( 'Skills', 'fusion-core' ),
					'query_var'    => true,
					'labels'       => array(
						'add_new_item' => esc_attr__( 'Add New Skill', 'fusion-core' ),
					),
					'rewrite'      => array(
						'slug'       => empty( $permalinks['portfolio_skills_base'] ) ? _x( 'portfolio_skills', 'slug', 'fusion-core' ) : $permalinks['portfolio_skills_base'],
						'with_front' => false,
					),
				)
			);

			register_taxonomy(
				'portfolio_tags',
				'avada_portfolio',
				array(
					'hierarchical' => false,
					'label'        => esc_attr__( 'Tags', 'fusion-core' ),
					'query_var'    => true,
					'rewrite'      => array(
						'slug'       => empty( $permalinks['portfolio_tags_base'] ) ? _x( 'portfolio_tags', 'slug', 'fusion-core' ) : $permalinks['portfolio_tags_base'],
						'with_front' => false,
					),
				)
			);

			// FAQ.
			register_post_type(
				'avada_faq',
				array(
					'labels'      => array(
						'name'                     => _x( 'FAQs', 'Post Type General Name', 'fusion-core' ),
						'singular_name'            => _x( 'FAQ', 'Post Type Singular Name', 'fusion-core' ),
						'add_new_item'             => _x( 'Add New FAQ Post', 'fusion-core' ),
						'edit_item'                => _x( 'Edit FAQ Post', 'fusion-core' ),
						'item_published'           => __( 'FAQ published.', 'fusion-core' ),
						'item_published_privately' => __( 'FAQ published privately.', 'fusion-core' ),
						'item_reverted_to_draft'   => __( 'FAQ reverted to draft.', 'fusion-core' ),
						'item_scheduled'           => __( 'FAQ scheduled.', 'fusion-core' ),
						'item_updated'             => __( 'FAQ updated.', 'fusion-core' ),
					),
					'public'      => true,
					'has_archive' => true,
					'rewrite'     => array(
						'slug' => 'faq-items',
					),
					'supports'    => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'revisions', 'custom-fields', 'page-attributes', 'post-formats' ),
					'can_export'  => true,
				)
			);

			register_taxonomy(
				'faq_category',
				'avada_faq',
				array(
					'hierarchical' => true,
					'label'        => __( 'FAQ Categories', 'fusion-core' ),
					'query_var'    => true,
					'rewrite'      => true,
				)
			);

			// Elastic Slider.
			if ( ! class_exists( 'Fusion_Settings' ) || '0' !== $fusion_settings_array['status_eslider'] ) {
				register_post_type(
					'themefusion_elastic',
					array(
						'public'              => true,
						'has_archive'         => false,
						'rewrite'             => array(
							'slug' => 'elastic-slide',
						),
						'supports'            => array( 'title', 'thumbnail' ),
						'can_export'          => true,
						'menu_position'       => 100,
						'publicly_queryable'  => false,
						'exclude_from_search' => true,
						'labels'              => array(
							'name'                     => _x( 'Elastic Sliders', 'Post Type General Name', 'fusion-core' ),
							'singular_name'            => _x( 'Elastic Slider', 'Post Type Singular Name', 'fusion-core' ),
							'menu_name'                => esc_attr__( 'Elastic Slider', 'fusion-core' ),
							'parent_item_colon'        => esc_attr__( 'Parent Slide:', 'fusion-core' ),
							'all_items'                => esc_attr__( 'Add or Edit Slides', 'fusion-core' ),
							'view_item'                => esc_attr__( 'View Slides', 'fusion-core' ),
							'add_new_item'             => esc_attr__( 'Add New Slide', 'fusion-core' ),
							'add_new'                  => esc_attr__( 'Add New Slide', 'fusion-core' ),
							'edit_item'                => esc_attr__( 'Edit Slide', 'fusion-core' ),
							'update_item'              => esc_attr__( 'Update Slide', 'fusion-core' ),
							'search_items'             => esc_attr__( 'Search Slide', 'fusion-core' ),
							'not_found'                => esc_attr__( 'Not found', 'fusion-core' ),
							'not_found_in_trash'       => esc_attr__( 'Not found in Trash', 'fusion-core' ),
							'item_published'           => __( 'Slide published.', 'fusion-core' ),
							'item_published_privately' => __( 'Slide published privately.', 'fusion-core' ),
							'item_reverted_to_draft'   => __( 'Slide reverted to draft.', 'fusion-core' ),
							'item_scheduled'           => __( 'Slide scheduled.', 'fusion-core' ),
							'item_updated'             => __( 'Slide updated.', 'fusion-core' ),
						),
					)
				);

				register_taxonomy(
					'themefusion_es_groups',
					'themefusion_elastic',
					array(
						'hierarchical' => false,
						'query_var'    => true,
						'rewrite'      => true,
						'labels'       => array(
							'name'                       => _x( 'Groups', 'Taxonomy General Name', 'fusion-core' ),
							'singular_name'              => _x( 'Group', 'Taxonomy Singular Name', 'fusion-core' ),
							'menu_name'                  => esc_attr__( 'Add or Edit Groups', 'fusion-core' ),
							'all_items'                  => esc_attr__( 'All Groups', 'fusion-core' ),
							'parent_item_colon'          => esc_attr__( 'Parent Group:', 'fusion-core' ),
							'new_item_name'              => esc_attr__( 'New Group Name', 'fusion-core' ),
							'add_new_item'               => esc_attr__( 'Add Groups', 'fusion-core' ),
							'edit_item'                  => esc_attr__( 'Edit Group', 'fusion-core' ),
							'update_item'                => esc_attr__( 'Update Group', 'fusion-core' ),
							'separate_items_with_commas' => esc_attr__( 'Separate groups with commas', 'fusion-core' ),
							'search_items'               => esc_attr__( 'Search Groups', 'fusion-core' ),
							'add_or_remove_items'        => esc_attr__( 'Add or remove groups', 'fusion-core' ),
							'choose_from_most_used'      => esc_attr__( 'Choose from the most used groups', 'fusion-core' ),
							'not_found'                  => esc_attr__( 'Not Found', 'fusion-core' ),
						),
					)
				);
			}

			// qTranslate and mqTranslate custom post type support.
			if ( function_exists( 'qtrans_getLanguage' ) ) {
				add_action( 'portfolio_category_add_form', 'qtrans_modifyTermFormFor' );
				add_action( 'portfolio_category_edit_form', 'qtrans_modifyTermFormFor' );
				add_action( 'portfolio_skills_add_form', 'qtrans_modifyTermFormFor' );
				add_action( 'portfolio_skills_edit_form', 'qtrans_modifyTermFormFor' );
				add_action( 'portfolio_tags_add_form', 'qtrans_modifyTermFormFor' );
				add_action( 'portfolio_tags_edit_form', 'qtrans_modifyTermFormFor' );
				add_action( 'faq_category_edit_form', 'qtrans_modifyTermFormFor' );
			}

			// Check if flushing permalinks required and flush them.
			$flush_permalinks = get_option( 'fusion_core_flush_permalinks' );
			if ( ! $flush_permalinks ) {
				flush_rewrite_rules();
				update_option( 'fusion_core_flush_permalinks', true );
			}
		}

		/**
		 * Elastic Slider admin menu.
		 *
		 * @access public
		 */
		public function admin_menu() {
			global $submenu;
			unset( $submenu['edit.php?post_type=themefusion_elastic'][10] );
		}

		/**
		 * Load single portfolio template from FC.
		 *
		 * @access public
		 * @since 3.1
		 * @param string $single_post_template The post template.
		 * @return string
		 */
		public function portfolio_single_template( $single_post_template ) {
			global $post;

			// Check the post-type.
			if ( 'avada_portfolio' !== $post->post_type ) {
				return $single_post_template;
			}

			// The filename of the template.
			$filename = 'single-avada_portfolio.php';

			// Include template file from the theme if it exists.
			if ( locate_template( 'single-avada_portfolio.php' ) ) {
				return locate_template( 'single-avada_portfolio.php' );
			}

			// Include template file from the plugin.
			$single_portfolio_template = FUSION_CORE_PATH . '/templates/' . $filename;

			// Checks if the single post is portfolio.
			if ( file_exists( $single_portfolio_template ) ) {
				return $single_portfolio_template;
			}
			return $single_post_template;
		}

		/**
		 * Compares db and plugin versions and does stuff if needed.
		 *
		 * @access public
		 * @since 3.1.5
		 */
		public function versions_compare() {

			$db_version = get_option( 'fusion_core_version', false );

			if ( ! $db_version || FUSION_CORE_VERSION !== $db_version ) {

				// Run activation related steps.
				delete_option( 'fusion_core_flush_permalinks' );

				if ( class_exists( 'Fusion_Cache' ) ) {
					$fusion_cache = new Fusion_Cache();
					$fusion_cache->reset_all_caches();
				}
				fusion_core_enable_elements();

				// Update version in the database.
				update_option( 'fusion_core_version', FUSION_CORE_VERSION );
			}
		}

		/**
		 * Return post types to exclude from events calendar.
		 *
		 * @since 3.3.0
		 * @access public
		 * @param array $all_post_types All allowed post types in events calendar.
		 * @return array
		 */
		public function fusion_core_exclude_post_type( $all_post_types ) {

			unset( $all_post_types['slide'] );
			unset( $all_post_types['themefusion_elastic'] );

			return $all_post_types;
		}

		/**
		 * Set builder element dependencies, for those which involve EO.
		 *
		 * @since  3.3.0
		 * @param  array  $dependencies currently active dependencies.
		 * @param  string $shortcode name of shortcode.
		 * @param  string $option name of option.
		 * @return array  dependency checks.
		 */
		public function set_builder_dependencies( $dependencies, $shortcode, $option ) {

			global $fusion_settings;
			if ( ! $fusion_settings ) {
				$fusion_settings = Fusion_Settings::get_instance();
			}

			$shortcode_option_map = array();

			// Portfolio.
			$portfolio_is_single_column                                   = array(
				'check'  => array(
					'element-global' => 'portfolio_columns',
					'value'          => '1',
					'operator'       => '==',
				),
				'output' => array(
					'element'  => 'columns',
					'value'    => '',
					'operator' => '!=',
				),
			);
			$shortcode_option_map['column_spacing']['fusion_portfolio'][] = $portfolio_is_single_column;
			$shortcode_option_map['equal_heights']['fusion_portfolio'][]  = $portfolio_is_single_column;

			$shortcode_option_map['grid_element_color']['fusion_portfolio'][]        = array(
				'check'  => array(
					'element-global' => 'portfolio_text_layout',
					'value'          => 'boxed',
					'operator'       => '!=',
				),
				'output' => array(
					'element'  => 'text_layout',
					'value'    => 'default',
					'operator' => '!=',
				),
			);
			$shortcode_option_map['grid_box_color']['fusion_portfolio'][]            = array(
				'check'  => array(
					'element-global' => 'portfolio_text_layout',
					'value'          => 'no_text',
					'operator'       => '==',
				),
				'output' => array(
					'element'  => 'text_layout',
					'value'    => 'default',
					'operator' => '!=',
				),
			);
			$shortcode_option_map['grid_separator_style_type']['fusion_portfolio'][] = array(
				'check'  => array(
					'element-global' => 'portfolio_text_layout',
					'value'          => 'boxed',
					'operator'       => '!=',
				),
				'output' => array(
					'element'  => 'text_layout',
					'value'    => 'default',
					'operator' => '!=',
				),
			);
			$shortcode_option_map['grid_separator_color']['fusion_portfolio'][]      = array(
				'check'  => array(
					'element-global' => 'portfolio_text_layout',
					'value'          => 'boxed',
					'operator'       => '!=',
				),
				'output' => array(
					'element'  => 'text_layout',
					'value'    => 'default',
					'operator' => '!=',
				),
			);
			$shortcode_option_map['portfolio_layout_padding']['fusion_portfolio'][]  = array(
				'check'  => array(
					'element-global' => 'portfolio_text_layout',
					'value'          => 'unboxed',
					'operator'       => '==',
				),
				'output' => array(
					'element'  => 'text_layout',
					'value'    => 'default',
					'operator' => '!=',
				),
			);
			$shortcode_option_map['excerpt_length']['fusion_portfolio'][]            = array(
				'check'  => array(
					'element-global' => 'portfolio_content_length',
					'value'          => 'full_content',
					'operator'       => '==',
				),
				'output' => array(
					'element'  => 'content_length',
					'value'    => 'default',
					'operator' => '!=',
				),
			);
			$shortcode_option_map['excerpt_length']['fusion_portfolio'][]            = array(
				'check'  => array(
					'element-global' => 'portfolio_content_length',
					'value'          => 'excerpt',
					'operator'       => '!=',
				),
				'output' => array(
					'element'  => 'content_length',
					'value'    => 'default',
					'operator' => '!=',
				),
			);

			$shortcode_option_map['strip_html']['fusion_portfolio'][] = array(
				'check'  => array(
					'element-global' => 'portfolio_content_length',
					'value'          => 'full_content',
					'operator'       => '==',
				),
				'output' => array(
					'element'  => 'content_length',
					'value'    => 'default',
					'operator' => '!=',
				),
			);

			// FAQs.
			$shortcode_option_map['divider_line']['fusion_faq'][]     = array(
				'check'  => array(
					'element-global' => 'faq_accordion_boxed_mode',
					'value'          => '1',
					'operator'       => '==',
				),
				'output' => array(
					'element'  => 'boxed_mode',
					'value'    => '',
					'operator' => '!=',
				),
			);
			$shortcode_option_map['border_size']['fusion_faq'][]      = array(
				'check'  => array(
					'element-global' => 'faq_accordion_boxed_mode',
					'value'          => '0',
					'operator'       => '==',
				),
				'output' => array(
					'element'  => 'boxed_mode',
					'value'    => '',
					'operator' => '!=',
				),
			);
			$shortcode_option_map['border_color']['fusion_faq'][]     = array(
				'check'  => array(
					'element-global' => 'faq_accordion_boxed_mode',
					'value'          => '0',
					'operator'       => '==',
				),
				'output' => array(
					'element'  => 'boxed_mode',
					'value'    => '',
					'operator' => '!=',
				),
			);
			$shortcode_option_map['background_color']['fusion_faq'][] = array(
				'check'  => array(
					'element-global' => 'faq_accordion_boxed_mode',
					'value'          => '0',
					'operator'       => '==',
				),
				'output' => array(
					'element'  => 'boxed_mode',
					'value'    => '',
					'operator' => '!=',
				),
			);
			$shortcode_option_map['hover_color']['fusion_faq'][]      = array(
				'check'  => array(
					'element-global' => 'faq_accordion_boxed_mode',
					'value'          => '0',
					'operator'       => '==',
				),
				'output' => array(
					'element'  => 'boxed_mode',
					'value'    => '',
					'operator' => '!=',
				),
			);
			$shortcode_option_map['icon_box_color']['fusion_faq'][]   = array(
				'check'  => array(
					'element-global' => 'faq_accordion_icon_boxed',
					'value'          => '0',
					'operator'       => '==',
				),
				'output' => array(
					'element'  => 'icon_boxed_mode',
					'value'    => '',
					'operator' => '!=',
				),
			);

			// If has TO related dependency, do checks.
			if ( isset( $shortcode_option_map[ $option ][ $shortcode ] ) && is_array( $shortcode_option_map[ $option ][ $shortcode ] ) ) {
				foreach ( $shortcode_option_map[ $option ][ $shortcode ] as $option_check ) {
					$option_value = $fusion_settings->get( $option_check['check']['element-global'] );
					$pass         = false;

					// Check the result of check.
					if ( '==' === $option_check['check']['operator'] ) {
						$pass = ( $option_value == $option_check['check']['value'] ); // phpcs:ignore WordPress.PHP.StrictComparisons
					}
					if ( '!=' === $option_check['check']['operator'] ) {
						$pass = ( $option_value != $option_check['check']['value'] ); // phpcs:ignore WordPress.PHP.StrictComparisons
					}

					// If check passes then add dependency for checking.
					if ( $pass ) {
						$dependencies[] = $option_check['output'];
					}
				}
			}
			return $dependencies;
		}

		/**
		 * Returns equivalent global information for FB param.
		 *
		 * @since 3.3.0
		 * @param array $shortcode_option_map Shortcodes description map array.
		 */
		public function map_builder_descriptions( $shortcode_option_map ) {

			// Portfolio.
			$shortcode_option_map['portfolio_layout_padding']['fusion_portfolio']       = array(
				'theme-option' => 'portfolio_layout_padding',
				'subset'       => array( 'top', 'right', 'bottom', 'left' ),
			);
			$shortcode_option_map['picture_size']['fusion_portfolio']                   = array(
				'theme-option' => 'portfolio_featured_image_size',
				'type'         => 'select',
			);
			$shortcode_option_map['text_layout']['fusion_portfolio']                    = array(
				'theme-option' => 'portfolio_text_layout',
				'type'         => 'select',
			);
			$shortcode_option_map['portfolio_text_alignment']['fusion_portfolio']       = array(
				'theme-option' => 'portfolio_text_alignment',
				'type'         => 'select',
			);
			$shortcode_option_map['columns']['fusion_portfolio']                        = array(
				'theme-option' => 'portfolio_columns',
				'type'         => 'range',
			);
			$shortcode_option_map['column_spacing']['fusion_portfolio']                 = array(
				'theme-option' => 'portfolio_column_spacing',
				'type'         => 'range',
			);
			$shortcode_option_map['number_posts']['fusion_portfolio']                   = array(
				'theme-option' => 'portfolio_items',
				'type'         => 'range',
			);
			$shortcode_option_map['pagination_type']['fusion_portfolio']                = array(
				'theme-option' => 'portfolio_pagination_type',
				'type'         => 'select',
			);
			$shortcode_option_map['content_length']['fusion_portfolio']                 = array(
				'theme-option' => 'portfolio_content_length',
				'type'         => 'select',
			);
			$shortcode_option_map['excerpt_length']['fusion_portfolio']                 = array(
				'theme-option' => 'portfolio_excerpt_length',
				'type'         => 'range',
			);
			$shortcode_option_map['portfolio_title_display']['fusion_portfolio']        = array(
				'theme-option' => 'portfolio_title_display',
				'type'         => 'select',
			);
			$shortcode_option_map['strip_html']['fusion_portfolio']                     = array(
				'theme-option' => 'portfolio_strip_html_excerpt',
				'type'         => 'yesno',
			);
			$shortcode_option_map['grid_box_color']['fusion_portfolio']                 = array(
				'theme-option' => 'timeline_bg_color',
				'reset'        => true,
			);
			$shortcode_option_map['grid_element_color']['fusion_portfolio']             = array(
				'theme-option' => 'timeline_color',
				'reset'        => true,
			);
			$shortcode_option_map['grid_separator_style_type']['fusion_portfolio']      = array(
				'theme-option' => 'grid_separator_style_type',
				'type'         => 'select',
			);
			$shortcode_option_map['grid_separator_color']['fusion_portfolio']           = array(
				'theme-option' => 'grid_separator_color',
				'reset'        => true,
			);
			$shortcode_option_map['portfolio_masonry_grid_ratio']['fusion_portfolio']   = array(
				'theme-option' => 'masonry_grid_ratio',
				'type'         => 'range',
			);
			$shortcode_option_map['portfolio_masonry_width_double']['fusion_portfolio'] = array(
				'theme-option' => 'masonry_width_double',
				'type'         => 'range',
			);

			// FAQs.
			$shortcode_option_map['featured_image']['fusion_faq']            = array(
				'theme-option' => 'faq_featured_image',
				'type'         => 'yesno',
			);
			$shortcode_option_map['filters']['fusion_faq']                   = array(
				'theme-option' => 'faq_filters',
				'type'         => 'select',
			);
			$shortcode_option_map['type']['fusion_faq']                      = array(
				'theme-option' => 'faq_accordion_type',
				'type'         => 'select',
			);
			$shortcode_option_map['divider_line']['fusion_faq']              = array(
				'theme-option' => 'faq_accordion_divider_line',
				'type'         => 'yesno',
			);
			$shortcode_option_map['boxed_mode']['fusion_faq']                = array(
				'theme-option' => 'faq_accordion_boxed_mode',
				'type'         => 'yesno',
			);
			$shortcode_option_map['border_size']['fusion_faq']               = array(
				'theme-option' => 'faq_accordion_border_size',
				'type'         => 'range',
			);
			$shortcode_option_map['border_color']['fusion_faq']              = array(
				'theme-option' => 'faq_accordian_border_color',
				'reset'        => true,
			);
			$shortcode_option_map['background_color']['fusion_faq']          = array(
				'theme-option' => 'faq_accordian_background_color',
				'reset'        => true,
			);
			$shortcode_option_map['hover_color']['fusion_faq']               = array(
				'theme-option' => 'faq_accordian_hover_color',
				'reset'        => true,
			);
			$shortcode_option_map['title_font_size']['fusion_faq']           = array(
				'theme-option' => 'faq_accordion_title_font_size',
			);
			$shortcode_option_map['icon_size']['fusion_faq']                 = array(
				'theme-option' => 'faq_accordion_icon_size',
				'type'         => 'range',
			);
			$shortcode_option_map['icon_color']['fusion_faq']                = array(
				'theme-option' => 'faq_accordian_icon_color',
				'reset'        => true,
			);
			$shortcode_option_map['icon_boxed_mode']['fusion_faq']           = array(
				'theme-option' => 'faq_accordion_icon_boxed',
				'type'         => 'yesno',
			);
			$shortcode_option_map['icon_box_color']['fusion_faq']            = array(
				'theme-option' => 'faq_accordian_inactive_color',
				'reset'        => true,
			);
			$shortcode_option_map['icon_alignment']['fusion_faq']            = array(
				'theme-option' => 'faq_accordion_icon_align',
				'type'         => 'select',
			);
			$shortcode_option_map['toggle_hover_accent_color']['fusion_faq'] = array(
				'theme-option' => 'faq_accordian_active_color',
				'reset'        => true,
			);

			return $shortcode_option_map;
		}
	}
}

// Load the instance of the plugin.
add_action( 'plugins_loaded', array( 'FusionCore_Plugin', 'get_instance' ) );

/**
 * Setup Fusion Slider.
 *
 * @since 3.1
 * @return void
 */
function setup_fusion_slider() {
	global $fusion_settings;
	if ( ! $fusion_settings && class_exists( 'Fusion_Settings' ) ) {
		$fusion_settings = Fusion_Settings::get_instance();
	}

	if ( ! class_exists( 'Fusion_Settings' ) || '0' !== $fusion_settings->get( 'status_fusion_slider' ) ) {
		include_once FUSION_CORE_PATH . '/fusion-slider/class-fusion-slider.php';
	}
}
// Setup Fusion Slider.
add_action( 'after_setup_theme', 'setup_fusion_slider', 10 );

/**
 * Find and include all shortcodes within shortcodes folder.
 *
 * @since 3.1
 * @return void
 */
function fusion_init_shortcodes() {
	if ( class_exists( 'Avada' ) ) {
		foreach ( glob( plugin_dir_path( __FILE__ ) . '/shortcodes/*.php', GLOB_NOSORT ) as $filename ) {
			require_once wp_normalize_path( $filename );
		}
	}
}
// Load all shortcode elements.
add_action( 'fusion_builder_shortcodes_init', 'fusion_init_shortcodes' );

/**
 * Load portfolio archive template from FC.
 *
 * @access public
 * @since 3.1
 * @param string $archive_post_template The post template.
 * @return string
 */
function fusion_portfolio_archive_template( $archive_post_template ) {
	$archive_portfolio_template = FUSION_CORE_PATH . '/templates/archive-avada_portfolio.php';

	// Checks if the archive is portfolio.
	if ( is_post_type_archive( 'avada_portfolio' )
		|| is_tax( 'portfolio_category' )
		|| is_tax( 'portfolio_skills' )
		|| is_tax( 'portfolio_tags' ) ) {
		if ( file_exists( $archive_portfolio_template ) ) {
			fusion_portfolio_scripts();
			return $archive_portfolio_template;
		}
	}
	return $archive_post_template;
}

// Provide archive portfolio template via filter.
add_filter( 'archive_template', 'fusion_portfolio_archive_template' );

/**
 * Enable Fusion Builder elements on activation.
 *
 * @access public
 * @since 3.1
 * @return void
 */
function fusion_core_enable_elements() {
	if ( function_exists( 'fusion_builder_auto_activate_element' ) && version_compare( FUSION_BUILDER_VERSION, '1.0.6', '>' ) ) {
		fusion_builder_auto_activate_element( 'fusion_portfolio' );
		fusion_builder_auto_activate_element( 'fusion_faq' );
		fusion_builder_auto_activate_element( 'fusion_fusionslider' );
		fusion_builder_auto_activate_element( 'fusion_privacy' );
	}
}

register_activation_hook( __FILE__, 'fusion_core_activation' );
register_deactivation_hook( __FILE__, 'fusion_core_deactivation' );

/**
 * Runs on fusion core activation hook.
 */
function fusion_core_activation() {

	// Reset patcher on activation.
	fusion_core_reset_patcher_counter();

	// Enable fusion core elements on activation.
	fusion_core_enable_elements();
}

/**
 * Runs on fusion core deactivation hook.
 */
function fusion_core_deactivation() {
	// Reset patcher on deactivation.
	fusion_core_reset_patcher_counter();

	// Delete the option to flush rewrite rules after activation.
	delete_option( 'fusion_core_flush_permalinks' );
}

/**
 * Resets the patcher counters.
 */
function fusion_core_reset_patcher_counter() {
	delete_site_transient( 'fusion_patcher_check_num' );
}

/**
 * Instantiate the patcher class.
 */
function fusion_core_patcher_activation() {
	if ( class_exists( 'Fusion_Patcher' ) ) {
		new Fusion_Patcher(
			array(
				'context'     => 'fusion-core',
				'version'     => FUSION_CORE_VERSION,
				'name'        => 'Fusion-Core',
				'parent_slug' => 'avada',
				'page_title'  => esc_attr__( 'Fusion Patcher', 'fusion-core' ),
				'menu_title'  => esc_attr__( 'Fusion Patcher', 'fusion-core' ),
				'classname'   => 'FusionCore_Plugin',
			)
		);
	}
}
add_action( 'after_setup_theme', 'fusion_core_patcher_activation', 17 );

/**
 * Add content filter if WPTouch is active.
 *
 * @access public
 * @since 3.1.1
 * @return void
 */
function fusion_wptouch_compatiblity() {
	global $wptouch_pro;
	if ( true === $wptouch_pro->is_mobile_device ) {
		add_filter( 'the_content', 'fusion_remove_orphan_shortcodes', 0 );
	}
}
add_action( 'wptouch_pro_loaded', 'fusion_wptouch_compatiblity', 11 );

/**
 * Add custom thumnail column.
 *
 * @since 5.3
 * @access public
 * @param array $existing_columns Array of existing columns.
 * @return array The modified columns array.
 */
function fusion_wp_list_add_column( $existing_columns ) {

	if ( ! class_exists( 'Avada' ) ) {
		return $existing_columns;
	}

	$columns = array(
		'cb'           => $existing_columns['cb'],
		'tf_thumbnail' => '<span class="dashicons dashicons-format-image"></span>',
	);

	return array_merge( $columns, $existing_columns );
}
// Add thumbnails to blog, portfolio, FAQs, Fusion Slider and Elastic Slider.
add_filter( 'manage_post_posts_columns', 'fusion_wp_list_add_column', 10 );
add_filter( 'manage_avada_portfolio_posts_columns', 'fusion_wp_list_add_column', 10 );
add_filter( 'manage_avada_faq_posts_columns', 'fusion_wp_list_add_column', 10 );
add_filter( 'manage_slide_posts_columns', 'fusion_wp_list_add_column', 10 );
add_filter( 'manage_themefusion_elastic_posts_columns', 'fusion_wp_list_add_column', 10 );

/**
 * Renders the contents of the thumbnail column.
 *
 * @since 5.3
 * @access public
 * @param string $column current column name.
 * @param int    $post_id cureent post ID.
 * @return void
 */
function fusion_add_thumbnail_in_column( $column, $post_id ) {

	if ( ! class_exists( 'Avada' ) ) {
		return;
	}

	switch ( $column ) {
		case 'tf_thumbnail':
			echo '<a href="' . esc_url_raw( get_edit_post_link( $post_id ) ) . '">';
			if ( has_post_thumbnail( $post_id ) ) {
				echo get_the_post_thumbnail( $post_id, 'thumbnail' );
			} else {
				echo '<img  src="data:image/png;base64,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">';
			}
			echo '</a>';

			break;
	}
}
add_action( 'manage_post_posts_custom_column', 'fusion_add_thumbnail_in_column', 10, 2 );
add_action( 'manage_avada_portfolio_posts_custom_column', 'fusion_add_thumbnail_in_column', 10, 2 );
add_action( 'manage_avada_faq_posts_custom_column', 'fusion_add_thumbnail_in_column', 10, 2 );
add_action( 'manage_slide_posts_custom_column', 'fusion_add_thumbnail_in_column', 10, 2 );
add_action( 'manage_themefusion_elastic_posts_custom_column', 'fusion_add_thumbnail_in_column', 10, 2 );

/**
 * Removes unregistered shortcodes.
 *
 * @access public
 * @since 3.1.1
 * @param string $content item content.
 * @return string
 */
function fusion_remove_orphan_shortcodes( $content ) {

	if ( false === strpos( $content, '[fusion' ) ) {
		return $content;
	}

	global $shortcode_tags;

	// Check for active shortcodes.
	$active_shortcodes = ( is_array( $shortcode_tags ) && ! empty( $shortcode_tags ) ) ? array_keys( $shortcode_tags ) : array();

	// Avoid "/" chars in content breaks preg_replace.
	$unique_string_one = md5( microtime() );
	$content           = str_replace( '[/fusion_', $unique_string_one, $content );

	$unique_string_two = md5( microtime() + 1 );
	$content           = str_replace( '/fusion_', $unique_string_two, $content );
	$content           = str_replace( $unique_string_one, '[/fusion_', $content );

	if ( ! empty( $active_shortcodes ) ) {
		// Be sure to keep active shortcodes.
		$keep_active = implode( '|', $active_shortcodes );
		$content     = preg_replace( '~(?:\[/?)(?!(?:' . $keep_active . '))[^/\]]+/?\]~s', '', $content );
	} else {
		// Strip all shortcodes.
		$content = preg_replace( '~(?:\[/?)[^/\]]+/?\]~s', '', $content );

	}

	// Set "/" back to its place.
	$content = str_replace( $unique_string_two, '/', $content );

	return $content;
}

/**
 * Remove post type from the link selector.
 *
 * @since 1.0
 * @param array $query Default query for link selector.
 * @return array $query
 */
function fusion_core_wp_link_query_args( $query ) {

	// Get array key for the post type 'slide'.
	$slide_post_type_key = array_search( 'slide', $query['post_type'], true );

	// Remove the post type from query.
	if ( $slide_post_type_key ) {
		unset( $query['post_type'][ $slide_post_type_key ] );
	}

	// Get array key for the post type 'themefusion_elastic'.
	$elastic_slider_post_type_key = array_search( 'themefusion_elastic', $query['post_type'], true );

	// Remove the post type from query.
	if ( $elastic_slider_post_type_key ) {
		unset( $query['post_type'][ $elastic_slider_post_type_key ] );
	}

	// Return updated query.
	return $query;
}

add_filter( 'wp_link_query_args', 'fusion_core_wp_link_query_args' );
