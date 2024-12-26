/**
 * LCCP Forms Admin JavaScript
 * 
 * Handles form import functionality and admin interactions.
 */

/* global jQuery, lccpFormsAdmin */
(function($) {
    'use strict';

    // Initialize form import functionality
    function initFormImport() {
        const $form = $('#lccp-import-form');
        const $fileInput = $('#form_file');
        const $preview = $('#import-preview');
        const $previewContent = $preview.find('.preview-content');
        const $messages = $('#import-messages');

        if (!$form.length) {
            return;
        }

        // Handle file selection for preview
        $fileInput.on('change', function(e) {
            const file = e.target.files[0];
            if (!file) {
                $preview.hide();
                return;
            }

            // Check file size (2MB limit)
            if (file.size > 2 * 1024 * 1024) {
                showError('File size exceeds 2MB limit.');
                $fileInput.val('');
                return;
            }

            // Check file type
            if (file.type !== 'application/json') {
                showError('Please select a JSON file.');
                $fileInput.val('');
                return;
            }

            // Read and preview file
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const json = JSON.parse(e.target.result);
                    validateFormData(json);
                    $previewContent.html(formatPreview(json));
                    $preview.show();
                    $messages.empty();
                } catch (error) {
                    showError('Invalid JSON format.');
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
                    showInfo('Importing form...');
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.data.message);
                        if (response.data.redirect) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1000);
                        } else {
                            $form[0].reset();
                            $preview.hide();
                        }
                    } else {
                        showError(response.data);
                    }
                },
                error: function(xhr) {
                    let message = 'An error occurred during import.';
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        message = xhr.responseJSON.data;
                    }
                    showError(message);
                },
                complete: function() {
                    $form.find('button[type="submit"]').prop('disabled', false);
                }
            });
        });

        // Helper function to show error message
        function showError(message) {
            $messages.html(`
                <div class="notice notice-error">
                    <p>${message}</p>
                </div>
            `);
        }

        // Helper function to show success message
        function showSuccess(message) {
            $messages.html(`
                <div class="notice notice-success">
                    <p>${message}</p>
                </div>
            `);
        }

        // Helper function to show info message
        function showInfo(message) {
            $messages.html(`
                <div class="notice notice-info">
                    <p>${message}</p>
                </div>
            `);
        }

        // Helper function to validate form data
        function validateFormData(json) {
            if (!json.fields || !Array.isArray(json.fields)) {
                throw new Error('Invalid form structure: missing fields array');
            }

            // Additional validation can be added here
            return true;
        }

        // Helper function to format preview
        function formatPreview(json) {
            let html = '<div class="form-preview-content">';
            
            // Add form title if available
            if (json.title) {
                html += `<h4>Form Title: ${json.title}</h4>`;
            }

            // Add fields preview
            if (json.fields && json.fields.length) {
                html += '<h4>Fields:</h4><ul>';
                json.fields.forEach(field => {
                    html += `<li>${field.label || field.name} (${field.type})</li>`;
                });
                html += '</ul>';
            }

            // Add settings preview if available
            if (json.settings) {
                html += '<h4>Settings:</h4><pre>' + 
                    JSON.stringify(json.settings, null, 2) + 
                    '</pre>';
            }

            html += '</div>';
            return html;
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initFormImport();
    });

})(jQuery); 