<?php

require_once __DIR__ . '/includes/ai/provider.php';

try {

    $response = aiAsk(
        'You are SalesAI, an assistant for BritDrive. '
        . 'Reply with exactly one short sentence confirming that you are connected.'
    );

    echo '<h1>Gemini Test</h1>';
    echo '<p>' . htmlspecialchars($response) . '</p>';

} catch (Throwable $e) {

    http_response_code(500);

    echo '<h1>Gemini Test Failed</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>