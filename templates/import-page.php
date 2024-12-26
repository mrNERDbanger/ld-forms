<?php
/**
 * Form Import Page Template
 *
 * @package LCCP_Foundations
 */

// Check if user has permission
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'lccp-foundations'));
}
?>

<div class="wrap">
    <h1><?php _e('Import Forms', 'lccp-foundations'); ?></h1>

    <div class="lccp-import-container">
        <div class="lccp-import-card">
            <div class="lccp-import-header">
                <h2><?php _e('Import JSON Form', 'lccp-foundations'); ?></h2>
                <p><?php _e('Upload a JSON file containing form data to import it as a new form.', 'lccp-foundations'); ?></p>
            </div>

            <div class="lccp-import-body">
                <form id="lccp-import-form" method="post" enctype="multipart/form-data">
                    <div class="form-field">
                        <label for="form_file"><?php _e('Select JSON File', 'lccp-foundations'); ?></label>
                        <input type="file" 
                               id="form_file" 
                               name="form_file" 
                               accept=".json" 
                               required 
                               class="lccp-file-input" />
                    </div>

                    <div id="import-preview" class="form-preview" style="display: none;">
                        <h3><?php _e('Form Preview', 'lccp-foundations'); ?></h3>
                        <div class="preview-content"></div>
                    </div>

                    <div class="form-field">
                        <div class="import-options">
                            <label>
                                <input type="checkbox" 
                                       name="import_settings" 
                                       value="1" 
                                       checked />
                                <?php _e('Import form settings', 'lccp-foundations'); ?>
                            </label>
                            <label>
                                <input type="checkbox" 
                                       name="import_notifications" 
                                       value="1" 
                                       checked />
                                <?php _e('Import notification settings', 'lccp-foundations'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="form-submit">
                        <button type="submit" class="button button-primary">
                            <?php _e('Import Form', 'lccp-foundations'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <div id="import-messages"></div>
        </div>

        <div class="lccp-import-help">
            <h3><?php _e('Import Instructions', 'lccp-foundations'); ?></h3>
            <ol>
                <li><?php _e('Prepare a JSON file containing your form data.', 'lccp-foundations'); ?></li>
                <li><?php _e('Click "Select JSON File" and choose your file.', 'lccp-foundations'); ?></li>
                <li><?php _e('Review the form preview to ensure everything looks correct.', 'lccp-foundations'); ?></li>
                <li><?php _e('Choose which settings to import.', 'lccp-foundations'); ?></li>
                <li><?php _e('Click "Import Form" to create the form.', 'lccp-foundations'); ?></li>
            </ol>

            <div class="import-notes">
                <h4><?php _e('Notes:', 'lccp-foundations'); ?></h4>
                <ul>
                    <li><?php _e('The JSON file must be properly formatted.', 'lccp-foundations'); ?></li>
                    <li><?php _e('Maximum file size: 2MB', 'lccp-foundations'); ?></li>
                    <li><?php _e('Supported field types will be automatically converted.', 'lccp-foundations'); ?></li>
                    <li><?php _e('Unsupported field types will be converted to text inputs.', 'lccp-foundations'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.lccp-import-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.lccp-import-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.lccp-import-header {
    padding: 20px;
    border-bottom: 1px solid #ccd0d4;
}

.lccp-import-header h2 {
    margin: 0 0 10px;
}

.lccp-import-header p {
    margin: 0;
    color: #666;
}

.lccp-import-body {
    padding: 20px;
}

.form-field {
    margin-bottom: 20px;
}

.form-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.lccp-file-input {
    width: 100%;
    padding: 10px;
    border: 2px dashed #ccd0d4;
    border-radius: 4px;
    cursor: pointer;
}

.lccp-file-input:hover {
    border-color: #0073aa;
}

.form-preview {
    margin: 20px 0;
    padding: 20px;
    background: #f8f9fa;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.import-options {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.import-options label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: normal;
}

.form-submit {
    margin-top: 30px;
}

#import-messages {
    padding: 20px;
    border-top: 1px solid #ccd0d4;
}

.lccp-import-help {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.lccp-import-help h3 {
    margin-top: 0;
}

.lccp-import-help ol {
    margin: 0 0 20px;
    padding-left: 20px;
}

.lccp-import-help li {
    margin-bottom: 10px;
    line-height: 1.4;
}

.import-notes {
    background: #f8f9fa;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px;
    margin-top: 20px;
}

.import-notes h4 {
    margin: 0 0 10px;
}

.import-notes ul {
    margin: 0;
    padding-left: 20px;
}

.import-notes li {
    margin-bottom: 5px;
    color: #666;
}

/* Messages */
.notice {
    margin: 0 0 20px;
}

.notice p {
    margin: 0.5em 0;
    padding: 2px;
}

.notice-success {
    border-left-color: #46b450;
}

.notice-error {
    border-left-color: #dc3232;
}
</style>

<script>
jQuery(document).ready(function($) {
    const $form = $('#lccp-import-form');
    const $fileInput = $('#form_file');
    const $preview = $('#import-preview');
    const $previewContent = $preview.find('.preview-content');
    const $messages = $('#import-messages');

    // Handle file selection for preview
    $fileInput.on('change', function(e) {
        const file = e.target.files[0];
        if (!file) {
            $preview.hide();
            return;
        }

        // Check file size
        if (file.size > 2 * 1024 * 1024) { // 2MB
            $messages.html('<div class="notice notice-error"><p>' + 
                '<?php echo esc_js(__('File size exceeds 2MB limit.', 'lccp-foundations')); ?>' + 
                '</p></div>');
            $fileInput.val('');
            return;
        }

        // Check file type
        if (file.type !== 'application/json') {
            $messages.html('<div class="notice notice-error"><p>' + 
                '<?php echo esc_js(__('Please select a JSON file.', 'lccp-foundations')); ?>' + 
                '</p></div>');
            $fileInput.val('');
            return;
        }

        // Read and preview file
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const json = JSON.parse(e.target.result);
                $previewContent.html('<pre>' + JSON.stringify(json, null, 2) + '</pre>');
                $preview.show();
                $messages.empty();
            } catch (error) {
                $messages.html('<div class="notice notice-error"><p>' + 
                    '<?php echo esc_js(__('Invalid JSON format.', 'lccp-foundations')); ?>' + 
                    '</p></div>');
                $fileInput.val('');
                $preview.hide();
            }
        };
        reader.readAsText(file);
    });

    // Handle form submission
    $form.on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'lccp_import_form');
        formData.append('nonce', lccpFormsAdmin.nonce);

        $.ajax({
            url: lccpFormsAdmin.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $form.find('button[type="submit"]').prop('disabled', true);
                $messages.html('<div class="notice notice-info"><p>' + 
                    '<?php echo esc_js(__('Importing form...', 'lccp-foundations')); ?>' + 
                    '</p></div>');
            },
            success: function(response) {
                if (response.success) {
                    $messages.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    $form[0].reset();
                    $preview.hide();
                } else {
                    $messages.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $messages.html('<div class="notice notice-error"><p>' + 
                    '<?php echo esc_js(__('An error occurred during import.', 'lccp-foundations')); ?>' + 
                    '</p></div>');
            },
            complete: function() {
                $form.find('button[type="submit"]').prop('disabled', false);
            }
        });
    });
});
</script> 