<?php
/**
 * Handles creation and management of custom roles
 *
 * @package LCCP_Foundations
 * @version 1.0.0
 */

class LCCP_Roles {
    /**
     * Initialize the roles functionality
     */
    public static function init() {
        add_filter('editable_roles', array(__CLASS__, 'rename_subscriber_role'));
        add_filter('wp_roles_init', array(__CLASS__, 'rename_subscriber_role_display'));
    }

    /**
     * Create custom roles
     */
    public static function create_roles() {
        // Add Mentor role
        add_role(
            'mentor',
            __('Mentor', 'lccp-foundations'),
            array(
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
                'publish_posts' => false,
                'upload_files' => true,
            )
        );

        // Add Big Bird role
        add_role(
            'big_bird',
            __('Big Bird', 'lccp-foundations'),
            array(
                'read' => true,
                'edit_posts' => true,
                'delete_posts' => true,
                'publish_posts' => true,
                'upload_files' => true,
            )
        );
    }

    /**
     * Rename the Subscriber role to Program Candidate
     */
    public static function rename_subscriber_role($roles) {
        if (isset($roles['subscriber'])) {
            $roles['subscriber'] = 'Program Candidate';
        }
        return $roles;
    }

    /**
     * Change the display name of Subscriber to PC
     */
    public static function rename_subscriber_role_display($wp_roles) {
        if (isset($wp_roles->roles['subscriber'])) {
            $wp_roles->roles['subscriber']['name'] = 'PC';
            $wp_roles->role_names['subscriber'] = 'PC';
        }
        return $wp_roles;
    }
} 