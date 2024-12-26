(function($) {
    'use strict';

    $(document).ready(function() {
        const $form = $('#lccp-form-import');
        const $results = $('#import-results');
        const $messages = $('#import-messages');

        $form.on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'lccp_import_forms');
            formData.append('nonce', lccpFormsAdmin.nonce);

            $.ajax({
                url: lccpFormsAdmin.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $form.find('button').prop('disabled', true).text('Importing...');
                    $messages.empty();
                    $results.hide();
                },
                success: function(response) {
                    if (response.success && response.data) {
                        $results.show();
                        response.data.forEach(function(result) {
                            const messageClass = result.success ? 'notice-success' : 'notice-error';
                            $messages.append(
                                `<div class="notice ${messageClass} is-dismissible"><p>${result.message}</p></div>`
                            );
                        });
                    }
                },
                error: function() {
                    $results.show();
                    $messages.html(
                        '<div class="notice notice-error is-dismissible"><p>Import failed. Please try again.</p></div>'
                    );
                },
                complete: function() {
                    $form.find('button').prop('disabled', false).text('Import Forms');
                }
            });
        });
    });
})(jQuery); 