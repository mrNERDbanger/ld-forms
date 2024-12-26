<?php
/**
 * LCCP Form Decoder
 *
 * @package LCCP_Foundations
 */

class LCCP_Form_Decoder {
    /**
     * Store unknown field types for logging
     *
     * @var array
     */
    private $unknown_fields = array();

    /**
     * Store field IDs for conditional logic
     *
     * @var array
     */
    private $conditional_fields = array();

    /**
     * Convert a JSON form into HTML
     *
     * @param string $json_content The JSON content to decode
     * @return string The HTML form
     */
    public function decode_form($json_content) {
        $form_data = json_decode($json_content, true);
        if (!$form_data) {
            return '';
        }

        $html = '<form class="lccp-form">';
        
        if (isset($form_data['fields']) && is_array($form_data['fields'])) {
            foreach ($form_data['fields'] as $field) {
                $html .= $this->process_field($field);
            }
        }

        if (isset($form_data['button']) && is_array($form_data['button'])) {
            $html .= sprintf(
                '<button type="submit" class="lccp-submit">%s</button>',
                esc_html($form_data['button']['text'])
            );
        }

        // Add conditional logic JavaScript if needed
        if (!empty($this->conditional_fields)) {
            $html .= $this->render_conditional_logic_script();
        }

        $html .= '</form>';

        // Log unknown field types if any were found
        if (!empty($this->unknown_fields)) {
            error_log('LCCP Forms: Unknown field types encountered: ' . print_r($this->unknown_fields, true));
        }

        return $html;
    }

    /**
     * Process a single field
     *
     * @param array $field The field data
     * @return string The HTML for the field
     */
    private function process_field($field) {
        if (!isset($field['type'])) {
            return '';
        }

        $html = '';
        
        // Handle section type
        if ($field['type'] === 'section') {
            return sprintf(
                '<h2 class="form-section">%s</h2>%s',
                esc_html($field['label']),
                !empty($field['description']) ? '<p class="section-description">' . esc_html($field['description']) . '</p>' : ''
            );
        }

        // Handle HTML blocks
        if ($field['type'] === 'html') {
            // Skip if it's just a line break
            if (isset($field['content']) && $field['content'] === '<br></br>') {
                return '<br>';
            }
            return isset($field['content']) ? wp_kses_post($field['content']) : '';
        }

        // Start field wrapper with all field properties as data attributes
        $wrapper_attrs = array(
            'class' => 'form-field field-' . esc_attr($field['type'])
        );

        // Add visibility class if specified
        if (!empty($field['visibility'])) {
            $wrapper_attrs['class'] .= ' visibility-' . esc_attr($field['visibility']);
        }

        // Handle conditional logic
        if (!empty($field['conditionalLogic'])) {
            $wrapper_attrs['class'] .= ' has-conditional-logic';
            $wrapper_attrs['data-conditional-logic'] = esc_attr(json_encode($field['conditionalLogic']));
            $this->conditional_fields[] = $field['id'];
        }

        // Add any additional field properties as data attributes
        foreach ($field as $key => $value) {
            if (!in_array($key, ['type', 'label', 'description', 'choices', 'inputs', 'content', 'conditionalLogic'])) {
                if (is_scalar($value)) {
                    $wrapper_attrs['data-' . $key] = esc_attr($value);
                } elseif (is_array($value)) {
                    $wrapper_attrs['data-' . $key] = esc_attr(json_encode($value));
                }
            }
        }

        // Build wrapper attributes string
        $wrapper_html = '<div';
        foreach ($wrapper_attrs as $attr => $value) {
            $wrapper_html .= ' ' . $attr . '="' . $value . '"';
        }
        $wrapper_html .= '>';
        $html .= $wrapper_html;

        // Add label if it exists and isn't empty
        if (!empty($field['label'])) {
            $html .= sprintf(
                '<label for="field_%s">%s%s</label>',
                esc_attr($field['id']),
                esc_html($field['label']),
                !empty($field['isRequired']) ? ' <span class="required">*</span>' : ''
            );
        }

        // Process field based on type
        switch ($field['type']) {
            case 'text':
            case 'email':
            case 'number':
            case 'phone':
            case 'hidden':
                $html .= $this->render_input_field($field);
                break;

            case 'textarea':
                $html .= $this->render_textarea_field($field);
                break;

            case 'select':
                $html .= $this->render_select_field($field);
                break;

            case 'radio':
            case 'checkbox':
                $html .= $this->render_choice_field($field);
                break;

            case 'fileupload':
                $html .= $this->render_file_field($field);
                break;

            case 'date':
                $html .= $this->render_date_field($field);
                break;

            case 'name':
                $html .= $this->render_name_field($field);
                break;

            case 'slider':
                $html .= $this->render_slider_field($field);
                break;

            default:
                // Log unknown field type
                if (!isset($this->unknown_fields[$field['type']])) {
                    $this->unknown_fields[$field['type']] = array();
                }
                $this->unknown_fields[$field['type']][] = $field;
                
                // Render as text input by default
                $html .= $this->render_input_field($field);
                break;
        }

        // Add description if it exists
        if (!empty($field['description'])) {
            $html .= sprintf(
                '<div class="field-description">%s</div>',
                esc_html($field['description'])
            );
        }

        $html .= '</div>'; // Close field wrapper
        return $html;
    }

    /**
     * Render a basic input field
     *
     * @param array $field The field data
     * @return string The HTML for the input field
     */
    private function render_input_field($field) {
        $attrs = array(
            'type' => $field['type'],
            'id' => 'field_' . $field['id'],
            'name' => 'field_' . $field['id'],
            'class' => 'lccp-input',
        );

        if (!empty($field['isRequired'])) {
            $attrs['required'] = 'required';
        }

        if (!empty($field['placeholder'])) {
            $attrs['placeholder'] = $field['placeholder'];
        }

        if (!empty($field['defaultValue'])) {
            $attrs['value'] = $field['defaultValue'];
        }

        if (!empty($field['maxLength'])) {
            $attrs['maxlength'] = $field['maxLength'];
        }

        if (!empty($field['pattern'])) {
            $attrs['pattern'] = $field['pattern'];
        }

        return $this->build_input_element('input', $attrs);
    }

    /**
     * Render a textarea field
     *
     * @param array $field The field data
     * @return string The HTML for the textarea field
     */
    private function render_textarea_field($field) {
        $attrs = array(
            'id' => 'field_' . $field['id'],
            'name' => 'field_' . $field['id'],
            'class' => 'lccp-textarea',
        );

        if (!empty($field['isRequired'])) {
            $attrs['required'] = 'required';
        }

        if (!empty($field['placeholder'])) {
            $attrs['placeholder'] = $field['placeholder'];
        }

        $content = !empty($field['defaultValue']) ? esc_textarea($field['defaultValue']) : '';

        return $this->build_input_element('textarea', $attrs, $content);
    }

    /**
     * Render a select field
     *
     * @param array $field The field data
     * @return string The HTML for the select field
     */
    private function render_select_field($field) {
        $attrs = array(
            'id' => 'field_' . $field['id'],
            'name' => 'field_' . $field['id'],
            'class' => 'lccp-select',
        );

        if (!empty($field['isRequired'])) {
            $attrs['required'] = 'required';
        }

        $html = $this->build_input_element('select', $attrs, '', false);

        if (!empty($field['placeholder'])) {
            $html .= sprintf(
                '<option value="">%s</option>',
                esc_html($field['placeholder'])
            );
        }

        if (!empty($field['choices']) && is_array($field['choices'])) {
            foreach ($field['choices'] as $choice) {
                $option_attrs = array(
                    'value' => $choice['value'],
                );

                if (!empty($choice['isSelected'])) {
                    $option_attrs['selected'] = 'selected';
                }

                $html .= $this->build_input_element(
                    'option',
                    $option_attrs,
                    esc_html($choice['text'])
                );
            }
        }

        $html .= '</select>';
        return $html;
    }

    /**
     * Render a choice field (radio or checkbox)
     *
     * @param array $field The field data
     * @return string The HTML for the choice field
     */
    private function render_choice_field($field) {
        $html = '';
        if (!empty($field['choices']) && is_array($field['choices'])) {
            foreach ($field['choices'] as $choice) {
                $attrs = array(
                    'type' => $field['type'],
                    'id' => 'choice_' . $field['id'] . '_' . sanitize_key($choice['value']),
                    'name' => 'field_' . $field['id'] . ($field['type'] === 'checkbox' ? '[]' : ''),
                    'value' => $choice['value'],
                    'class' => 'lccp-' . $field['type'],
                );

                if (!empty($field['isRequired'])) {
                    $attrs['required'] = 'required';
                }

                if (!empty($choice['isSelected'])) {
                    $attrs['checked'] = 'checked';
                }

                $html .= '<div class="choice-wrapper">';
                $html .= $this->build_input_element('input', $attrs);
                $html .= sprintf(
                    '<label for="%s">%s</label>',
                    esc_attr($attrs['id']),
                    esc_html($choice['text'])
                );
                $html .= '</div>';
            }
        }
        return $html;
    }

    /**
     * Render a file upload field
     *
     * @param array $field The field data
     * @return string The HTML for the file field
     */
    private function render_file_field($field) {
        $attrs = array(
            'type' => 'file',
            'id' => 'field_' . $field['id'],
            'name' => 'field_' . $field['id'],
            'class' => 'lccp-file',
        );

        if (!empty($field['isRequired'])) {
            $attrs['required'] = 'required';
        }

        if (!empty($field['allowedExtensions'])) {
            $attrs['accept'] = '.' . implode(',.', $field['allowedExtensions']);
        }

        if (!empty($field['maxFiles']) && $field['maxFiles'] > 1) {
            $attrs['multiple'] = 'multiple';
        }

        return $this->build_input_element('input', $attrs);
    }

    /**
     * Render a date field
     *
     * @param array $field The field data
     * @return string The HTML for the date field
     */
    private function render_date_field($field) {
        $attrs = array(
            'type' => 'date',
            'id' => 'field_' . $field['id'],
            'name' => 'field_' . $field['id'],
            'class' => 'lccp-date',
        );

        if (!empty($field['isRequired'])) {
            $attrs['required'] = 'required';
        }

        if (!empty($field['defaultValue'])) {
            $attrs['value'] = $field['defaultValue'];
        }

        return $this->build_input_element('input', $attrs);
    }

    /**
     * Render a name field with multiple inputs
     *
     * @param array $field The field data
     * @return string The HTML for the name field
     */
    private function render_name_field($field) {
        $html = '<div class="name-inputs">';

        if (!empty($field['inputs']) && is_array($field['inputs'])) {
            foreach ($field['inputs'] as $input) {
                $attrs = array(
                    'type' => 'text',
                    'id' => 'input_' . $field['id'] . '_' . $input['id'],
                    'name' => 'input_' . $field['id'] . '_' . $input['id'],
                    'class' => 'lccp-name-input',
                );

                if (!empty($field['isRequired'])) {
                    $attrs['required'] = 'required';
                }

                if (!empty($input['placeholder'])) {
                    $attrs['placeholder'] = $input['placeholder'];
                }

                $html .= '<div class="name-input-wrapper">';
                if (!empty($input['label'])) {
                    $html .= sprintf(
                        '<label for="%s">%s</label>',
                        esc_attr($attrs['id']),
                        esc_html($input['label'])
                    );
                }
                $html .= $this->build_input_element('input', $attrs);
                $html .= '</div>';
            }
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render a slider field (1-10 scale)
     *
     * @param array $field The field data
     * @return string The HTML for the slider field
     */
    private function render_slider_field($field) {
        $min = isset($field['min']) ? intval($field['min']) : 1;
        $max = isset($field['max']) ? intval($field['max']) : 10;
        $step = isset($field['step']) ? floatval($field['step']) : 1;
        $default = isset($field['defaultValue']) ? intval($field['defaultValue']) : floor(($min + $max) / 2);

        $attrs = array(
            'type' => 'range',
            'id' => 'field_' . $field['id'],
            'name' => 'field_' . $field['id'],
            'class' => 'lccp-slider',
            'min' => $min,
            'max' => $max,
            'step' => $step,
            'value' => $default
        );

        if (!empty($field['isRequired'])) {
            $attrs['required'] = 'required';
        }

        $html = $this->build_input_element('input', $attrs);
        
        // Add value display
        $html .= sprintf(
            '<output for="field_%s" class="slider-value">%s</output>',
            esc_attr($field['id']),
            esc_html($default)
        );

        // Add JavaScript to update value display
        $html .= sprintf(
            '<script>
            document.getElementById("field_%s").addEventListener("input", function(e) {
                document.querySelector("output[for=\'field_%s\']").value = e.target.value;
            });
            </script>',
            esc_attr($field['id']),
            esc_attr($field['id'])
        );

        return $html;
    }

    /**
     * Build an HTML input element
     *
     * @param string $tag The HTML tag
     * @param array $attrs The attributes
     * @param string $content The content (for textarea/option)
     * @param bool $self_closing Whether the tag is self-closing
     * @return string The HTML element
     */
    private function build_input_element($tag, $attrs, $content = '', $self_closing = true) {
        $html = '<' . $tag;
        foreach ($attrs as $key => $value) {
            if ($value === true) {
                $html .= ' ' . $key;
            } else {
                $html .= ' ' . $key . '="' . esc_attr($value) . '"';
            }
        }
        
        if ($self_closing) {
            $html .= ' />';
        } else {
            $html .= '>' . $content;
        }
        
        return $html;
    }

    /**
     * Render the conditional logic JavaScript
     *
     * @return string The JavaScript code
     */
    private function render_conditional_logic_script() {
        ob_start();
        ?>
        <script>
        (function($) {
            'use strict';

            class ConditionalLogic {
                constructor(form) {
                    this.form = form;
                    this.fields = {};
                    this.init();
                }

                init() {
                    // Initialize fields with conditional logic
                    this.form.find('.has-conditional-logic').each((i, el) => {
                        const $field = $(el);
                        const fieldId = $field.data('id');
                        const logic = $field.data('conditional-logic');
                        
                        if (logic) {
                            this.fields[fieldId] = {
                                element: $field,
                                logic: logic
                            };
                        }
                    });

                    // Add change event listeners
                    this.form.on('change', 'input, select, textarea', (e) => {
                        this.evaluateAllRules();
                    });

                    // Initial evaluation
                    this.evaluateAllRules();
                }

                evaluateAllRules() {
                    Object.keys(this.fields).forEach(fieldId => {
                        const field = this.fields[fieldId];
                        const show = this.evaluateRules(field.logic);
                        field.element.toggle(show);

                        // If field is required and hidden, temporarily remove required attribute
                        const inputs = field.element.find('input, select, textarea');
                        if (show) {
                            inputs.each((i, input) => {
                                if ($(input).data('was-required')) {
                                    $(input).prop('required', true);
                                    $(input).removeData('was-required');
                                }
                            });
                        } else {
                            inputs.each((i, input) => {
                                const $input = $(input);
                                if ($input.prop('required')) {
                                    $input.data('was-required', true);
                                    $input.prop('required', false);
                                }
                            });
                        }
                    });
                }

                evaluateRules(logic) {
                    if (!logic || !logic.rules || !logic.rules.length) {
                        return true;
                    }

                    const results = logic.rules.map(rule => this.evaluateRule(rule));
                    
                    return logic.actionType === 'show'
                        ? (logic.logicType === 'all' ? results.every(r => r) : results.some(r => r))
                        : (logic.logicType === 'all' ? !results.every(r => r) : !results.some(r => r));
                }

                evaluateRule(rule) {
                    const $field = this.form.find(`[name="field_${rule.fieldId}"]`);
                    if (!$field.length) return false;

                    const value = $field.val();
                    
                    switch (rule.operator) {
                        case 'is':
                            return value === rule.value;
                        case 'isnot':
                            return value !== rule.value;
                        case 'contains':
                            return value.includes(rule.value);
                        case 'notcontains':
                            return !value.includes(rule.value);
                        case 'greater_than':
                            return parseFloat(value) > parseFloat(rule.value);
                        case 'less_than':
                            return parseFloat(value) < parseFloat(rule.value);
                        case 'starts_with':
                            return value.startsWith(rule.value);
                        case 'ends_with':
                            return value.endsWith(rule.value);
                        case 'is_empty':
                            return !value || value.length === 0;
                        case 'is_not_empty':
                            return value && value.length > 0;
                        default:
                            return false;
                    }
                }
            }

            // Initialize conditional logic for each form
            $('.lccp-form').each((i, form) => {
                new ConditionalLogic($(form));
            });
        })(jQuery);
        </script>
        <?php
        return ob_get_clean();
    }
} 