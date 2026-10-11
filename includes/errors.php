<?php
// Start session if not already started
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

// ==============================================
// MESSAGE/ERROR HANDLING FUNCTIONS
// ==============================================

/**
 * Set a flash message
 * @param string $type success|danger|warning|info
 * @param string $text Message content
 */
function setMessage($type, $text) {
    $_SESSION['message'] = [
        'type' => $type,
        'text' => $text,
        'time' => time()
    ];
}

/**
 * Get and clear the current message
 * @return array|null Message array or null
 */
function getMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']); // Clear after reading
        return $message;
    }
    return null;
}

/**
 * Display a message if exists
 */
function displayMessage() {
    $message = getMessage();
    if ($message) {
        $alertClass = 'alert-subtle-' . $message['type'];
        $icon = getMessageIcon($message['type']);
        
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">
                ' . $icon . ' ' . htmlspecialchars($message['text']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
    }
        // 2. Then show form errors if they exist
    if (!empty($_SESSION['form_errors'])) {
        echo '<div class="alert alert-subtle-danger alert-dismissible fade show" role="alert">
                <strong><span class="fas fa-times-circle text-danger fs-5 me-3"></span> Please fix the following:</strong>
                <ul class="mb-0">';
        
        foreach ($_SESSION['form_errors'] as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        
        echo '</ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
    }

}

/**
 * Get icon for message type
 */
function getMessageIcon($type) {
    switch($type) {
        case 'success': return '<span class="fas fa-check-circle text-success fs-5 me-3"></span>';
        case 'danger': return '  <span class="fas fa-times-circle text-danger fs-5 me-3"></span>';
        case 'warning': return '<span class="fas fa-info-circle text-warning fs-5 me-3"></span>';
        case 'info': return '  <span class="fas fa-info-circle text-info fs-5 me-3"></span>';
        default: return '';
    }
}

/**
 * Set form validation errors
 * @param array $errors Array of field errors
 */
function setFormErrors($errors) {
    $_SESSION['form_errors'] = $errors;
}

/**
 * Get form error for specific field
 */
function getFormError($field) {
    $error = $_SESSION['form_errors'][$field] ?? '';
    unset($_SESSION['form_errors'][$field]); // Clear after reading
    return $error;
}

/**
 * Check if field has error
 */
function hasError($field) {
    return !empty($_SESSION['form_errors'][$field]);
}

/**
 * Clear all form errors
 */
function clearFormErrors() {
    unset($_SESSION['form_errors']);
}

/**
 * Save form data to session
 */
function saveFormData($data) {
    $_SESSION['form_data'] = $data;
}

/**
 * Get saved form data for field
 */
function getFormData($field, $default = '') {
    $value = $_SESSION['form_data'][$field] ?? $default;
    unset($_SESSION['form_data'][$field]); // Clear after reading
    return htmlspecialchars($value);
}

/**
 * Clear saved form data
 */
function clearFormData() {
    unset($_SESSION['form_data']);
}

/**
 * Quick success message helper
 */
function setSuccess($text) {
    setMessage('success', $text);
}

/**
 * Quick error message helper
 */
function setError($text) {
    setMessage('danger', $text);
}

/**
 * Quick warning message helper
 */
function setWarning($text) {
    setMessage('warning', $text);
}

// Auto Clear Old Messages
function autoClearOldMessages() {
    if (isset($_SESSION['message']) && time() - $_SESSION['message']['time'] > 10) {
        unset($_SESSION['message']);
    }
}
?>