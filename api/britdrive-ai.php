<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');


/*
 * ---------------------------------------------------------
 * CORE AUTHENTICATION / CONFIGURATION
 * ---------------------------------------------------------
 */

require_once __DIR__ . '/../includes/auth/auth.php';
require_once __DIR__ . '/../includes/auth/permissions.php';
require_once __DIR__ . '/../includes/config/config.php';


/*
 * ---------------------------------------------------------
 * BRITDRIVE AI
 * ---------------------------------------------------------
 */

require_once __DIR__ . '/../includes/ai/access.php';
require_once __DIR__ . '/../includes/ai/provider.php';
require_once __DIR__ . '/../includes/ai/filter.php';
require_once __DIR__ . '/../includes/ai/presentation.php';


/*
 * ---------------------------------------------------------
 * REQUIRE LOGIN
 * ---------------------------------------------------------
 */

requireLogin();


/*
 * ---------------------------------------------------------
 * REQUEST METHOD
 * ---------------------------------------------------------
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'error' => 'POST requests only.'
    ]);

    exit;
}


/*
 * ---------------------------------------------------------
 * READ REQUEST
 * ---------------------------------------------------------
 */

$input = json_decode(
    file_get_contents('php://input'),
    true
);


if (!is_array($input)) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error' => 'Invalid request.'
    ]);

    exit;
}


$question = trim(
    (string) ($input['question'] ?? '')
);


/*
 * ---------------------------------------------------------
 * QUESTION CLASSIFICATION
 * ---------------------------------------------------------
 */

$classification = aiClassifyQuestion(
    $question
);


/*
 * ---------------------------------------------------------
 * BLOCKED QUESTION
 * ---------------------------------------------------------
 */

if ($classification === 'blocked') {

    echo json_encode([
        'success' => true,

        'response' =>
            aiGetRestrictionMessage()
    ]);

    exit;
}


/*
 * ---------------------------------------------------------
 * GREETING
 * ---------------------------------------------------------
 */

if ($classification === 'greeting') {

    echo json_encode([
        'success' => true,

        'response' =>
            aiGetGreetingMessage()
    ]);

    exit;
}


/*
 * ---------------------------------------------------------
 * EMPTY QUESTION
 * ---------------------------------------------------------
 */

if ($question === '') {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error' => 'Please enter a question.'
    ]);

    exit;
}


/*
 * ---------------------------------------------------------
 * ASK BRITDRIVE AI
 * ---------------------------------------------------------
 */

try {

    /*
     * aiAskWithTools() now returns:
     *
     * [
     *     'response' => '...',
     *     'tool_name' => '...',
     *     'tool_result' => [...]
     * ]
     */
    $aiResult = aiAskWithTools(
        $question,
        $db
    );


    /*
     * -----------------------------------------------------
     * EXTRACT AI RESPONSE
     * -----------------------------------------------------
     */

    $response =
        (string) (
            $aiResult['response']
            ?? ''
        );


    /*
     * -----------------------------------------------------
     * EXTRACT TOOL INFORMATION
     * -----------------------------------------------------
     */

    $toolName =
        $aiResult['tool_name']
        ?? null;


    $toolResult =
        $aiResult['tool_result']
        ?? null;


    /*
     * -----------------------------------------------------
     * BUILD PRESENTATION
     * -----------------------------------------------------
     *
     * presentation.php decides whether this tool result
     * has a supported UI representation.
     */
    $presentation =
        aiBuildPresentation(
            $toolName,
            $toolResult
        );


    /*
     * -----------------------------------------------------
     * BUILD RESPONSE
     * -----------------------------------------------------
     */

    $output = [
        'success' => true,

        'response' => $response
    ];


    /*
     * Only include presentation when one exists.
     *
     * This preserves the existing response format for
     * unsupported tools and normal text responses.
     */
    if ($presentation !== null) {

        $output['presentation'] =
            $presentation;
    }


    /*
     * -----------------------------------------------------
     * RETURN JSON
     * -----------------------------------------------------
     */

    echo json_encode(
        $output,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

} catch (Throwable $e) {

    /*
     * Log the real technical error.
     */
    error_log(
        'BritDrive AI endpoint error: ' .
        $e->getMessage()
    );


    /*
     * Return a safe message to the browser.
     */
    error_log(sprintf(
    'BritDriveAI exception: %s in %s:%d',
    $e->getMessage(),
    $e->getFile(),
    $e->getLine()
));
    http_response_code(500);

    echo json_encode([
        'success' => false,

        'error' =>
            'BritDrive AI could not process your request.'
    ]);
}
?>