<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Extension;

use Xoops\SmartyExtensions\AbstractExtension;

/**
 * Form-related Smarty function plugins.
 *
 * Provides form open/close, input rendering, button creation,
 * validation, and error display with automatic CSRF token injection.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
final class FormExtension extends AbstractExtension
{
    public function __construct(private readonly ?\XoopsSecurity $security = null)
    {
    }

    public function getFunctions(): array
    {
        return [
            'form_open' => $this->formOpen(...),
            'form_close' => $this->formClose(...),
            'form_input' => $this->formInput(...),
            'create_button' => $this->createButton(...),
            'render_form_errors' => $this->renderFormErrors(...),
            'validate_form' => $this->validateForm(...),
            'validate_email' => $this->validateEmail(...),
            'display_error' => $this->displayError(...),
        ];
    }

    /**
     * Opens an HTML form tag with automatic XOOPS CSRF token injection.
     *
     * Usage: <{form_open action="save.php" method="post"}>
     *        <{form_open action="upload.php" method="post" enctype="multipart/form-data"}>
     */
    public function formOpen(array $params, object $template): string
    {
        $action = \htmlspecialchars($params['action'] ?? '', ENT_QUOTES, 'UTF-8');
        $method = \htmlspecialchars($params['method'] ?? 'post', ENT_QUOTES, 'UTF-8');

        $html = '<form action="' . $action . '" method="' . $method . '"';

        // Pass through optional HTML attributes
        $passthrough = ['enctype', 'class', 'id', 'name', 'target', 'autocomplete'];
        foreach ($passthrough as $attr) {
            if (isset($params[$attr])) {
                $html .= ' ' . $attr . '="' . \htmlspecialchars($params[$attr], ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        $html .= '>';

        // Inject XOOPS CSRF token for POST forms (when security is available)
        if ($this->security !== null && \strtolower($params['method'] ?? 'post') === 'post') {
            $html .= "\n" . $this->security->getTokenHTML();
        }

        return $html;
    }

    /**
     * Closes an HTML form tag.
     *
     * Usage: <{form_close}>
     */
    public function formClose(array $params, object $template): string
    {
        return '</form>';
    }

    /**
     * Renders an HTML input element.
     *
     * Usage: <{form_input type="text" name="title" value=$title class="form-control"}>
     *        <{form_input type="email" name="email" value=$email placeholder="you@example.com"}>
     */
    public function formInput(array $params, object $template): string
    {
        $type = \htmlspecialchars($params['type'] ?? 'text', ENT_QUOTES, 'UTF-8');
        $name = \htmlspecialchars($params['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $value = \htmlspecialchars((string) ($params['value'] ?? ''), ENT_QUOTES, 'UTF-8');

        $html = '<input type="' . $type . '" name="' . $name . '" value="' . $value . '"';

        // Pass through optional HTML attributes
        $reserved = ['type', 'name', 'value', 'assign'];
        foreach ($params as $attr => $attrValue) {
            if (!\in_array($attr, $reserved, true)) {
                $html .= ' ' . \htmlspecialchars($attr, ENT_QUOTES, 'UTF-8') . '="' . \htmlspecialchars((string) $attrValue, ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        $html .= ' />';

        return $html;
    }

    /**
     * Renders an HTML button element.
     *
     * Note: this function is an exception to the raw-in-assign contract.
     * When `assign` is set, the stored value is already-escaped HTML markup,
     * not raw data. Do not apply |escape or htmlspecialchars() when
     * interpolating the assigned variable into a template.
     *
     * Usage: <{create_button label="Save" type="submit" class="btn btn-primary"}>
     *        <{create_button label="Delete" type="button" class="btn btn-danger" icon="bi-trash"}>
     *        <{create_button label="Save" type="submit" assign="saveBtn"}>  <{$saveBtn nofilter}>
     */
    public function createButton(array $params, object $template): string
    {
        $label = \htmlspecialchars($params['label'] ?? 'Button', ENT_QUOTES, 'UTF-8');
        $type = \htmlspecialchars($params['type'] ?? 'button', ENT_QUOTES, 'UTF-8');
        $class = \htmlspecialchars($params['class'] ?? 'btn', ENT_QUOTES, 'UTF-8');
        $icon = $params['icon'] ?? '';

        $iconHtml = '';
        if ($icon !== '') {
            $safeIcon = \htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');
            $iconHtml = '<i class="' . $safeIcon . '"></i> ';
        }

        $html = '<button type="' . $type . '" class="' . $class . '">' . $iconHtml . $label . '</button>';

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $html);
            return '';
        }

        return $html;
    }

    /**
     * Renders form validation errors as a Bootstrap 5 alert list.
     *
     * Note: this function is an exception to the raw-in-assign contract.
     * When `assign` is set, the stored value is already-escaped HTML markup,
     * not raw data. Do not apply |escape or htmlspecialchars() when
     * interpolating the assigned variable into a template.
     *
     * Usage: <{render_form_errors errors=$errors}>
     *        <{render_form_errors errors=$errors assign="errHtml"}>  <{$errHtml nofilter}>
     */
    public function renderFormErrors(array $params, object $template): string
    {
        $errors = $params['errors'] ?? [];

        if (empty($errors)) {
            return '';
        }

        $html = '<div class="alert alert-danger"><ul class="mb-0">';
        foreach ($errors as $field => $fieldErrors) {
            $safeField = \htmlspecialchars((string) $field, ENT_QUOTES, 'UTF-8');
            if (\is_array($fieldErrors)) {
                foreach ($fieldErrors as $error) {
                    $html .= '<li><strong>' . $safeField . ':</strong> ' . \htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') . '</li>';
                }
            } else {
                $html .= '<li><strong>' . $safeField . ':</strong> ' . \htmlspecialchars((string) $fieldErrors, ENT_QUOTES, 'UTF-8') . '</li>';
            }
        }
        $html .= '</ul></div>';

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $html);
            return '';
        }

        return $html;
    }

    /**
     * Validates form data against a set of rules.
     *
     * This function always returns an empty string. Validation errors are
     * only accessible via the `assign` parameter — the errors array is stored
     * in the named template variable and never inlined as output. This is
     * intentional: errors are structured data, not a string to interpolate.
     *
     * Usage: <{validate_form data=$formData rules=$validationRules assign="errors"}>
     *        <{render_form_errors errors=$errors}>
     *
     * Rules format:
     *   ['field_name' => ['required' => true, 'min_length' => 3, 'max_length' => 255, 'email' => true]]
     *
     * Supported rules: required, min_length, max_length (both use mb_strlen for UTF-8
     * correctness), email, numeric.
     *
     * @return string Always ''
     */
    public function validateForm(array $params, object $template): string
    {
        $data = $params['data'] ?? [];
        $rules = $params['rules'] ?? [];
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? '';
            foreach ($fieldRules as $rule => $ruleValue) {
                match ($rule) {
                    'required' => $ruleValue && $value === ''
                        ? $errors[$field][] = 'This field is required'
                        : null,
                    'min_length' => \is_string($value) && \mb_strlen($value, 'UTF-8') < (int) $ruleValue
                        ? $errors[$field][] = "Minimum length is {$ruleValue} characters"
                        : null,
                    'max_length' => \is_string($value) && \mb_strlen($value, 'UTF-8') > (int) $ruleValue
                        ? $errors[$field][] = "Maximum length is {$ruleValue} characters"
                        : null,
                    'email' => $ruleValue && $value !== '' && \filter_var($value, FILTER_VALIDATE_EMAIL) === false
                        ? $errors[$field][] = 'Invalid email address'
                        : null,
                    'numeric' => $ruleValue && $value !== '' && !\is_numeric($value)
                        ? $errors[$field][] = 'Must be a number'
                        : null,
                    default => null,
                };
            }
        }

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $errors);
            return '';
        }

        // validateForm is a data-producing function, not a rendering function.
        // Errors live in the assigned variable. There is no inline output path.
        return '';
    }

    /**
     * Validates an email address.
     *
     * Usage: <{validate_email email=$userEmail assign="isValid"}>
     */
    public function validateEmail(array $params, object $template): string
    {
        $email = $params['email'] ?? '';
        $result = \filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return $result ? '1' : '';
    }

    /**
     * Renders an error message in a styled Bootstrap 5 danger div.
     *
     * Note: this function is an exception to the raw-in-assign contract.
     * When `assign` is set, the stored value is already-escaped HTML markup,
     * not raw data. Do not apply |escape or htmlspecialchars() when
     * interpolating the assigned variable into a template.
     *
     * Usage: <{display_error message="Something went wrong"}>
     *        <{display_error message=$msg assign="errHtml"}>  <{$errHtml nofilter}>
     */
    public function displayError(array $params, object $template): string
    {
        $message = $params['message'] ?? '';

        if ($message === '') {
            return '';
        }

        $html = '<div class="alert alert-danger" role="alert">' . \htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $html);
            return '';
        }

        return $html;
    }
}
