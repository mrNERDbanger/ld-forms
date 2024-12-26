<?php
/**
 * Handles user meta fields and WP Fusion integration
 *
 * @package LCCP_Foundations
 * @version 1.0.0
 */

class LCCP_User_Fields {
    /**
     * Initialize the user fields functionality
     */
    public static function init() {
        add_action('show_user_profile', array(__CLASS__, 'add_hours_completed_field'));
        add_action('edit_user_profile', array(__CLASS__, 'add_hours_completed_field'));
        add_action('personal_options_update', array(__CLASS__, 'save_hours_completed_field'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_hours_completed_field'));
        
        // WP Fusion integration
        add_filter('wpf_meta_fields', array(__CLASS__, 'add_hours_completed_to_wp_fusion'));
    }

    /**
     * Add Hours Completed field to user profile
     */
    public static function add_hours_completed_field($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        ?>
        <h3><?php _e('LCCP Program Information', 'lccp-foundations'); ?></h3>
        <table class="form-table">
            <tr>
                <th>
                    <label for="hours_completed"><?php _e('Hours Completed', 'lccp-foundations'); ?></label>
                </th>
                <td>
                    <input type="number"
                           name="hours_completed"
                           id="hours_completed"
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'hours_completed', true)); ?>"
                           class="regular-text"
                           min="0"
                           step="0.5"
                    />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save Hours Completed field
     */
    public static function save_hours_completed_field($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        if (isset($_POST['hours_completed'])) {
            $hours = sanitize_text_field($_POST['hours_completed']);
            update_user_meta($user_id, 'hours_completed', $hours);
        }
    }

    /**
     * Add Hours Completed field to WP Fusion
     */
    public static function add_hours_completed_to_wp_fusion($fields) {
        $fields['hours_completed'] = array(
            'type'  => 'number',
            'label' => 'Hours Completed',
            'group' => 'lccp_foundations'
        );
        
        return $fields;
    }
} 