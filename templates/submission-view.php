<?php
/**
 * Template for viewing form submissions
 *
 * @package LCCP_Foundations
 */

// Check if user has permission to view this submission
if (!LCCP_Forms::can_view_submission(get_the_ID())) {
    wp_die(__('You do not have permission to view this submission.', 'lccp-foundations'));
}

get_header();

while (have_posts()) :
    the_post();
    $submission_id = get_the_ID();
    $form_id = get_post_meta($submission_id, '_form_id', true);
    $submission_data = get_post_meta($submission_id, '_submission_data', true);
    $submission_date = get_the_date();
    $submission_time = get_the_time();
    $form = get_post($form_id);
    ?>

    <div id="primary" class="content-area">
        <main id="main" class="site-main">
            <article id="submission-<?php echo esc_attr($submission_id); ?>" <?php post_class('lccp-submission-container'); ?>>
                <header class="lccp-submission-header">
                    <h1 class="lccp-submission-title">
                        <?php
                        printf(
                            __('Submission for: %s', 'lccp-foundations'),
                            esc_html($form->post_title)
                        );
                        ?>
                    </h1>

                    <div class="lccp-submission-meta">
                        <div class="submission-date">
                            <?php
                            printf(
                                __('Submitted on %s at %s', 'lccp-foundations'),
                                esc_html($submission_date),
                                esc_html($submission_time)
                            );
                            ?>
                        </div>

                        <?php if (function_exists('learndash_get_course_id')) : ?>
                            <?php
                            $course_id = learndash_get_course_id($form_id);
                            if ($course_id) :
                                $course = get_post($course_id);
                                ?>
                                <div class="submission-course">
                                    <?php
                                    printf(
                                        __('Course: %s', 'lccp-foundations'),
                                        sprintf(
                                            '<a href="%s">%s</a>',
                                            get_permalink($course_id),
                                            esc_html($course->post_title)
                                        )
                                    );
                                    ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </header>

                <div class="lccp-submission-content">
                    <?php if (!empty($submission_data)) : ?>
                        <div class="submission-fields">
                            <?php foreach ($submission_data as $field_id => $field_data) : ?>
                                <div class="submission-field">
                                    <div class="field-label">
                                        <?php echo esc_html($field_data['label']); ?>
                                    </div>
                                    <div class="field-value">
                                        <?php
                                        switch ($field_data['type']) {
                                            case 'file':
                                                if (!empty($field_data['value'])) {
                                                    $file_url = wp_get_attachment_url($field_data['value']);
                                                    if ($file_url) {
                                                        printf(
                                                            '<a href="%s" target="_blank">%s</a>',
                                                            esc_url($file_url),
                                                            __('Download File', 'lccp-foundations')
                                                        );
                                                    }
                                                }
                                                break;

                                            case 'checkbox':
                                                if (is_array($field_data['value'])) {
                                                    echo '<ul class="checkbox-values">';
                                                    foreach ($field_data['value'] as $value) {
                                                        printf('<li>%s</li>', esc_html($value));
                                                    }
                                                    echo '</ul>';
                                                } else {
                                                    echo esc_html($field_data['value'] ? __('Yes', 'lccp-foundations') : __('No', 'lccp-foundations'));
                                                }
                                                break;

                                            case 'radio':
                                            case 'select':
                                                echo esc_html($field_data['value']);
                                                break;

                                            case 'textarea':
                                                echo nl2br(esc_html($field_data['value']));
                                                break;

                                            case 'date':
                                                echo esc_html(date_i18n(get_option('date_format'), strtotime($field_data['value'])));
                                                break;

                                            case 'slider':
                                                printf(
                                                    '<span class="slider-value">%d</span>',
                                                    intval($field_data['value'])
                                                );
                                                break;

                                            default:
                                                echo esc_html($field_data['value']);
                                                break;
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="submission-actions">
                            <?php if (LCCP_Forms::can_download_pdf($submission_id)) : ?>
                                <a href="<?php echo esc_url(LCCP_Forms::get_pdf_download_url($submission_id)); ?>" 
                                   class="button download-pdf" 
                                   target="_blank">
                                    <?php _e('Download PDF', 'lccp-foundations'); ?>
                                </a>
                            <?php endif; ?>

                            <?php if (LCCP_Forms::can_user_submit_form($form_id)) : ?>
                                <a href="<?php echo esc_url(get_permalink($form_id)); ?>" 
                                   class="button submit-again">
                                    <?php _e('Submit Again', 'lccp-foundations'); ?>
                                </a>
                            <?php endif; ?>

                            <a href="<?php echo esc_url(get_post_type_archive_link('lccp_form')); ?>" 
                               class="button view-all-forms">
                                <?php _e('View All Forms', 'lccp-foundations'); ?>
                            </a>
                        </div>

                    <?php else : ?>
                        <div class="lccp-submission-message error">
                            <?php _e('No submission data found.', 'lccp-foundations'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        </main>
    </div>

    <?php
endwhile;

get_sidebar();
get_footer();
?> 