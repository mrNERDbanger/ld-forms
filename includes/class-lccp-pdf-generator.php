<?php
/**
 * LCCP PDF Generator
 *
 * @package LCCP_Foundations
 */

class LCCP_PDF_Generator {
    /**
     * Generate PDF from form submission
     *
     * @param int $form_id The form ID
     * @param int $submission_id The submission ID
     * @return string|false The PDF content or false on failure
     */
    public function generate($form_id, $submission_id) {
        if (!class_exists('TCPDF')) {
            require_once LCCP_FOUNDATIONS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        // Get submission data
        global $wpdb;
        $table_name = $wpdb->prefix . 'lccp_form_submissions';
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $submission_id
        ));

        if (!$submission) {
            return false;
        }

        // Get form data
        $form = get_post($form_id);
        if (!$form) {
            return false;
        }

        // Get user data
        $user = get_userdata($submission->user_id);
        if (!$user) {
            return false;
        }

        // Create PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator(get_bloginfo('name'));
        $pdf->SetAuthor($user->display_name);
        $pdf->SetTitle($form->post_title . ' - Submission #' . $submission_id);

        // Set header and footer
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->setHeaderData(
            '', // Logo
            0, // Logo width
            $form->post_title, // Header title
            'Submitted by: ' . $user->display_name . ' | Date: ' . wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->submission_date))
        );

        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // Set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // Set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Set font
        $pdf->SetFont('dejavusans', '', 10);

        // Add a page
        $pdf->AddPage();

        // Get submission data
        $submission_data = json_decode($submission->submission_data, true);
        if (!$submission_data) {
            return false;
        }

        // Build HTML content
        $html = '<h1>' . esc_html($form->post_title) . '</h1>';
        $html .= '<hr>';
        
        // Add submission details
        $html .= '<table border="0" cellpadding="5">';
        $html .= '<tr><td width="30%"><strong>Submission ID:</strong></td><td>' . esc_html($submission_id) . '</td></tr>';
        $html .= '<tr><td><strong>Submitted By:</strong></td><td>' . esc_html($user->display_name) . '</td></tr>';
        $html .= '<tr><td><strong>Date:</strong></td><td>' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->submission_date))) . '</td></tr>';
        $html .= '</table>';
        $html .= '<hr>';

        // Add form fields
        $html .= '<h2>Form Responses</h2>';
        $html .= '<table border="0" cellpadding="5">';
        foreach ($submission_data as $field_id => $value) {
            // Get field label from form content or use field ID
            $field_label = $this->get_field_label($form_id, $field_id);
            
            // Format value based on field type
            $formatted_value = $this->format_field_value($value);

            $html .= sprintf(
                '<tr><td width="30%%"><strong>%s:</strong></td><td>%s</td></tr>',
                esc_html($field_label),
                $formatted_value
            );
        }
        $html .= '</table>';

        // Write HTML content
        $pdf->writeHTML($html, true, false, true, false, '');

        // Close and return PDF content
        return $pdf->Output('', 'S');
    }

    /**
     * Get field label from form content
     *
     * @param int $form_id The form ID
     * @param string $field_id The field ID
     * @return string The field label
     */
    private function get_field_label($form_id, $field_id) {
        $form = get_post($form_id);
        if (!$form) {
            return $field_id;
        }

        // Try to find field label in form content
        $pattern = '/<label[^>]*for="' . preg_quote($field_id, '/') . '"[^>]*>(.*?)<\/label>/i';
        if (preg_match($pattern, $form->post_content, $matches)) {
            return strip_tags($matches[1]);
        }

        // Fallback to field ID
        return $field_id;
    }

    /**
     * Format field value for PDF display
     *
     * @param mixed $value The field value
     * @return string The formatted value
     */
    private function format_field_value($value) {
        if (is_array($value)) {
            // Handle array values (like checkboxes)
            return implode(', ', array_map('esc_html', $value));
        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
            // Handle URLs (like file uploads)
            return sprintf('<a href="%1$s">%1$s</a>', esc_url($value));
        } else {
            // Handle regular values
            return esc_html($value);
        }
    }
} 