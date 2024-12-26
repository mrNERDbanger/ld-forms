<?php
/**
 * Template for displaying form archives
 *
 * @package LCCP_Foundations
 */

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <?php if (have_posts()) : ?>
            <header class="page-header">
                <h1 class="page-title">
                    <?php 
                    if (is_tax()) {
                        single_term_title();
                    } else {
                        _e('Forms', 'lccp-foundations');
                    }
                    ?>
                </h1>
                <?php the_archive_description('<div class="archive-description">', '</div>'); ?>
            </header>

            <div class="lccp-forms-grid">
                <?php
                while (have_posts()) :
                    the_post();
                    $form_id = get_the_ID();
                    $form_settings = get_post_meta($form_id, '_lccp_form_settings', true);
                    $can_submit = LCCP_Forms::can_user_submit_form($form_id);
                    $submission_count = LCCP_Forms::get_user_submission_count($form_id);
                    $due_date = !empty($form_settings['due_date']) ? strtotime($form_settings['due_date']) : false;
                    ?>

                    <article id="post-<?php the_ID(); ?>" <?php post_class('lccp-form-card'); ?>>
                        <div class="lccp-form-card-inner">
                            <header class="lccp-form-card-header">
                                <?php if ($due_date) : ?>
                                    <div class="lccp-form-due-date <?php echo current_time('timestamp') > $due_date ? 'expired' : ''; ?>">
                                        <?php 
                                        if (current_time('timestamp') > $due_date) {
                                            _e('Expired', 'lccp-foundations');
                                        } else {
                                            printf(
                                                __('Due: %s', 'lccp-foundations'),
                                                date_i18n(get_option('date_format'), $due_date)
                                            );
                                        }
                                        ?>
                                    </div>
                                <?php endif; ?>

                                <?php the_title('<h2 class="lccp-form-card-title">', '</h2>'); ?>

                                <?php if (has_excerpt()) : ?>
                                    <div class="lccp-form-card-excerpt">
                                        <?php the_excerpt(); ?>
                                    </div>
                                <?php endif; ?>
                            </header>

                            <div class="lccp-form-card-meta">
                                <?php if ($submission_count > 0) : ?>
                                    <div class="lccp-form-submission-count">
                                        <?php
                                        printf(
                                            _n(
                                                'Submitted %d time',
                                                'Submitted %d times',
                                                $submission_count,
                                                'lccp-foundations'
                                            ),
                                            $submission_count
                                        );
                                        ?>
                                    </div>
                                <?php endif; ?>

                                <?php
                                // Display associated course/lesson if this is part of LearnDash
                                if (function_exists('learndash_get_course_id')) {
                                    $course_id = learndash_get_course_id($form_id);
                                    if ($course_id) {
                                        $course = get_post($course_id);
                                        if ($course) {
                                            echo '<div class="lccp-form-course">';
                                            printf(
                                                __('Part of: %s', 'lccp-foundations'),
                                                sprintf(
                                                    '<a href="%s">%s</a>',
                                                    get_permalink($course_id),
                                                    $course->post_title
                                                )
                                            );
                                            echo '</div>';
                                        }
                                    }
                                }
                                ?>
                            </div>

                            <div class="lccp-form-card-footer">
                                <?php if (!$can_submit) : ?>
                                    <div class="lccp-form-status warning">
                                        <?php 
                                        $message = LCCP_Forms::get_submission_restriction_message($form_id);
                                        echo wp_kses_post($message);
                                        ?>
                                    </div>
                                <?php endif; ?>

                                <a href="<?php the_permalink(); ?>" class="button">
                                    <?php
                                    if ($submission_count > 0 && !empty($form_settings['allow_multiple'])) {
                                        _e('Submit Again', 'lccp-foundations');
                                    } elseif ($submission_count > 0) {
                                        _e('View Submission', 'lccp-foundations');
                                    } else {
                                        _e('Start Form', 'lccp-foundations');
                                    }
                                    ?>
                                </a>
                            </div>
                        </div>
                    </article>

                <?php endwhile; ?>
            </div>

            <?php
            // Pagination
            the_posts_pagination(array(
                'mid_size' => 2,
                'prev_text' => __('Previous', 'lccp-foundations'),
                'next_text' => __('Next', 'lccp-foundations'),
            ));
            ?>

        <?php else : ?>
            <div class="no-forms-found">
                <p><?php _e('No forms available at this time.', 'lccp-foundations'); ?></p>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php
get_sidebar();
get_footer();
?> 