<?php
/**
 * LCCP Forms Class
 *
 * @package LCCP_Foundations
 */

class LCCP_Forms {
    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('admin_menu', array($this, 'add_import_page'));
        add_action('admin_menu', array($this, 'add_submissions_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_lccp_import_form', array($this, 'handle_form_import'));
        add_action('wp_ajax_lccp_download_submission_pdf', array($this, 'handle_submission_pdf_download'));
        add_action('add_meta_boxes', array($this, 'add_form_meta_boxes'));
        add_action('save_post_lccp_form', array($this, 'save_form_settings'));
        add_action('wp_ajax_lccp_submit_form', array($this, 'handle_form_submission'));
        add_action('wp_ajax_lccp_get_course_lessons', array($this, 'get_course_lessons'));
        add_filter('template_include', array($this, 'form_templates'));
        add_filter('learndash_content', array($this, 'inject_form_content'), 10, 2);
        
        // Register BuddyBoss email templates
        add_action('bp_core_install_emails', array($this, 'register_email_templates'));
        add_action('bp_init', array($this, 'register_email_templates'));
    }

    /**
     * Register the Forms post type
     */
    public function register_post_type() {
        $labels = array(
            'name'               => __('Forms', 'lccp-foundations'),
            'singular_name'      => __('Form', 'lccp-foundations'),
            'menu_name'          => __('Forms', 'lccp-foundations'),
            'add_new'           => __('Add New', 'lccp-foundations'),
            'add_new_item'      => __('Add New Form', 'lccp-foundations'),
            'edit_item'         => __('Edit Form', 'lccp-foundations'),
            'new_item'          => __('New Form', 'lccp-foundations'),
            'view_item'         => __('View Form', 'lccp-foundations'),
            'search_items'      => __('Search Forms', 'lccp-foundations'),
            'not_found'         => __('No forms found', 'lccp-foundations'),
            'not_found_in_trash'=> __('No forms found in Trash', 'lccp-foundations'),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-feedback',
            'menu_position'      => 51,
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'supports'           => array('title', 'editor', 'custom-fields'),
            'has_archive'        => true,
            'rewrite'           => array('slug' => 'forms'),
            'show_in_rest'      => true,
        );

        register_post_type('lccp_form', $args);

        // Check and update database table
        $this->maybe_update_db_table();

        // Add LearnDash integration if available
        if (defined('LEARNDASH_VERSION')) {
            add_filter('learndash_post_args', array($this, 'add_form_to_learndash'));
            add_action('admin_menu', array($this, 'adjust_menu_for_learndash'));
        }
    }

    /**
     * Add form to LearnDash if available
     */
    public function add_form_to_learndash($args) {
        $args['lccp_form'] = array(
            'plugin_name' => __('Forms', 'lccp-foundations'),
            'slug_name' => 'forms',
            'post_type' => 'lccp_form',
            'template_redirect' => true,
        );
        return $args;
    }

    /**
     * Adjust menu for LearnDash integration
     */
    public function adjust_menu_for_learndash() {
        // Remove standalone menu
        remove_menu_page('edit.php?post_type=lccp_form');
        
        // Add under LearnDash
        add_submenu_page(
            'learndash-lms',
            __('Forms', 'lccp-foundations'),
            __('Forms', 'lccp-foundations'),
            'edit_posts',
            'edit.php?post_type=lccp_form'
        );
    }

    /**
     * Check and update database table if necessary
     */
    private function maybe_update_db_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'lccp_form_submissions';
        
        // Current schema version
        $current_version = get_option('lccp_forms_db_version', '0');
        
        // Define schema versions and their changes
        $schema_versions = array(
            '1.0' => array(
                'sql' => "CREATE TABLE IF NOT EXISTS $table_name (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    form_id bigint(20) NOT NULL,
                    user_id bigint(20) NOT NULL,
                    submission_data longtext NOT NULL,
                    submission_date datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY form_id (form_id),
                    KEY user_id (user_id)
                ) {$wpdb->get_charset_collate()};",
                'migrations' => array()
            ),
            '1.1' => array(
                'sql' => "ALTER TABLE $table_name 
                    ADD COLUMN status varchar(50) DEFAULT 'submitted' AFTER submission_date,
                    ADD COLUMN last_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER status,
                    ADD KEY status (status)",
                'migrations' => array(
                    "UPDATE $table_name SET status = 'submitted' WHERE status IS NULL"
                )
            ),
            '1.2' => array(
                'sql' => "ALTER TABLE $table_name 
                    ADD COLUMN course_id bigint(20) DEFAULT NULL AFTER form_id,
                    ADD COLUMN lesson_id bigint(20) DEFAULT NULL AFTER course_id,
                    ADD KEY course_id (course_id),
                    ADD KEY lesson_id (lesson_id)",
                'migrations' => array(
                    // Migrate existing course/lesson data
                    "UPDATE $table_name s 
                     JOIN {$wpdb->postmeta} pm1 ON s.form_id = pm1.post_id AND pm1.meta_key = '_ld_course_id'
                     SET s.course_id = pm1.meta_value",
                    "UPDATE $table_name s 
                     JOIN {$wpdb->postmeta} pm2 ON s.form_id = pm2.post_id AND pm2.meta_key = '_ld_lesson_id'
                     SET s.lesson_id = pm2.meta_value"
                )
            ),
            '1.3' => array(
                'sql' => "ALTER TABLE $table_name 
                    ADD COLUMN notification_sent tinyint(1) DEFAULT 0 AFTER status,
                    ADD COLUMN pdf_generated tinyint(1) DEFAULT 0 AFTER notification_sent,
                    ADD KEY notification_sent (notification_sent),
                    ADD KEY pdf_generated (pdf_generated)",
                'migrations' => array(
                    "UPDATE $table_name SET notification_sent = 1, pdf_generated = 1"
                )
            )
        );

        // Get latest version
        $latest_version = max(array_keys($schema_versions));

        // If current version is not the latest, update is needed
        if (version_compare($current_version, $latest_version, '<')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            // Start transaction
            $wpdb->query('START TRANSACTION');

            try {
                // Apply all updates in sequence
                foreach ($schema_versions as $version => $schema) {
                    if (version_compare($current_version, $version, '<')) {
                        // Execute schema change
                        if (!empty($schema['sql'])) {
                            dbDelta($schema['sql']);
                            
                            // Check for errors
                            if (!empty($wpdb->last_error)) {
                                throw new Exception("Error updating to version $version: " . $wpdb->last_error);
                            }
                        }

                        // Execute migrations
                        if (!empty($schema['migrations'])) {
                            foreach ($schema['migrations'] as $migration) {
                                $wpdb->query($migration);
                                if (!empty($wpdb->last_error)) {
                                    throw new Exception("Error migrating data for version $version: " . $wpdb->last_error);
                                }
                            }
                        }

                        // Update version after successful update
                        update_option('lccp_forms_db_version', $version);
                    }
                }

                // Commit transaction
                $wpdb->query('COMMIT');

                // Log successful update
                error_log("LCCP Forms: Database updated successfully to version $latest_version");
            } catch (Exception $e) {
                // Rollback on error
                $wpdb->query('ROLLBACK');
                error_log('LCCP Forms: Database update failed - ' . $e->getMessage());
            }
        }

        // Verify table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log('LCCP Forms: Database table creation failed');
            return false;
        }

        return true;
    }

    /**
     * Add meta boxes for form settings
     */
    public function add_form_meta_boxes() {
        add_meta_box(
            'lccp_form_settings',
            __('Form Settings', 'lccp-foundations'),
            array($this, 'render_form_settings'),
            'lccp_form',
            'normal',
            'high'
        );

        add_meta_box(
            'lccp_form_notifications',
            __('Form Notifications', 'lccp-foundations'),
            array($this, 'render_form_notifications'),
            'lccp_form',
            'normal',
            'high'
        );

        // Only add LearnDash meta box if LearnDash is active
        if (defined('LEARNDASH_VERSION')) {
            add_meta_box(
                'lccp_form_learndash',
                __('LearnDash Integration', 'lccp-foundations'),
                array($this, 'render_learndash_settings'),
                'lccp_form',
                'side',
                'default'
            );
        }
    }

    /**
     * Render form settings meta box
     */
    public function render_form_settings($post) {
        wp_nonce_field('lccp_form_settings', 'lccp_form_settings_nonce');
        
        $settings = get_post_meta($post->ID, '_form_settings', true);
        $allow_multiple = !empty($settings['allow_multiple']);
        $due_date_type = !empty($settings['due_date_type']) ? $settings['due_date_type'] : 'none';
        $due_date = !empty($settings['due_date']) ? $settings['due_date'] : '';
        $submission_frequency = !empty($settings['submission_frequency']) ? $settings['submission_frequency'] : '';
        ?>
        <div class="lccp-form-settings">
            <p>
                <label>
                    <input type="checkbox" name="form_settings[allow_multiple]" value="1" <?php checked($allow_multiple); ?>>
                    <?php _e('Allow Multiple Submissions', 'lccp-foundations'); ?>
                </label>
            </p>

            <p>
                <label><?php _e('Due Date Type:', 'lccp-foundations'); ?></label><br>
                <select name="form_settings[due_date_type]" class="due-date-type">
                    <option value="none" <?php selected($due_date_type, 'none'); ?>><?php _e('No Due Date', 'lccp-foundations'); ?></option>
                    <option value="specific" <?php selected($due_date_type, 'specific'); ?>><?php _e('Specific Date', 'lccp-foundations'); ?></option>
                    <option value="frequency" <?php selected($due_date_type, 'frequency'); ?>><?php _e('Submission Frequency', 'lccp-foundations'); ?></option>
                </select>
            </p>

            <div class="due-date-specific" <?php echo $due_date_type !== 'specific' ? 'style="display:none;"' : ''; ?>>
                <p>
                    <label><?php _e('Due Date:', 'lccp-foundations'); ?></label><br>
                    <input type="date" name="form_settings[due_date]" value="<?php echo esc_attr($due_date); ?>">
                </p>
            </div>

            <div class="due-date-frequency" <?php echo $due_date_type !== 'frequency' ? 'style="display:none;"' : ''; ?>>
                <p>
                    <label><?php _e('Submission Frequency:', 'lccp-foundations'); ?></label><br>
                    <select name="form_settings[submission_frequency]">
                        <option value="daily" <?php selected($submission_frequency, 'daily'); ?>><?php _e('Daily', 'lccp-foundations'); ?></option>
                        <option value="weekly" <?php selected($submission_frequency, 'weekly'); ?>><?php _e('Weekly', 'lccp-foundations'); ?></option>
                        <option value="monthly" <?php selected($submission_frequency, 'monthly'); ?>><?php _e('Monthly', 'lccp-foundations'); ?></option>
                    </select>
                </p>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('.due-date-type').on('change', function() {
                const type = $(this).val();
                $('.due-date-specific, .due-date-frequency').hide();
                if (type === 'specific') {
                    $('.due-date-specific').show();
                } else if (type === 'frequency') {
                    $('.due-date-frequency').show();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render form notifications meta box
     */
    public function render_form_notifications($post) {
        $notifications = get_post_meta($post->ID, '_form_notifications', true);
        $notify_mentor = !empty($notifications['notify_mentor']);
        $notify_big_bird = !empty($notifications['notify_big_bird']);
        ?>
        <div class="lccp-form-notifications">
            <p>
                <label>
                    <input type="checkbox" name="form_notifications[notify_mentor]" value="1" <?php checked($notify_mentor); ?>>
                    <?php _e('Send Results to Mentor', 'lccp-foundations'); ?>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="form_notifications[notify_big_bird]" value="1" <?php checked($notify_big_bird); ?>>
                    <?php _e('Send Results to Big Bird', 'lccp-foundations'); ?>
                </label>
            </p>
        </div>
        <?php
    }

    /**
     * Render LearnDash settings meta box
     */
    public function render_learndash_settings($post) {
        $course_id = get_post_meta($post->ID, '_ld_course_id', true);
        $lesson_id = get_post_meta($post->ID, '_ld_lesson_id', true);
        $mark_complete = get_post_meta($post->ID, '_ld_mark_complete', true);
        
        // Get all LearnDash courses
        $courses = get_posts(array(
            'post_type' => 'sfwd-courses',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        // Get lessons if course is selected
        $lessons = array();
        if ($course_id) {
            $lessons = learndash_get_course_lessons_list($course_id);
        }
        ?>
        <p>
            <label for="ld_course_id"><?php _e('Associated Course:', 'lccp-foundations'); ?></label><br>
            <select name="ld_course_id" id="ld_course_id" class="widefat">
                <option value=""><?php _e('Select Course', 'lccp-foundations'); ?></option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course->ID; ?>" <?php selected($course_id, $course->ID); ?>>
                        <?php echo esc_html($course->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="ld_lesson_id"><?php _e('Associated Lesson:', 'lccp-foundations'); ?></label><br>
            <select name="ld_lesson_id" id="ld_lesson_id" class="widefat">
                <option value=""><?php _e('Select Lesson', 'lccp-foundations'); ?></option>
                <?php if ($lessons): foreach ($lessons as $lesson): ?>
                    <option value="<?php echo $lesson['id']; ?>" <?php selected($lesson_id, $lesson['id']); ?>>
                        <?php echo esc_html($lesson['post']->post_title); ?>
                    </option>
                <?php endforeach; endif; ?>
            </select>
        </p>

        <p>
            <label>
                <input type="checkbox" name="ld_mark_complete" value="1" <?php checked($mark_complete); ?>>
                <?php _e('Mark lesson complete on form submission', 'lccp-foundations'); ?>
            </label>
        </p>

        <script>
        jQuery(document).ready(function($) {
            $('#ld_course_id').on('change', function() {
                var courseId = $(this).val();
                var lessonSelect = $('#ld_lesson_id');
                
                lessonSelect.empty().append('<option value=""><?php _e("Select Lesson", "lccp-foundations"); ?></option>');
                
                if (!courseId) {
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    data: {
                        action: 'lccp_get_course_lessons',
                        course_id: courseId,
                        nonce: '<?php echo wp_create_nonce("lccp_get_lessons"); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            $.each(response.data, function(i, lesson) {
                                lessonSelect.append(
                                    $('<option></option>')
                                        .val(lesson.id)
                                        .text(lesson.title)
                                );
                            });
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Save form settings
     */
    public function save_form_settings($post_id) {
        if (!isset($_POST['lccp_form_settings_nonce']) || !wp_verify_nonce($_POST['lccp_form_settings_nonce'], 'lccp_form_settings')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save form settings
        if (isset($_POST['form_settings'])) {
            update_post_meta($post_id, '_form_settings', $_POST['form_settings']);
        }

        // Save form notifications
        if (isset($_POST['form_notifications'])) {
            update_post_meta($post_id, '_form_notifications', $_POST['form_notifications']);
        }

        // Save LearnDash settings
        if (isset($_POST['ld_course_id'])) {
            update_post_meta($post_id, '_ld_course_id', sanitize_text_field($_POST['ld_course_id']));
        }
        if (isset($_POST['ld_lesson_id'])) {
            update_post_meta($post_id, '_ld_lesson_id', sanitize_text_field($_POST['ld_lesson_id']));
        }
        update_post_meta($post_id, '_ld_mark_complete', isset($_POST['ld_mark_complete']) ? '1' : '');
    }

    /**
     * Handle form submission
     */
    public function handle_form_submission() {
        check_ajax_referer('lccp_submit_form', 'nonce');

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $user_id = get_current_user_id();

        if (!$form_id || !$user_id) {
            wp_send_json_error('Invalid form submission');
            return;
        }

        // Store submission
        global $wpdb;
        $table_name = $wpdb->prefix . 'lccp_form_submissions';
        $submission_data = json_encode($_POST['form_data']);

        $result = $wpdb->insert(
            $table_name,
            array(
                'form_id' => $form_id,
                'user_id' => $user_id,
                'submission_data' => $submission_data,
            ),
            array('%d', '%d', '%s')
        );

        if ($result === false) {
            wp_send_json_error('Failed to save submission');
            return;
        }

        // Handle LearnDash completion if integrated
        if (defined('LEARNDASH_VERSION')) {
            $lesson_id = get_post_meta($form_id, '_ld_lesson_id', true);
            $mark_complete = get_post_meta($form_id, '_ld_mark_complete', true);

            if ($lesson_id && $mark_complete) {
                learndash_process_mark_complete($user_id, $lesson_id);
            }
        }

        // Generate and send PDF
        $this->send_submission_notifications($form_id, $wpdb->insert_id);

        wp_send_json_success(array(
            'message' => 'Form submitted successfully',
            'submission_id' => $wpdb->insert_id,
        ));
    }

    /**
     * Send submission notifications
     */
    private function send_submission_notifications($form_id, $submission_id) {
        $notifications = get_post_meta($form_id, '_form_notifications', true);
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        $form = get_post($form_id);

        // Generate PDF
        $pdf = $this->generate_submission_pdf($form_id, $submission_id);
        
        // Send to user
        $this->send_notification_email(
            $user->user_email,
            'Your Form Submission: ' . $form->post_title,
            'Thank you for submitting the form "' . $form->post_title . '". You can view your submission online using the link below, or download the attached PDF copy.',
            $pdf
        );

        // Send to mentor if enabled
        if (!empty($notifications['notify_mentor'])) {
            $mentor_id = get_user_meta($user_id, '_mentor_id', true);
            if ($mentor_id) {
                $mentor = get_userdata($mentor_id);
                if ($mentor) {
                    $this->send_notification_email(
                        $mentor->user_email,
                        'New Form Submission from ' . $user->display_name . ': ' . $form->post_title,
                        'Your mentee ' . $user->display_name . ' has submitted the form "' . $form->post_title . '". You can view the submission online using the link below, or download the attached PDF copy.',
                        $pdf
                    );
                }
            }
        }

        // Send to Big Bird if enabled
        if (!empty($notifications['notify_big_bird'])) {
            $big_bird_id = get_user_meta($user_id, '_big_bird_id', true);
            if ($big_bird_id) {
                $big_bird = get_userdata($big_bird_id);
                if ($big_bird) {
                    $this->send_notification_email(
                        $big_bird->user_email,
                        'New Form Submission from ' . $user->display_name . ': ' . $form->post_title,
                        'Your PC ' . $user->display_name . ' has submitted the form "' . $form->post_title . '". You can view the submission online using the link below, or download the attached PDF copy.',
                        $pdf
                    );
                }
            }
        }
    }

    /**
     * Generate PDF from submission
     */
    private function generate_submission_pdf($form_id, $submission_id) {
        require_once LCCP_FOUNDATIONS_PLUGIN_DIR . 'includes/class-lccp-pdf-generator.php';
        $generator = new LCCP_PDF_Generator();
        return $generator->generate($form_id, $submission_id);
    }

    /**
     * Send notification email with PDF attachment
     */
    private function send_notification_email($to, $subject, $message, $pdf_content) {
        if (!$pdf_content) {
            return false;
        }

        // Generate unique filename for PDF
        $filename = 'form-submission-' . uniqid() . '.pdf';
        $temp_dir = get_temp_dir();
        $temp_file = $temp_dir . $filename;
        file_put_contents($temp_file, $pdf_content);

        // Get user data for the recipient
        $user = get_user_by('email', $to);
        
        // Generate auto-login link using WP Fusion if available
        $view_url = $this->get_submission_url($submission_id);
        $auto_login_url = '';
        
        if (class_exists('WP_Fusion')) {
            $auto_login_url = wp_fusion()->access->get_auto_login_url($view_url, $user->ID);
        } else {
            // Fallback to standard URL with nonce
            $auto_login_url = wp_nonce_url($view_url, 'view_submission_' . $submission_id);
        }

        // Use BuddyBoss email template if available
        if (function_exists('bp_email_get_schema') && function_exists('bp_send_email')) {
            $email_type = 'form_submission';
            $args = array(
                'tokens' => array(
                    'site.name'     => get_bloginfo('name'),
                    'submission.title' => $subject,
                    'submission.message' => $message,
                    'submission.link' => $auto_login_url,
                    'recipient.name' => bp_core_get_user_displayname($user->ID),
                ),
            );

            // Create HTML content using BuddyBoss email template
            $email_content = bp_email_core_wp_get_template(array(
                'id'       => $email_type,
                'content'  => array(
                    'html' => '
                        <div class="email-content">
                            <h2 style="color: var(--bb-headings-color);">{{{submission.title}}}</h2>
                            
                            <p style="color: var(--bb-body-text-color); font-family: var(--bb-body-font-family);">
                                {{{submission.message}}}
                            </p>
                            
                            <div class="email-button-wrap" style="margin: 30px 0; text-align: center;">
                                <a href="{{{submission.link}}}" class="email-button" style="
                                    display: inline-block;
                                    padding: 12px 24px;
                                    background-color: var(--bb-primary-color);
                                    color: #ffffff;
                                    text-decoration: none;
                                    border-radius: var(--bb-button-radius);
                                    font-family: var(--bb-body-font-family);
                                    font-weight: 500;
                                ">
                                    ' . esc_html__('View Submission Online', 'lccp-foundations') . '
                                </a>
                            </div>
                            
                            <p style="color: var(--bb-alternate-text-color); font-size: 14px; font-family: var(--bb-body-font-family);">
                                ' . esc_html__('A PDF copy of the submission is attached to this email for your records.', 'lccp-foundations') . '
                            </p>
                        </div>
                    ',
                    'plain' => $message . "\n\n" . esc_html__('View Submission Online:', 'lccp-foundations') . ' ' . $auto_login_url,
                ),
            ));

            // Send email using BuddyBoss
            $sent = bp_send_email($email_type, $user->ID, array(
                'tokens' => $args['tokens'],
                'to' => $to,
                'subject' => $subject,
                'content' => $email_content,
                'attachments' => array($temp_file),
            ));
        } else {
            // Fallback to standard WordPress email template
            $site_name = get_bloginfo('name');
            $site_url = get_bloginfo('url');
            $admin_email = get_bloginfo('admin_email');
            
            // Build fallback HTML template
            $html = '<!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <title>' . esc_html($subject) . '</title>
            </head>
            <body style="margin: 0; padding: 0; background-color: #f7f7f7; font-family: Arial, sans-serif;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f7f7f7; padding: 20px;">
                    <tr>
                        <td align="center">
                            <table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <!-- Header -->
                                <tr>
                                    <td align="center" style="padding: 40px 0; background-color: #385DFF; border-radius: 4px 4px 0 0;">
                                        <h1 style="color: #ffffff; margin: 0; font-size: 24px;">' . esc_html($site_name) . '</h1>
                                    </td>
                                </tr>
                                
                                <!-- Content -->
                                <tr>
                                    <td style="padding: 40px;">
                                        <h2 style="color: #333333; margin: 0 0 20px;">' . esc_html($subject) . '</h2>
                                        <div style="color: #666666; font-size: 16px; line-height: 1.5; margin-bottom: 30px;">
                                            ' . wp_kses_post($message) . '
                                        </div>
                                        
                                        <div style="text-align: center; margin: 30px 0;">
                                            <a href="' . esc_url($auto_login_url) . '" style="display: inline-block; padding: 12px 24px; background-color: #385DFF; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold;">
                                                ' . esc_html__('View Submission Online', 'lccp-foundations') . '
                                            </a>
                                        </div>
                                        
                                        <p style="color: #999999; font-size: 14px; margin-top: 30px;">
                                            ' . esc_html__('A PDF copy of the submission is attached to this email for your records.', 'lccp-foundations') . '
                                        </p>
                                    </td>
                                </tr>
                                
                                <!-- Footer -->
                                <tr>
                                    <td style="padding: 20px 40px; background-color: #f7f7f7; border-radius: 0 0 4px 4px; color: #999999; font-size: 12px;">
                                        <p style="margin: 0;">
                                            ' . sprintf(
                                                __('This email was sent from %s', 'lccp-foundations'),
                                                '<a href="' . esc_url($site_url) . '" style="color: #385DFF; text-decoration: none;">' . esc_html($site_name) . '</a>'
                                            ) . '
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>';

            // Set up email headers
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $site_name . ' <' . $admin_email . '>',
                'Reply-To: ' . $admin_email,
            );

            // Send email using wp_mail
            $sent = wp_mail($to, $subject, $html, $headers, array($temp_file));
        }

        // Clean up temp file
        unlink($temp_file);

        return $sent;
    }

    /**
     * Register email templates with BuddyBoss
     */
    public function register_email_templates() {
        if (!function_exists('bp_email_get_schema')) {
            return;
        }

        // Register form submission email type
        bp_email_register_type('form_submission', array(
            'post_title'   => __('Form Submission Notification', 'lccp-foundations'),
            'post_content' => __("A form has been submitted on {{site.name}}", 'lccp-foundations'),
            'post_excerpt' => __("A form has been submitted on {{site.name}}", 'lccp-foundations'),
            'situation_description' => __('Sent when a user submits a form', 'lccp-foundations'),
            'unsubscribe'  => false,
        ));
    }

    /**
     * Handle form templates
     */
    public function form_templates($template) {
        if (is_singular('lccp_form')) {
            $new_template = plugin_dir_path(__FILE__) . '../templates/single-form.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        } elseif (is_post_type_archive('lccp_form')) {
            $new_template = plugin_dir_path(__FILE__) . '../templates/archive-form.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }
        return $template;
    }

    /**
     * Add the import page under Forms
     */
    public function add_import_page() {
        add_submenu_page(
            'learndash-lms',
            __('Import Forms', 'lccp-foundations'),
            __('Import Forms', 'lccp-foundations'),
            'manage_options',
            'lccp-import-forms',
            array($this, 'render_import_page')
        );
    }

    /**
     * Render the import page
     */
    public function render_import_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Import Forms', 'lccp-foundations'); ?></h1>
            <div class="lccp-import-form">
                <form id="lccp-form-import" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('lccp_import_form', 'lccp_import_nonce'); ?>
                    <p>
                        <label for="form_file"><?php echo esc_html__('Select JSON file:', 'lccp-foundations'); ?></label>
                        <input type="file" name="form_file" id="form_file" accept=".json" required>
                    </p>
                    <p>
                        <input type="submit" class="button button-primary" value="<?php echo esc_attr__('Import', 'lccp-foundations'); ?>">
                    </p>
                </form>
                <div id="import-results"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ('learndash-lms_page_lccp-import-forms' !== $hook) {
            return;
        }

        wp_enqueue_script(
            'lccp-forms-admin',
            plugins_url('assets/js/lccp-forms-admin.js', dirname(__FILE__)),
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('lccp-forms-admin', 'lccp_forms', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lccp_import_form'),
        ));
    }

    /**
     * Handle form import
     */
    public function handle_form_import() {
        check_ajax_referer('lccp_import_form', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        if (!isset($_FILES['form_file'])) {
            wp_send_json_error('No file uploaded');
            return;
        }

        $file = $_FILES['form_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('File upload error');
            return;
        }

        // Read the JSON file
        $json_content = file_get_contents($file['tmp_name']);
        if (!$json_content) {
            wp_send_json_error('Could not read file');
            return;
        }

        // Decode the JSON
        $form_data = json_decode($json_content, true);
        if (!$form_data) {
            wp_send_json_error('Invalid JSON format');
            return;
        }

        // Include the decoder class
        require_once plugin_dir_path(__FILE__) . 'class-lccp-form-decoder.php';
        $decoder = new LCCP_Form_Decoder();

        // Convert JSON to HTML
        $html_content = $decoder->decode_form($json_content);

        // Create a new form post
        $post_data = array(
            'post_title'   => !empty($form_data['title']) ? $form_data['title'] : 'Imported Form',
            'post_content' => $html_content,
            'post_status'  => 'publish',
            'post_type'    => 'lccp_form',
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            wp_send_json_error($post_id->get_error_message());
            return;
        }

        // Save original JSON as post meta
        update_post_meta($post_id, '_form_json', $json_content);

        wp_send_json_success(array(
            'message' => sprintf(
                __('Form imported successfully. <a href="%s">View form</a>', 'lccp-foundations'),
                get_edit_post_link($post_id)
            ),
            'post_id' => $post_id,
        ));
    }

    /**
     * Add the submissions page under Forms
     */
    public function add_submissions_page() {
        add_submenu_page(
            'learndash-lms',
            __('Form Submissions', 'lccp-foundations'),
            __('Form Submissions', 'lccp-foundations'),
            'read',
            'lccp-form-submissions',
            array($this, 'render_submissions_page')
        );
    }

    /**
     * Render the submissions page
     */
    public function render_submissions_page() {
        if (!is_user_logged_in()) {
            wp_die(__('You do not have permission to view this page.', 'lccp-foundations'));
        }

        $user_id = get_current_user_id();
        $user_role = $this->get_user_role($user_id);
        $view_all = isset($_GET['view']) && $_GET['view'] === 'all';

        ?>
        <div class="wrap">
            <h1><?php _e('Form Submissions', 'lccp-foundations'); ?></h1>
            
            <?php if ($user_role === 'mentor'): ?>
            <div class="mentor-controls">
                <?php if (!$view_all): ?>
                    <a href="<?php echo add_query_arg('view', 'all'); ?>" class="button button-primary">
                        <?php _e('View All Submissions', 'lccp-foundations'); ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo remove_query_arg('view'); ?>" class="button">
                        <?php _e('View My PC Submissions', 'lccp-foundations'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="submissions-list">
                <?php $this->display_submissions($user_id, $user_role, $view_all); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Display submissions based on user role and permissions
     */
    private function display_submissions($user_id, $user_role, $view_all = false) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'lccp_form_submissions';
        
        // Build query based on user role and view type
        $query = "SELECT s.*, p.post_title as form_title, u.display_name as submitter_name 
                 FROM $table_name s 
                 JOIN {$wpdb->posts} p ON s.form_id = p.ID 
                 JOIN {$wpdb->users} u ON s.user_id = u.ID";

        $where = array();
        $query_args = array();

        switch ($user_role) {
            case 'pc':
                // PCs can only see their own submissions
                $where[] = "s.user_id = %d";
                $query_args[] = $user_id;
                break;

            case 'big_bird':
                // Big Birds can see submissions from their PCs
                $pc_ids = $this->get_assigned_pcs($user_id);
                if (!empty($pc_ids)) {
                    $placeholders = array_fill(0, count($pc_ids), '%d');
                    $where[] = "s.user_id IN (" . implode(',', $placeholders) . ")";
                    $query_args = array_merge($query_args, $pc_ids);
                } else {
                    $where[] = "1=0"; // No PCs assigned, return no results
                }
                break;

            case 'mentor':
                if (!$view_all) {
                    // Mentors see their PC submissions by default
                    $pc_ids = $this->get_assigned_pcs($user_id);
                    if (!empty($pc_ids)) {
                        $placeholders = array_fill(0, count($pc_ids), '%d');
                        $where[] = "s.user_id IN (" . implode(',', $placeholders) . ")";
                        $query_args = array_merge($query_args, $pc_ids);
                    }
                }
                // If view_all is true, no WHERE clause is added, showing all submissions
                break;

            default:
                wp_die(__('Invalid user role', 'lccp-foundations'));
                break;
        }

        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }

        $query .= " ORDER BY s.submission_date DESC";

        if (!empty($query_args)) {
            $submissions = $wpdb->get_results($wpdb->prepare($query, $query_args));
        } else {
            $submissions = $wpdb->get_results($query);
        }

        if (empty($submissions)) {
            echo '<p>' . __('No submissions found.', 'lccp-foundations') . '</p>';
            return;
        }

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Form', 'lccp-foundations'); ?></th>
                    <th><?php _e('Submitted By', 'lccp-foundations'); ?></th>
                    <th><?php _e('Date', 'lccp-foundations'); ?></th>
                    <th><?php _e('Actions', 'lccp-foundations'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $submission): ?>
                <tr>
                    <td><?php echo esc_html($submission->form_title); ?></td>
                    <td><?php echo esc_html($submission->submitter_name); ?></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->submission_date))); ?></td>
                    <td>
                        <a href="<?php echo esc_url($this->get_submission_url($submission->id)); ?>" class="button button-small">
                            <?php _e('View', 'lccp-foundations'); ?>
                        </a>
                        <a href="<?php echo esc_url($this->get_submission_pdf_url($submission->id)); ?>" class="button button-small">
                            <?php _e('Download PDF', 'lccp-foundations'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Get user role (pc, big_bird, or mentor)
     */
    private function get_user_role($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Check for BuddyBoss member types if available
        if (function_exists('bp_get_member_type')) {
            $member_type = bp_get_member_type($user_id);
            if ($member_type === 'pc') {
                return 'pc';
            } elseif ($member_type === 'big_bird') {
                return 'big_bird';
            } elseif ($member_type === 'mentor') {
                return 'mentor';
            }
        }

        // Fallback to WordPress roles
        if (in_array('administrator', $user->roles)) {
            return 'mentor'; // Admins have mentor privileges
        }

        // Check custom roles
        $custom_roles = array(
            'pc' => array('pc', 'peace_corps_volunteer', 'pcv'),
            'big_bird' => array('big_bird', 'big-bird', 'bigbird'),
            'mentor' => array('mentor', 'supervisor', 'trainer')
        );

        foreach ($user->roles as $role) {
            foreach ($custom_roles as $type => $possible_roles) {
                if (in_array($role, $possible_roles)) {
                    return $type;
                }
            }
        }

        // Check user meta as last resort
        $role_meta = get_user_meta($user_id, '_user_role', true);
        if (in_array($role_meta, array('pc', 'big_bird', 'mentor'))) {
            return $role_meta;
        }

        return false;
    }

    /**
     * Get assigned PCs for a mentor or big bird
     */
    private function get_assigned_pcs($user_id) {
        global $wpdb;
        $pc_ids = array();
        
        // Get user role
        $user_role = $this->get_user_role($user_id);
        if (!in_array($user_role, array('mentor', 'big_bird'))) {
            return array();
        }

        // Try BuddyBoss groups first if available
        if (function_exists('groups_get_user_groups')) {
            $groups = groups_get_user_groups($user_id);
            if (!empty($groups['groups'])) {
                $group_members = array();
                foreach ($groups['groups'] as $group_id) {
                    $members = groups_get_group_members(array(
                        'group_id' => $group_id,
                        'per_page' => 999
                    ));
                    if (!empty($members['members'])) {
                        foreach ($members['members'] as $member) {
                            if ($this->get_user_role($member->id) === 'pc') {
                                $group_members[] = $member->id;
                            }
                        }
                    }
                }
                if (!empty($group_members)) {
                    return array_unique($group_members);
                }
            }
        }

        // Check user meta relationships
        $meta_key = $user_role === 'mentor' ? '_mentor_id' : '_big_bird_id';
        
        $pc_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
            $meta_key,
            $user_id
        ));

        // If no PCs found through meta, check custom tables if they exist
        if (empty($pc_ids)) {
            $relationships_table = $wpdb->prefix . 'user_relationships';
            if ($wpdb->get_var("SHOW TABLES LIKE '$relationships_table'") === $relationships_table) {
                $pc_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT pc_id FROM $relationships_table WHERE {$user_role}_id = %d",
                    $user_id
                ));
            }
        }

        return array_unique(array_filter($pc_ids));
    }

    /**
     * Get URL for viewing a submission
     */
    private function get_submission_url($submission_id) {
        return add_query_arg(array(
            'page' => 'lccp-form-submissions',
            'action' => 'view',
            'submission' => $submission_id,
            'nonce' => wp_create_nonce('view_submission_' . $submission_id)
        ), admin_url('admin.php'));
    }

    /**
     * Get URL for downloading submission PDF
     */
    private function get_submission_pdf_url($submission_id) {
        return add_query_arg(array(
            'action' => 'lccp_download_submission_pdf',
            'submission' => $submission_id,
            'nonce' => wp_create_nonce('download_submission_' . $submission_id)
        ), admin_url('admin-ajax.php'));
    }

    /**
     * Handle submission PDF download
     */
    public function handle_submission_pdf_download() {
        $submission_id = isset($_GET['submission']) ? intval($_GET['submission']) : 0;
        if (!$submission_id || !wp_verify_nonce($_GET['nonce'], 'download_submission_' . $submission_id)) {
            wp_die(__('Invalid request', 'lccp-foundations'));
        }

        // Check if user has permission to view this submission
        if (!$this->can_view_submission($submission_id)) {
            wp_die(__('You do not have permission to view this submission', 'lccp-foundations'));
        }

        // Get submission data
        global $wpdb;
        $table_name = $wpdb->prefix . 'lccp_form_submissions';
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $submission_id
        ));

        if (!$submission) {
            wp_die(__('Submission not found', 'lccp-foundations'));
        }

        // Generate PDF
        $pdf_content = $this->generate_submission_pdf($submission->form_id, $submission_id);
        if (!$pdf_content) {
            wp_die(__('Failed to generate PDF', 'lccp-foundations'));
        }

        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="submission-' . $submission_id . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $pdf_content;
        exit;
    }

    /**
     * Check if current user can view a submission
     */
    private function can_view_submission($submission_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'lccp_form_submissions';
        $user_id = get_current_user_id();
        $user_role = $this->get_user_role($user_id);

        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM $table_name WHERE id = %d",
            $submission_id
        ));

        if (!$submission) {
            return false;
        }

        switch ($user_role) {
            case 'pc':
                // PCs can only view their own submissions
                return $submission->user_id === $user_id;

            case 'big_bird':
                // Big Birds can view submissions from their PCs
                $pc_ids = $this->get_assigned_pcs($user_id);
                return in_array($submission->user_id, $pc_ids);

            case 'mentor':
                // Mentors can view all submissions
                return true;

            default:
                return false;
        }
    }

    /**
     * AJAX handler for getting course lessons
     */
    public function get_course_lessons() {
        check_ajax_referer('lccp_get_lessons', 'nonce');
        
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
        if (!$course_id) {
            wp_send_json_error('Invalid course ID');
            return;
        }

        $lessons = learndash_get_course_lessons_list($course_id);
        $formatted_lessons = array();

        foreach ($lessons as $lesson) {
            $formatted_lessons[] = array(
                'id' => $lesson['post']->ID,
                'title' => $lesson['post']->post_title
            );
        }

        wp_send_json_success($formatted_lessons);
    }

    /**
     * Inject form content into LearnDash lessons
     */
    public function inject_form_content($content, $post) {
        if ($post->post_type !== 'sfwd-lessons') {
            return $content;
        }

        // Find forms associated with this lesson
        $forms = get_posts(array(
            'post_type' => 'lccp_form',
            'meta_query' => array(
                array(
                    'key' => '_ld_lesson_id',
                    'value' => $post->ID,
                ),
            ),
        ));

        if (empty($forms)) {
            return $content;
        }

        foreach ($forms as $form) {
            // Check if user has already submitted this form
            global $wpdb;
            $table_name = $wpdb->prefix . 'lccp_form_submissions';
            $user_id = get_current_user_id();
            
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE form_id = %d AND user_id = %d",
                $form->ID,
                $user_id
            ));

            // Get form settings
            $settings = get_post_meta($form->ID, '_form_settings', true);
            $allow_multiple = !empty($settings['allow_multiple']);

            if (!$allow_multiple && $existing > 0) {
                $content .= sprintf(
                    '<div class="lccp-form-submitted">
                        <h3>%s</h3>
                        <p>%s</p>
                    </div>',
                    esc_html($form->post_title),
                    __('You have already submitted this form.', 'lccp-foundations')
                );
                continue;
            }

            // Check due date
            if (!empty($settings['due_date_type'])) {
                if ($settings['due_date_type'] === 'specific' && !empty($settings['due_date'])) {
                    $due_date = strtotime($settings['due_date']);
                    if (current_time('timestamp') > $due_date) {
                        $content .= sprintf(
                            '<div class="lccp-form-expired">
                                <h3>%s</h3>
                                <p>%s</p>
                            </div>',
                            esc_html($form->post_title),
                            __('The submission deadline for this form has passed.', 'lccp-foundations')
                        );
                        continue;
                    }
                }
            }

            // Add form to content
            $content .= sprintf(
                '<div class="lccp-form-container" id="form-%d">
                    <h3>%s</h3>
                    %s
                </div>',
                $form->ID,
                esc_html($form->post_title),
                apply_filters('the_content', $form->post_content)
            );
        }

        return $content;
    }
} 