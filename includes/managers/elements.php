<?php
namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Elements_Manager {
	/**
	 * @var Element_Base[]
	 */
	private $_element_types;

	private $_categories;

	public function __construct() {
		$this->require_files();

		add_action( 'wp_ajax_elementor_save_builder', [ $this, 'ajax_save_builder' ] );
	}

	/**
	 * @param array $element_data
	 *
	 * @return Element_Base
	 */
	public function create_element_instance( array $element_data ) {
		$args = [];

		if ( 'widget' === $element_data['elType'] ) {
			$element_type = Plugin::instance()->widgets_manager->get_widget_types( $element_data['widgetType'] );

			$args = $element_type->get_default_args();
		} else {
			$element_type = $this->get_element_types( $element_data['elType'] );
		}

		$element_class = $element_type->get_class_name();

		return new $element_class( $element_data, $args );
	}

	public function get_categories() {
		if ( null === $this->_categories ) {
			$this->init_categories();
		}

		return $this->_categories;
	}

	public function add_category( $category_name, $category_properties, $offset = null ) {
		if ( null === $this->_categories ) {
			$this->init_categories();
		}

		if ( null === $offset ) {
			$this->_categories[ $category_name ] = $category_properties;
		}

		$this->_categories = array_slice( $this->_categories, 0, $offset, true )
			+ [
				$category_name => $category_properties,
			]
			+ array_slice( $this->_categories, $offset, null, true );
	}

	public function register_element_type( Element_Base $element ) {
		$this->_element_types[ $element->get_name() ] = $element;

		return true;
	}

	public function unregister_element_type( $name ) {
		if ( ! isset( $this->_element_types[ $name ] ) ) {
			return false;
		}

		unset( $this->_element_types[ $name ] );

		return true;
	}

	public function get_element_types( $element_name = null ) {
		if ( is_null( $this->_element_types ) ) {
			$this->_init_elements();
		}

		if ( null !== $element_name ) {
			return isset( $this->_element_types[ $element_name ] ) ? $this->_element_types[ $element_name ] : null;
		}

		return $this->_element_types;
	}

	public function get_element_types_config() {
		$config = [];

		foreach ( $this->get_element_types() as $element ) {
			$config[ $element->get_name() ] = $element->get_config();
		}

		return $config;
	}

	public function render_elements_content() {
		foreach ( $this->get_element_types() as $element_type ) {
			$element_type->print_template();
		}
	}

	public function ajax_save_builder() {
		if ( empty( $_POST['_nonce'] ) || ! wp_verify_nonce( $_POST['_nonce'], 'elementor-editing' ) ) {
			wp_send_json_error( new \WP_Error( 'token_expired' ) );
		}

		if ( empty( $_POST['post_id'] ) ) {
			wp_send_json_error( new \WP_Error( 'no_post_id' ) );
		}

		if ( ! User::is_current_user_can_edit( $_POST['post_id'] ) ) {
			wp_send_json_error( new \WP_Error( 'no_access' ) );
		}

		if ( isset( $_POST['revision'] ) && DB::REVISION_PUBLISH === $_POST['revision'] ) {
			$revision = DB::REVISION_PUBLISH;
		} else {
			$revision = DB::REVISION_DRAFT;
		}

		$posted = json_decode( stripslashes( html_entity_decode( $_POST['data'] ) ), true );

		Plugin::instance()->db->save_editor( $_POST['post_id'], $posted, $revision );

		wp_send_json_success();
	}

	private function _init_elements() {
		$this->_element_types = [];

		foreach ( [ 'section', 'column' ] as $element_name ) {
			$class_name = __NAMESPACE__ . '\Element_' . $element_name;

			$this->register_element_type( new $class_name() );
		}

		do_action( 'elementor/elements/elements_registered' );
	}

	private function init_categories() {
		$this->_categories = [
			'basic' => [
				'title' => __( 'Basic', 'elementor' ),
				'icon' => 'font',
			],
			'general-elements' => [
				'title' => __( 'General Elements', 'elementor' ),
				'icon' => 'font',
			],
			'pojo' => [
				'title' => __( 'Pojo Themes', 'elementor' ),
				'icon' => 'pojome',
			],
			'wordpress' => [
				'title' => __( 'WordPress', 'elementor' ),
				'icon' => 'wordpress',
			],
		];
	}

	private function require_files() {
		require_once ELEMENTOR_PATH . 'includes/base/element-base.php';

		require ELEMENTOR_PATH . 'includes/elements/column.php';
		require ELEMENTOR_PATH . 'includes/elements/section.php';
		require ELEMENTOR_PATH . 'includes/elements/repeater.php';
	}
}
