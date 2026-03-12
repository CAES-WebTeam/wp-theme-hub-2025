<?php

/**
 * ACF Field Groups for the caes_hub_person CPT.
 *
 * These are duplicates of the user-targeted field groups, re-keyed to target
 * the caes_hub_person post type. The original user field groups remain untouched
 * until migration is fully verified.
 *
 * Field groups duplicated:
 * 1. "Users" (from theme code: user-field-group.php)
 * 2. "Symplectic Elements" (from theme code: user-field-group.php)
 * 3. "Editorial" (from theme code: user-field-group.php)
 * 4. "Expert/Source" (from WP admin, see acf-export-2026-03-10.json)
 * 5. "Writer" (from WP admin, see acf-export-2026-03-10.json)
 *
 * Plus a new "Person CPT Link" group for linking editorial staff to their WP user.
 */

add_action('acf/include_fields', function () {
	if (! function_exists('acf_add_local_field_group')) {
		return;
	}

	// =========================================================================
	// Field Group 1: "Personnel" (duplicated from "Users" user field group)
	// =========================================================================
	acf_add_local_field_group(array(
		'key' => 'group_person_cpt_personnel',
		'title' => 'Personnel',
		'fields' => array(
			array(
				'key' => 'field_person_cpt_first_name',
				'label' => 'First Name',
				'name' => 'first_name',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '33', 'class' => '', 'id' => ''),
				'default_value' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
				'maxlength' => '',
			),
			array(
				'key' => 'field_person_cpt_last_name',
				'label' => 'Last Name',
				'name' => 'last_name',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '33', 'class' => '', 'id' => ''),
				'default_value' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
				'maxlength' => '',
			),
			array(
				'key' => 'field_person_cpt_display_name',
				'label' => 'Display Name',
				'name' => 'display_name',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => 'The preferred name for display on the site. Synced from the personnel database.',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '34', 'class' => '', 'id' => ''),
				'default_value' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
				'maxlength' => '',
			),
			array(
				'key' => 'field_person_cpt_personnel_id',
				'label' => 'Personnel ID',
				'name' => 'personnel_id',
				'aria-label' => '',
				'type' => 'number',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'min' => '',
				'max' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'step' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_college_id',
				'label' => 'College ID',
				'name' => 'college_id',
				'aria-label' => '',
				'type' => 'number',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'min' => '',
				'max' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'step' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_uga_email',
				'label' => 'UGA Email',
				'name' => 'uga_email',
				'aria-label' => '',
				'type' => 'email',
				'instructions' => 'Original email address from the UGA personnel API',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_title',
				'label' => 'Title',
				'name' => 'title',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_department',
				'label' => 'Department',
				'name' => 'department',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_program_area',
				'label' => 'Program Area',
				'name' => 'program_area',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_phone_number',
				'label' => 'Phone Number',
				'name' => 'phone_number',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_cell_phone_number',
				'label' => 'Cell Phone Number',
				'name' => 'cell_phone_number',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_fax_number',
				'label' => 'Fax Number',
				'name' => 'fax_number',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_caes_location_id',
				'label' => 'CAES Location ID',
				'name' => 'caes_location_id',
				'aria-label' => '',
				'type' => 'number',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'min' => '',
				'max' => '',
				'allow_in_bindings' => 1,
				'placeholder' => '',
				'step' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_image_name',
				'label' => 'Image Name',
				'name' => 'image_name',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_mailing_address',
				'label' => 'Mailing Address',
				'name' => 'mailing_address',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_mailing_address2',
				'label' => 'Mailing Address2',
				'name' => 'mailing_address2',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_mailing_city',
				'label' => 'Mailing City',
				'name' => 'mailing_city',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '50', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_mailing_state',
				'label' => 'Mailing State',
				'name' => 'mailing_state',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '20', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_mailing_zip',
				'label' => 'Mailing Zip',
				'name' => 'mailing_zip',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '30', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_shipping_address',
				'label' => 'Shipping Address',
				'name' => 'shipping_address',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_shipping_address2',
				'label' => 'Shipping Address2',
				'name' => 'shipping_address2',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_shipping_city',
				'label' => 'Shipping City',
				'name' => 'shipping_city',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '50', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_shipping_state',
				'label' => 'Shipping State',
				'name' => 'shipping_state',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '20', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_shipping_zip',
				'label' => 'Shipping Zip',
				'name' => 'shipping_zip',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '30', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'caes_hub_person',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
		'show_in_rest' => 0,
	));

	// =========================================================================
	// Field Group 2: "Symplectic Elements" (duplicated from user field group)
	// =========================================================================
	acf_add_local_field_group(array(
		'key' => 'group_person_cpt_symplectic',
		'title' => 'Symplectic Elements',
		'fields' => array(
			array(
				'key' => 'field_person_cpt_elements_user_id',
				'label' => 'Elements User ID',
				'name' => 'elements_user_id',
				'type' => 'number',
				'instructions' => 'Synced automatically from Symplectic Elements.',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'min' => '',
				'max' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'step' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_elements_overview',
				'label' => 'Overview',
				'name' => 'elements_overview',
				'type' => 'textarea',
				'instructions' => 'Synced automatically from Symplectic Elements.',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'maxlength' => '',
				'rows' => 6,
				'new_lines' => 'wpautop',
			),
			array(
				'key' => 'field_person_cpt_elements_areas_of_expertise',
				'label' => 'Areas of Expertise',
				'name' => 'elements_areas_of_expertise',
				'type' => 'taxonomy',
				'instructions' => 'Synced automatically from Symplectic Elements. Terms are drawn from the Fields of Research (FOR/ANZSRC) taxonomy.',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'taxonomy' => 'areas_of_expertise',
				'field_type' => 'multi_select',
				'allow_null' => 1,
				'add_term' => 1,
				'save_terms' => 0,
				'load_terms' => 0,
				'return_format' => 'id',
				'multiple' => 1,
				'allow_in_bindings' => 0,
			),
			array(
				'key' => 'field_person_cpt_elements_scholarly_works',
				'label' => 'Scholarly Works',
				'name' => 'elements_scholarly_works',
				'type' => 'repeater',
				'instructions' => 'Synced automatically from Symplectic Elements. Top 5 by citation count.',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'layout' => 'block',
				'min' => 0,
				'max' => 5,
				'allow_in_bindings' => 0,
				'sub_fields' => array(
					array(
						'key' => 'field_person_cpt_pub_title',
						'label' => 'Title',
						'name' => 'pub_title',
						'type' => 'text',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
						'default_value' => '',
						'maxlength' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
					),
					array(
						'key' => 'field_person_cpt_pub_type',
						'label' => 'Type',
						'name' => 'pub_type',
						'type' => 'text',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
						'default_value' => '',
						'maxlength' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
					),
					array(
						'key' => 'field_person_cpt_pub_journal',
						'label' => 'Journal',
						'name' => 'pub_journal',
						'type' => 'text',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
						'default_value' => '',
						'maxlength' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
					),
					array(
						'key' => 'field_person_cpt_pub_doi',
						'label' => 'DOI',
						'name' => 'pub_doi',
						'type' => 'text',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
						'default_value' => '',
						'maxlength' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
					),
					array(
						'key' => 'field_person_cpt_pub_year',
						'label' => 'Year',
						'name' => 'pub_year',
						'type' => 'text',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array('width' => '25', 'class' => '', 'id' => ''),
						'default_value' => '',
						'maxlength' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
					),
					array(
						'key' => 'field_person_cpt_pub_citation_count',
						'label' => 'Citation Count',
						'name' => 'pub_citation_count',
						'type' => 'number',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array('width' => '25', 'class' => '', 'id' => ''),
						'default_value' => '',
						'min' => '',
						'max' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'step' => '',
						'prepend' => '',
						'append' => '',
					),
				),
			),
			array(
				'key' => 'field_person_cpt_elements_distinctions',
				'label' => 'Distinctions',
				'name' => 'elements_distinctions',
				'type' => 'repeater',
				'instructions' => 'Synced automatically from Symplectic Elements.',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'layout' => 'block',
				'min' => 0,
				'max' => 0,
				'allow_in_bindings' => 0,
				'sub_fields' => array(
					array(
						'key' => 'field_person_cpt_distinction_title',
						'label' => 'Title',
						'name' => 'distinction_title',
						'type' => 'text',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
						'default_value' => '',
						'maxlength' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
					),
					array(
						'key' => 'field_person_cpt_distinction_date',
						'label' => 'Date',
						'name' => 'distinction_date',
						'type' => 'text',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array('width' => '25', 'class' => '', 'id' => ''),
						'default_value' => '',
						'maxlength' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
					),
					array(
						'key' => 'field_person_cpt_distinction_description',
						'label' => 'Description',
						'name' => 'distinction_description',
						'type' => 'textarea',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
						'default_value' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'maxlength' => '',
						'rows' => 3,
						'new_lines' => 'wpautop',
					),
				),
			),
			array(
				'key' => 'field_person_cpt_elements_courses_taught',
				'label' => 'Courses Taught',
				'name' => 'elements_courses_taught',
				'type' => 'repeater',
				'instructions' => 'Synced automatically from Symplectic Elements. Courses from the past year only.',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'layout' => 'table',
				'min' => 0,
				'max' => 0,
				'allow_in_bindings' => 0,
				'sub_fields' => array(
					array(
						'key' => 'field_person_cpt_course_title',
						'label' => 'Course Title',
						'name' => 'course_title',
						'type' => 'text',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
						'default_value' => '',
						'maxlength' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
					),
					array(
						'key' => 'field_person_cpt_course_code',
						'label' => 'Course Code',
						'name' => 'course_code',
						'type' => 'text',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array('width' => '25', 'class' => '', 'id' => ''),
						'default_value' => '',
						'maxlength' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
					),
					array(
						'key' => 'field_person_cpt_course_term',
						'label' => 'Term',
						'name' => 'course_term',
						'type' => 'text',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array('width' => '25', 'class' => '', 'id' => ''),
						'default_value' => '',
						'maxlength' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
					),
				),
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'caes_hub_person',
				),
			),
		),
		'menu_order' => 1,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
		'show_in_rest' => 0,
	));

	// =========================================================================
	// Field Group 3: "Editorial" (duplicated from user field group)
	// =========================================================================
	acf_add_local_field_group(array(
		'key' => 'group_person_cpt_editorial',
		'title' => 'Editorial',
		'fields' => array(
			array(
				'key' => 'field_person_cpt_public_friendly_title',
				'label' => 'Public Friendly Title',
				'name' => 'public_friendly_title',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => 'A public-facing title that will be displayed on articles and expert resources. Not currently used on profile pages.',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => 'e.g., Extension Specialist, Research Director',
				'prepend' => '',
				'append' => '',
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'caes_hub_person',
				),
			),
		),
		'menu_order' => -1,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
		'show_in_rest' => 0,
	));

	// =========================================================================
	// Field Group 4: "Expert/Source" (duplicated from WP admin field group)
	// See acf-export-2026-03-10.json for original definitions
	// =========================================================================
	acf_add_local_field_group(array(
		'key' => 'group_person_cpt_expert_source',
		'title' => 'Expert/Source',
		'fields' => array(
			array(
				'key' => 'field_person_cpt_source_expert_id',
				'label' => 'Source Expert ID',
				'name' => 'source_expert_id',
				'aria-label' => '',
				'type' => 'number',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'min' => '',
				'max' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'step' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_description',
				'label' => 'Description',
				'name' => 'description',
				'aria-label' => '',
				'type' => 'textarea',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'rows' => 2,
				'placeholder' => '',
				'new_lines' => '',
			),
			array(
				'key' => 'field_person_cpt_area_of_expertise',
				'label' => 'Area of Expertise',
				'name' => 'area_of_expertise',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_is_source',
				'label' => 'Is Source',
				'name' => 'is_source',
				'aria-label' => '',
				'type' => 'true_false',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'message' => '',
				'default_value' => 0,
				'allow_in_bindings' => 1,
				'ui_on_text' => '',
				'ui_off_text' => '',
				'ui' => 1,
			),
			array(
				'key' => 'field_person_cpt_is_expert',
				'label' => 'Is Expert',
				'name' => 'is_expert',
				'aria-label' => '',
				'type' => 'true_false',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'message' => '',
				'default_value' => 0,
				'allow_in_bindings' => 0,
				'ui_on_text' => '',
				'ui_off_text' => '',
				'ui' => 1,
			),
			array(
				'key' => 'field_person_cpt_is_active',
				'label' => 'Is Active',
				'name' => 'is_active',
				'aria-label' => '',
				'type' => 'true_false',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'message' => '',
				'default_value' => 0,
				'allow_in_bindings' => 0,
				'ui_on_text' => '',
				'ui_off_text' => '',
				'ui' => 1,
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'caes_hub_person',
				),
			),
		),
		'menu_order' => 2,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
		'show_in_rest' => 0,
	));

	// =========================================================================
	// Field Group 5: "Writer" (duplicated from WP admin field group)
	// See acf-export-2026-03-10.json for original definitions
	// =========================================================================
	acf_add_local_field_group(array(
		'key' => 'group_person_cpt_writer',
		'title' => 'Writer',
		'fields' => array(
			array(
				'key' => 'field_person_cpt_writer_id',
				'label' => 'Writer ID',
				'name' => 'writer_id',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_tagline',
				'label' => 'Tagline',
				'name' => 'tagline',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_coverage_area',
				'label' => 'Coverage Area',
				'name' => 'coverage_area',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'default_value' => '',
				'maxlength' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_person_cpt_is_proofer',
				'label' => 'Is Proofer',
				'name' => 'is_proofer',
				'aria-label' => '',
				'type' => 'true_false',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'message' => '',
				'default_value' => 0,
				'allow_in_bindings' => 0,
				'ui_on_text' => '',
				'ui_off_text' => '',
				'ui' => 1,
			),
			array(
				'key' => 'field_person_cpt_is_media_contact',
				'label' => 'Is Media Contact',
				'name' => 'is_media_contact',
				'aria-label' => '',
				'type' => 'true_false',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'message' => '',
				'default_value' => 0,
				'allow_in_bindings' => 0,
				'ui_on_text' => '',
				'ui_off_text' => '',
				'ui' => 1,
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'caes_hub_person',
				),
			),
		),
		'menu_order' => 3,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
		'show_in_rest' => 0,
	));

	// =========================================================================
	// Field Group 6: "Person CPT Link" (NEW - links editorial staff to WP user)
	// =========================================================================
	acf_add_local_field_group(array(
		'key' => 'group_person_cpt_link',
		'title' => 'Linked WordPress User',
		'fields' => array(
			array(
				'key' => 'field_person_cpt_linked_wp_user',
				'label' => 'Linked WP User',
				'name' => 'linked_wp_user',
				'aria-label' => '',
				'type' => 'user',
				'instructions' => 'For editorial staff only. Links this person record to a WordPress user account for authentication and content authoring.',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
				'role' => '',
				'return_format' => 'id',
				'multiple' => 0,
				'allow_null' => 1,
				'allow_in_bindings' => 0,
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'caes_hub_person',
				),
			),
		),
		'menu_order' => -2,
		'position' => 'side',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
		'show_in_rest' => 0,
	));
});

// =============================================================================
// Read-only fields for synced field groups (Personnel + Symplectic Elements)
// =============================================================================

/**
 * Make all fields in the Personnel and Symplectic Elements groups read-only.
 * These are populated by scheduled syncs and any manual changes would be overwritten.
 */
add_filter('acf/prepare_field', 'person_cpt_readonly_synced_fields');
function person_cpt_readonly_synced_fields($field)
{
	if (!$field) {
		return $field;
	}

	// Only apply on the caes_hub_person edit screen
	$screen = get_current_screen();
	if (!$screen || $screen->post_type !== 'caes_hub_person') {
		return $field;
	}

	// Field keys belonging to the Personnel and Symplectic Elements groups
	$readonly_groups = array(
		'group_person_cpt_personnel',
		'group_person_cpt_symplectic',
	);

	if (isset($field['parent']) && in_array($field['parent'], $readonly_groups, true)) {
		$field['readonly'] = 1;
		$field['disabled'] = 1;
	}

	// Also catch repeater sub-fields (their parent is the repeater field key, not the group)
	$synced_repeater_keys = array(
		'field_person_cpt_elements_scholarly_works',
		'field_person_cpt_elements_distinctions',
		'field_person_cpt_elements_courses_taught',
	);

	if (isset($field['parent']) && in_array($field['parent'], $synced_repeater_keys, true)) {
		$field['readonly'] = 1;
		$field['disabled'] = 1;
	}

	return $field;
}

// =============================================================================
// Hide Expert/Source and Writer field groups from non-admin users
// =============================================================================

add_action('add_meta_boxes', 'person_cpt_hide_legacy_groups_from_non_admins', 99);
function person_cpt_hide_legacy_groups_from_non_admins()
{
	if (current_user_can('manage_options')) {
		return;
	}

	remove_meta_box('acf-group_person_cpt_expert_source', 'caes_hub_person', 'normal');
	remove_meta_box('acf-group_person_cpt_writer', 'caes_hub_person', 'normal');
}

// =============================================================================
// Collapse Personnel and Symplectic Elements metaboxes by default
// =============================================================================

add_filter('postbox_classes_caes_hub_person_acf-group_person_cpt_personnel', 'person_cpt_collapse_synced_metabox');
add_filter('postbox_classes_caes_hub_person_acf-group_person_cpt_symplectic', 'person_cpt_collapse_synced_metabox');
add_filter('postbox_classes_caes_hub_person_acf-group_person_cpt_expert_source', 'person_cpt_collapse_synced_metabox');
add_filter('postbox_classes_caes_hub_person_acf-group_person_cpt_writer', 'person_cpt_collapse_synced_metabox');
function person_cpt_collapse_synced_metabox($classes)
{
	if (!in_array('closed', $classes)) {
		$classes[] = 'closed';
	}
	return $classes;
}

// =============================================================================
// Position all ACF field groups above Yoast SEO + hide repeater controls
// =============================================================================

add_action('admin_enqueue_scripts', 'person_cpt_admin_styles');
function person_cpt_admin_styles($hook)
{
	if (!in_array($hook, ['post.php', 'post-new.php'])) {
		return;
	}

	$screen = get_current_screen();
	if (!$screen || $screen->post_type !== 'caes_hub_person') {
		return;
	}

	wp_add_inline_style('acf-input', '
		/* Ensure metaboxes fully contain their ACF field content */
		#acf-group_person_cpt_personnel .inside,
		#acf-group_person_cpt_symplectic .inside {
			overflow: hidden;
		}

		/* Synced field groups: hide repeater add/remove buttons and row handles */
		#acf-group_person_cpt_personnel .acf-actions,
		#acf-group_person_cpt_symplectic .acf-actions,
		#acf-group_person_cpt_personnel .acf-row-handle .acf-icon,
		#acf-group_person_cpt_symplectic .acf-row-handle .acf-icon,
		#acf-group_person_cpt_personnel a[data-event="add-row"],
		#acf-group_person_cpt_symplectic a[data-event="add-row"],
		#acf-group_person_cpt_personnel a[data-event="remove-row"],
		#acf-group_person_cpt_symplectic a[data-event="remove-row"] {
			display: none !important;
		}

		/* Synced field groups: visual indicator that fields are managed by sync */
		#acf-group_person_cpt_personnel .acf-fields,
		#acf-group_person_cpt_symplectic .acf-fields {
			opacity: 0.85;
		}

		/* Synced group notice */
		.person-cpt-sync-notice {
			background: #f0f6fc;
			border-left: 4px solid #72aee6;
			padding: 8px 12px;
			margin: 10px 0;
			font-size: 13px;
		}
	');
}

/**
 * Add a notice inside synced field group metaboxes on the post edit screen.
 */
add_action('edit_form_after_title', 'person_cpt_synced_group_notices');
function person_cpt_synced_group_notices()
{
	$screen = get_current_screen();
	if (!$screen || $screen->post_type !== 'caes_hub_person') {
		return;
	}

	?>
	<script>
	jQuery(document).ready(function($) {
		// Move Yoast SEO metabox below all ACF field groups
		var $yoast = $('#wpseo_meta');
		if ($yoast.length) {
			$yoast.parent().append($yoast);
		}

		// Sync notice for Personnel and Symplectic Elements
		var syncNotice = '<div class="person-cpt-sync-notice">These fields are populated by a scheduled import and cannot be edited manually. Any changes would be overwritten by the next sync.</div>';
		$('#acf-group_person_cpt_personnel .inside').prepend(syncNotice);
		$('#acf-group_person_cpt_symplectic .inside').prepend(syncNotice);

		// Static notice for Expert/Source and Writer
		var staticNotice = '<div class="person-cpt-sync-notice">These fields were imported from a legacy database and are no longer updated by a scheduled sync. They are preserved as-is for reference. This section is only visible to administrators.</div>';
		$('#acf-group_person_cpt_expert_source .inside').prepend(staticNotice);
		$('#acf-group_person_cpt_writer .inside').prepend(staticNotice);
	});
	</script>
	<?php
}
