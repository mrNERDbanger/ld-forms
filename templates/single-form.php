<?php
/**
 * Template for displaying single forms
 *
 * @package LCCP_Foundations
 */

get_header();

while (have_posts()) :
    the_post();
    $form_id = get_the_ID();
    $form_settings = get_post_meta($form_id, '_lccp_form_settings', true);
    $form_content = get_post_meta($form_id, '_lccp_form_content', true);
    $can_submit = LCCP_Forms::can_user_submit_form($form_id);
    ?>

    <div id="primary" class="content-area">
        <main id="main" class="site-main">
            <article id="post-<?php the_ID(); ?>" <?php post_class('lccp-form-container'); ?>>
                <header class="lccp-form-header">
                    <?php the_title('<h1 class="lccp-form-title">', '</h1>'); ?>
                    <?php if (has_excerpt()) : ?>
                        <div class="lccp-form-description">
                            <?php the_excerpt(); ?>
                        </div>
                    <?php endif; ?>
                </header>

                <?php if (!$can_submit) : ?>
                    <div class="lccp-form-message warning">
                        <?php 
                        $message = LCCP_Forms::get_submission_restriction_message($form_id);
                        echo wp_kses_post($message);
                        ?>
                    </div>
                <?php else : ?>
                    <div class="lccp-form-content">
                        <?php if (!empty($form_content)) : ?>
                            <form id="lccp-form-<?php echo esc_attr($form_id); ?>" 
                                  class="lccp-form" 
                                  method="post" 
                                  enctype="multipart/form-data">
                                
                                <?php 
                                // Output nonce field
                                wp_nonce_field('lccp_form_submission', 'lccp_form_nonce');
                                ?>

                                <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
                                
                                <?php 
                                // Display form fields
                                echo wp_kses_post($form_content);
                                ?>

                                <div class="lccp-form-submit">
                                    <button type="submit" class="button button-primary">
                                        <?php 
                                        $submit_text = !empty($form_settings['submit_text']) 
                                            ? $form_settings['submit_text'] 
                                            : __('Submit Form', 'lccp-foundations');
                                        echo esc_html($submit_text);
                                        ?>
                                    </button>
                                </div>
                            </form>

                            <div id="form-messages"></div>

                        <?php else : ?>
                            <div class="lccp-form-message error">
                                <?php _e('This form appears to be empty or incorrectly configured.', 'lccp-foundations'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php 
                // If LearnDash is active and this is a lesson
                if (function_exists('learndash_get_post_type_slug') && 
                    get_post_type() === learndash_get_post_type_slug('lesson')) {
                    learndash_get_template_part(
                        'modules/lesson-progression.php',
                        array(
                            'course_id' => learndash_get_course_id(),
                            'lesson_id' => get_the_ID(),
                            'user_id' => get_current_user_id(),
                        ),
                        true
                    );
                }
                ?>
            </article>
        </main>
    </div>

    <?php
endwhile;

get_sidebar();
get_footer();
?>

<script>
jQuery(document).ready(function($) {
    const $form = $('.lccp-form');
    const $messages = $('#form-messages');

    $form.on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'lccp_submit_form');
        formData.append('nonce', lccpForms.nonce);

        $.ajax({
            url: lccpForms.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $form.find('button[type="submit"]').prop('disabled', true);
                $messages.html(`
                    <div class="lccp-form-message info">
                        <p><?php echo esc_js(__('Submitting form...', 'lccp-foundations')); ?></p>
                    </div>
                `);
            },
            success: function(response) {
                if (response.success) {
                    $messages.html(`
                        <div class="lccp-form-message success">
                            <p>${response.data.message}</p>
                        </div>
                    `);
                    
                    if (response.data.redirect) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 1000);
                    } else {
                        $form[0].reset();
                    }

                    // If this is a LearnDash lesson, mark it complete
                    if (typeof learndash_mark_complete === 'function') {
                        learndash_mark_complete();
                    }
                } else {
                    $messages.html(`
                        <div class="lccp-form-message error">
                            <p>${response.data}</p>
                        </div>
                    `);
                }
            },
            error: function(xhr) {
                let message = '<?php echo esc_js(__('An error occurred while submitting the form.', 'lccp-foundations')); ?>';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    message = xhr.responseJSON.data;
                }
                $messages.html(`
                    <div class="lccp-form-message error">
                        <p>${message}</p>
                    </div>
                `);
            },
            complete: function() {
                $form.find('button[type="submit"]').prop('disabled', false);
                
                // Scroll to messages
                $('html, body').animate({
                    scrollTop: $messages.offset().top - 100
                }, 500);
            }
        });
    });

    // Handle file inputs
    $('input[type="file"]').on('change', function() {
        const $input = $(this);
        const files = $input[0].files;
        const $label = $input.next('.file-label');
        
        if (files.length > 0) {
            const fileNames = Array.from(files).map(file => file.name).join(', ');
            $label.text(fileNames);
        } else {
            $label.text('<?php echo esc_js(__('Choose file', 'lccp-foundations')); ?>');
        }
    });

    // Handle sliders
    $('input[type="range"]').on('input', function() {
        const $slider = $(this);
        const $value = $slider.next('.slider-value');
        $value.text($slider.val());
    });
});
</script> 