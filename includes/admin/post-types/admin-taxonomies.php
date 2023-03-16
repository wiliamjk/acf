<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'ACF_Admin_Taxonomies' ) ) :

	/**
	 * The ACF Post Types admin controller class
	 */
	#[AllowDynamicProperties]
	class ACF_Admin_Taxonomies extends ACF_Admin_Internal_Post_Type_List {

		/**
		 * The slug for the internal post type.
		 *
		 * @since 6.1
		 * @var string
		 */
		public $post_type = 'acf-taxonomy';

		/**
		 * The admin body class used for the post type.
		 *
		 * @since 6.1
		 * @var string
		 */
		public $admin_body_class = 'acf-admin-taxonomies';

		/**
		 * The name of the store used for the post type.
		 *
		 * @var string
		 */
		public $store = 'taxonomies';

		/**
		 * Constructor for the taxonomies list admin page.
		 *
		 * @since   6.1
		 *
		 * @return  void
		 */
		public function current_screen() {
			// Bail early if not post types admin page.
			if ( ! acf_is_screen( "edit-{$this->post_type}" ) ) {
				return;
			}

			parent::current_screen();

			// Run a first-run routine to set some defaults which are stored in user preferences.
			if ( ! acf_get_user_setting( 'taxonomies-first-run', false ) ) {
				$option_key   = 'manageedit-' . $this->post_type . 'columnshidden';
				$hidden_items = get_user_option( $option_key );

				if ( ! is_array( $hidden_items ) ) {
					$hidden_items = array();
				}

				if ( ! in_array( 'acf-key', $hidden_items ) ) {
					$hidden_items[] = 'acf-key';
				}
				update_user_option( get_current_user_id(), $option_key, $hidden_items, true );

				acf_update_user_setting( 'taxonomies-first-run', true );
			}
		}

		/**
		 * Add any menu items required for taxonomies.
		 *
		 * @since 6.1
		 */
		public function admin_menu() {
			$parent_slug = 'edit.php?post_type=acf-field-group';
			$cap         = acf_get_setting( 'capability' );
			add_submenu_page( $parent_slug, __( 'Taxonomies', 'acf' ), __( 'Taxonomies', 'acf' ), $cap, 'edit.php?post_type=acf-taxonomy' );
		}

		/**
		 * Customizes the admin table columns.
		 *
		 * @date    1/4/20
		 * @since   5.9.0
		 *
		 * @param array $_columns The columns array.
		 * @return array
		 */
		public function admin_table_columns( $_columns ) {
			// Set the "no found" label to be our custom HTML for no results.
			global $wp_post_types;
			$this->not_found_label                                = $wp_post_types[ $this->post_type ]->labels->not_found;
			$wp_post_types[ $this->post_type ]->labels->not_found = $this->get_not_found_html();

			$columns = array(
				'cb'               => $_columns['cb'],
				'title'            => $_columns['title'],
				'acf-description'  => __( 'Description', 'acf' ),
				'acf-key'          => __( 'Key', 'acf' ),
				'acf-post-types'   => __( 'Post Types', 'acf' ),
				'acf-field-groups' => __( 'Field Groups', 'acf' ),
				'acf-count'        => __( 'Terms', 'acf' ),
			);

			if ( acf_get_local_json_files( $this->post_type ) ) {
				$columns['acf-json'] = __( 'Local JSON', 'acf' );
			}

			return $columns;
		}

		/**
		 * Renders a specific admin table column.
		 *
		 * @date    17/4/20
		 * @since   5.9.0
		 *
		 * @param string $column_name The name of the column to display.
		 * @param array  $post        The main ACF post array.
		 * @return void
		 */
		public function render_admin_table_column( $column_name, $post ) {
			switch ( $column_name ) {
				case 'acf-key':
					echo '<i class="acf-icon acf-icon-key-solid"></i>';
					echo esc_html( $post['key'] );
					break;

				// Description.
				case 'acf-description':
					if ( $post['description'] ) {
						echo '<span class="acf-description">' . acf_esc_html( $post['description'] ) . '</span>';
					}
					break;

				case 'acf-field-groups':
					$this->render_admin_table_column_field_groups( $post );
					break;

				case 'acf-post-types':
					$this->render_admin_table_column_post_types( $post );
					break;

				case 'acf-count':
					$num_terms = wp_count_terms(
						$post['taxonomy'],
						array(
							'hide_empty' => false,
							'parent'     => 0,
						)
					);

					echo esc_html( $num_terms );
					break;

				// Local JSON.
				case 'acf-json':
					$this->render_admin_table_column_local_status( $post );
					break;
			}
		}

		/**
		 * Renders the field groups attached to the taxonomy in the list table.
		 *
		 * @since 6.1
		 *
		 * @param array $taxonomy The main taxonomy array.
		 * @return void
		 */
		public function render_admin_table_column_field_groups( $taxonomy ) {
			$field_groups = acf_get_field_groups( array( 'taxonomy' => $taxonomy['taxonomy'] ) );

			if ( empty( $field_groups ) ) {
				return;
			}

			$labels        = wp_list_pluck( $field_groups, 'title' );
			$limit         = 3;
			$shown_labels  = array_slice( $labels, 0, $limit );
			$hidden_labels = array_slice( $labels, $limit );
			$text          = implode( ', ', $shown_labels );

			if ( ! empty( $hidden_labels ) ) {
				$text .= ', <span class="acf-more-items acf-tooltip-js" title="' . implode( ', ', $hidden_labels ) . '">+' . count( $hidden_labels ) . '</span>';
			}

			echo acf_esc_html( $text );
		}

		/**
		 * Renders the post types attached to the taxonomy in the list table.
		 *
		 * @since 6.1
		 *
		 * @param array $taxonomy The main taxonomy array.
		 * @return void
		 */
		public function render_admin_table_column_post_types( $taxonomy ) {
			$post_types   = get_post_types( array(), 'objects' );
			$labels       = array();
			$object_types = array();

			if ( ! empty( $taxonomy['object_type'] ) ) {
				$object_types = (array) $taxonomy['object_type'];
			}

			foreach ( $object_types as $post_type_slug ) {
				if ( ! isset( $post_types[ $post_type_slug ] ) ) {
					continue;
				}

				$post_type = $post_types[ $post_type_slug ];

				if ( empty( $post_type->label ) ) {
					continue;
				}

				$labels[] = $post_type->label;
			}

			$acf_post_types = acf_get_internal_post_type_posts( 'acf-post-type' );

			foreach ( $acf_post_types as $acf_post_type ) {
				if ( is_array( $acf_post_type['taxonomies'] ) && in_array( $taxonomy['taxonomy'], $acf_post_type['taxonomies'], true ) ) {
					$labels[] = $acf_post_type['title'];
				}
			}

			$labels        = array_unique( $labels );
			$limit         = 3;
			$shown_labels  = array_slice( $labels, 0, $limit );
			$hidden_labels = array_slice( $labels, $limit );
			$text          = implode( ', ', $shown_labels );

			if ( ! empty( $hidden_labels ) ) {
				$text .= ', <span class="acf-more-items acf-tooltip-js" title="' . implode( ', ', $hidden_labels ) . '">+' . count( $hidden_labels ) . '</span>';
			}

			echo acf_esc_html( $text );
		}

		/**
		 * Gets the translated action notice text for list table actions (activate, deactivate, sync, etc.).
		 *
		 * @since 6.1
		 *
		 * @param string $action The action being performed.
		 * @param int    $count  The number of items the action was performed on.
		 * @return string
		 */
		public function get_action_notice_text( $action, $count = 1 ) {
			$text  = '';
			$count = (int) $count;

			switch ( $action ) {
				case 'acfactivatecomplete':
					$text = sprintf(
						/* translators: %s number of taxonomies activated */
						_n( 'Taxonomy activated.', '%s taxonomies activated.', $count, 'acf' ),
						$count
					);
					break;
				case 'acfdeactivatecomplete':
					$text = sprintf(
						/* translators: %s number of taxonomies deactivated */
						_n( 'Taxonomy deactivated.', '%s taxonomies deactivated.', $count, 'acf' ),
						$count
					);
					break;
				case 'acfduplicatecomplete':
					$text = sprintf(
						/* translators: %s number of taxonomies duplicated */
						_n( 'Taxonomy duplicated.', '%s taxonomies duplicated.', $count, 'acf' ),
						$count
					);
					break;
				case 'acfsynccomplete':
					$text = sprintf(
						/* translators: %s number of taxonomies synchronised */
						_n( 'Taxonomy synchronised.', '%s taxonomies synchronised.', $count, 'acf' ),
						$count
					);
					break;
			}

			return $text;
		}

		/**
		 * Returns the registration error state.
		 *
		 * @since   6.1
		 *
		 * @return  string
		 */
		public function get_registration_error_state() {
			return '<span class="acf-js-tooltip dashicons dashicons-warning" title="' .
			__( 'This taxonomy could not be registered because its key is in use by another taxonomy registered by another plugin or theme.', 'acf' ) .
			'"></span> ' . _x( 'Registration Failed', 'post status', 'acf' );
		}

	}

	// Instantiate.
	acf_new_instance( 'ACF_Admin_Taxonomies' );

endif; // Class exists check.
