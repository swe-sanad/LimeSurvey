<?php

/**
 * Shared HTML-fragment error rendering used by controllers to display
 * model validation errors (previously duplicated in UserManagementController
 * and UserRoleController).
 */
trait RenderErrorsTrait
{
    /**
     * Returns HTML fragment of errors
     *
     * @param array $errors
     *
     * @return string $errorDiv
     */
    private function renderErrors(array $errors): string
    {
        $errorDiv = '<ul class="list-unstyled">';
        foreach ($errors as $key => $error) {
            foreach ($error as $errorMessages) {
                $errorDiv .= '<li>' . print_r($errorMessages, true) . '</li>';
            }
        }
        $errorDiv .= '</ul>';
        return (string) $errorDiv;
    }
}
