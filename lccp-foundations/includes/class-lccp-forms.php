<?php
/**
 * Handles Forms post type and LearnDash integration
 *
 * @package LCCP_Foundations
 * @version 1.0.0
 */

class LCCP_Forms {
    /**
     * Initialize the forms functionality
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'), 11);
        add_action('init', array(__CLASS__, 'register_meta'));
        add_filter('learndash_post_args', array(__CLASS__, 'add_form_to_builder'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_course_association_meta_box'));
        add_action('save_post', array(__CLASS__, 'save_course_association'));
        add_filter('post_type_link', array(__CLASS__, 'modify_form_permalink'), 10, 2);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
        add_action('admin_menu', array(__CLASS__, 'register_import_page'), 11);
        add_action('wp_ajax_lccp_import_forms', array(__CLASS__, 'handle_form_import'));
    }

    /**
     * Register the Forms post type
     */
    public static function register_post_type() {
        $labels = array(
            'name'                  => _x('Forms', 'Post Type General Name', 'lccp-foundations'),
            'singular_name'         => _x('Form', 'Post Type Singular Name', 'lccp-foundations'),
            'menu_name'            => __('Forms', 'lccp-foundations'),
            'add_new'              => __('Add New Form', 'lccp-foundations'),
            'add_new_item'         => __('Add New Form', 'lccp-foundations'),
            'edit_item'            => __('Edit Form', 'lccp-foundations'),
            'view_item'            => __('View Form', 'lccp-foundations'),
            'all_items'            => __('All Forms', 'lccp-foundations'),
        );

        $args = array(
            'label'                 => __('Form', 'lccp-foundations'),
            'labels'                => $labels,
            'supports'              => array('title', 'editor', 'revisions'),
            'hierarchical'          => false,
            'public'               => true,
            'show_ui'              => true,
            'show_in_menu'         => 'learndash-lms',
            'menu_position'        => 51,
            'menu_icon'            => 'dashicons-feedback',
            'show_in_admin_bar'    => true,
            'show_in_nav_menus'    => true,
            'can_export'           => true,
            'has_archive'          => true,
            'exclude_from_search'  => false,
            'publicly_queryable'   => true,
            'capability_type'      => 'post',
            'show_in_rest'         => true,
            'rewrite'              => array(
                'slug' => 'form',
                'with_front' => false
            ),
        );

        register_post_type('lccp_form', $args);
    }

    /**
     * Register form meta
     */
    public static function register_meta() {
        register_post_meta('lccp_form', '_learndash_course_id', array(
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
        ));
    }

    /**
     * Add Forms to LearnDash builder
     */
    public static function add_form_to_builder($args) {
        $args['lccp_form'] = array(
            'post_type' => 'lccp_form',
            'name' => __('Forms', 'lccp-foundations'),
            'slug' => 'forms',
            'hierarchical' => false,
        );
        return $args;
    }

    /**
     * Add meta box for course association
     */
    public static function add_course_association_meta_box() {
        add_meta_box(
            'lccp_form_course_association',
            __('Course Association', 'lccp-foundations'),
            array(__CLASS__, 'render_course_association_meta_box'),
            'lccp_form',
            'side',
            'default'
        );
    }

    /**
     * Render course association meta box
     */
    public static function render_course_association_meta_box($post) {
        wp_nonce_field('lccp_form_course_nonce', 'lccp_form_course_nonce');
        $course_id = get_post_meta($post->ID, '_learndash_course_id', true);
        
        $courses = get_posts(array(
            'post_type' => 'sfwd-courses',
            'posts_per_page' => -1,
        ));
        ?>
        <select name="learndash_course_id">
            <option value=""><?php _e('Select Course', 'lccp-foundations'); ?></option>
            <?php foreach ($courses as $course) : ?>
                <option value="<?php echo $course->ID; ?>" <?php selected($course_id, $course->ID); ?>>
                    <?php echo esc_html($course->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Save course association
     */
    public static function save_course_association($post_id) {
        if (!isset($_POST['lccp_form_course_nonce']) || 
            !wp_verify_nonce($_POST['lccp_form_course_nonce'], 'lccp_form_course_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['learndash_course_id'])) {
            update_post_meta(
                $post_id,
                '_learndash_course_id',
                sanitize_text_field($_POST['learndash_course_id'])
            );
        }
    }

    /**
     * Modify form permalink structure
     */
    public static function modify_form_permalink($permalink, $post) {
        if ($post->post_type !== 'lccp_form') {
            return $permalink;
        }

        $course_id = get_post_meta($post->ID, '_learndash_course_id', true);
        if (!$course_id) {
            return $permalink;
        }

        $course = get_post($course_id);
        if (!$course) {
            return $permalink;
        }

        // Build URL structure similar to LearnDash
        $new_permalink = home_url('/courses/' . $course->post_name . '/forms/' . $post->post_name);
        return $new_permalink;
    }

    /**
     * Enqueue form styles
     */
    public static function enqueue_styles() {
        wp_enqueue_style(
            'lccp-forms',
            LCCP_FOUNDATIONS_PLUGIN_URL . 'assets/css/lccp-forms.css',
            array(),
            LCCP_FOUNDATIONS_VERSION
        );
    }

    /**
     * Register import page
     */
    public static function register_import_page() {
        add_submenu_page(
            'learndash-lms',
            __('Import Forms', 'lccp-foundations'),
            __('Import Forms', 'lccp-foundations'),
            'manage_options',
            'lccp-import-forms',
            array(__CLASS__, 'render_import_page')
        );
    }

    /**
     * Enqueue admin scripts
     */
    public static function enqueue_admin_scripts($hook) {
        if ('learndash-lms_page_lccp-import-forms' !== $hook) {
            return;
        }

        wp_enqueue_script(
            'lccp-forms-admin',
            LCCP_FOUNDATIONS_PLUGIN_URL . 'assets/js/lccp-forms-admin.js',
            array('jquery'),
            LCCP_FOUNDATIONS_VERSION,
            true
        );

        wp_localize_script('lccp-forms-admin', 'lccpFormsAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lccp_import_forms_nonce')
        ));
    }

    /**
     * Render import page
     */
    public static function render_import_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Import Forms', 'lccp-foundations'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Import HTML Forms', 'lccp-foundations'); ?></h2>
                <p><?php _e('Upload HTML files containing forms to import them as Form posts.', 'lccp-foundations'); ?></p>
                
                <form id="lccp-form-import" method="post" enctype="multipart/form-data">
                    <input type="file" 
                           name="form_files[]" 
                           id="form_files" 
                           multiple 
                           accept=".html,.htm"
                           required>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php _e('Import Forms', 'lccp-foundations'); ?>
                        </button>
                    </p>
                </form>
                
                <div id="import-results" style="display: none;">
                    <h3><?php _e('Import Results', 'lccp-foundations'); ?></h3>
                    <div id="import-messages"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle form import
     */
    public static function handle_form_import() {
        check_ajax_referer('lccp_import_forms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        if (!isset($_FILES['form_files'])) {
            wp_send_json_error('No files uploaded');
            return;
        }

        $results = array();
        $files = $_FILES['form_files'];

        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $content = file_get_contents($files['tmp_name'][$i]);
            if (!$content) {
                continue;
            }

            // Parse HTML content
            $dom = new DOMDocument();
            @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            // Find all forms
            $forms = $dom->getElementsByTagName('form');
            foreach ($forms as $form) {
                // Remove form fields with label "HTML Block"
                $labels = $form->getElementsByTagName('label');
                foreach ($labels as $label) {
                    if (trim($label->nodeValue) === 'HTML Block') {
                        // Find the associated form field (usually next to or connected by 'for' attribute)
                        $fieldId = $label->getAttribute('for');
                        if ($fieldId) {
                            $field = $dom->getElementById($fieldId);
                            if ($field) {
                                // Remove the field and its container if it exists
                                $container = $field->parentNode;
                                if ($container && $container->nodeName === 'div') {
                                    $container->parentNode->removeChild($container);
                                } else {
                                    $field->parentNode->removeChild($field);
                                }
                                // Remove the label too
                                $label->parentNode->removeChild($label);
                            }
                        }
                    }
                }

                // Create post title from filename
                $title = pathinfo($files['name'][$i], PATHINFO_FILENAME);
                $title = str_replace(array('-', '_'), ' ', $title);
                $title = ucwords($title);

                // Create the post
                $post_data = array(
                    'post_title'    => $title,
                    'post_content'  => $dom->saveHTML($form),
                    'post_status'   => 'publish',
                    'post_type'     => 'lccp_form'
                );

                $post_id = wp_insert_post($post_data);
                
                if (!is_wp_error($post_id)) {
                    $results[] = array(
                        'success' => true,
                        'message' => sprintf(
                            __('Successfully imported form: %s', 'lccp-foundations'),
                            $title
                        )
                    );
                } else {
                    $results[] = array(
                        'success' => false,
                        'message' => sprintf(
                            __('Failed to import form: %s', 'lccp-foundations'),
                            $title
                        )
                    );
                }
            }
        }

        wp_send_json_success($results);
    }
} 