<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/tools/registry.php';


/**
 * Basic AI request without tools.
 *
 * Kept for simple AI requests that do not require
 * access to BritDrive data.
 */
function aiAsk(string $prompt): string
{
    global $OPENROUTER_API_KEY;

    if (empty($OPENROUTER_API_KEY)) {
        throw new RuntimeException(
            'OpenRouter API key is not configured.'
        );
    }

    $url = 'https://openrouter.ai/api/v1/chat/completions';

    $payload = [
        'model' => 'openrouter/free',

        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],

        'max_tokens' => 300
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $OPENROUTER_API_KEY
        ],

        CURLOPT_POSTFIELDS => json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
        ),

        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);

        curl_close($ch);

        throw new RuntimeException(
            'OpenRouter request failed: ' . $error
        );
    }

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    $data = json_decode(
        $response,
        true
    );

    if ($httpCode < 200 || $httpCode >= 300) {

        $message =
            $data['error']['message']
            ?? 'Unknown OpenRouter API error.';

        throw new RuntimeException(
            "OpenRouter API error ({$httpCode}): {$message}"
        );
    }

    $message =
        $data['choices'][0]['message']
        ?? null;

    if (!$message) {
        throw new RuntimeException(
            'OpenRouter returned no message. Raw response: ' .
            json_encode($data)
        );
    }

    $text =
        $message['content']
        ?? '';

    if (is_array($text)) {
        $text = json_encode($text);
    }

    if (trim((string) $text) === '') {
        throw new RuntimeException(
            'OpenRouter returned an empty content field. Full response: ' .
            json_encode($data)
        );
    }

    return trim((string) $text);
}


/**
 * Ask BritDriveAI a question using authorised BritDrive tools.
 *
 * Flow:
 *
 * User
 *   ↓
 * BritDriveAI
 *   ↓
 * Tool Registry
 *   ↓
 * Sales Tool Adapter
 *   ↓
 * Shared Sales Data Functions
 *   ↓
 * Permissions
 *   ↓
 * Database
 */
function aiAskWithTools(
    string $prompt,
    PDO $db
): array {

    global $OPENROUTER_API_KEY;

    if (empty($OPENROUTER_API_KEY)) {
        throw new RuntimeException(
            'OpenRouter API key is not configured.'
        );
    }

    $url =
        'https://openrouter.ai/api/v1/chat/completions';


    /*
     * Get all tools through the central registry.
     *
     * IMPORTANT:
     * Do not load domain adapters directly here.
     */
    $tools = aiGetAllTools();


    /*
     * Conversation history.
     */
    $messages = [

        [
            'role' => 'system',

            'content' =>
                'You are BritDriveAI, a specialised internal sales assistant for BritDrive. ' .
                'When the user says "my", "mine", or similar ownership wording, interpret it as the currently authenticated user authorised BritDrive data. ' .
                'Do not ask for a user ID when the authentication and permission system already identifies the user. ' .
                'Use the available tools whenever the user asks for information that requires BritDrive sales data. ' .
                'Never invent sales data. ' .
                'Only use data returned by authorised tools. ' .
                'The PHP application controls user authentication, permissions and data scope. ' .
                'Respect the information returned by the tools and do not claim access to data that was not returned. ' .
                'BritDrive uses British Pounds (GBP) for all monetary values. ' .
                'Always display monetary amounts using the £ symbol, never the $ symbol. ' .
                'The current date is ' . date('Y-m-d') . '. ' .
                'When the user uses a relative date such as today, yesterday, this week, last week, this month, last month, this year or last year, ' .
                'calculate the appropriate date range from the current date and use the relevant sales tool with those dates. ' .
                'Do not ask the user to provide dates when a relative date can be calculated from the current date. ' .
                'Do not ask the user for their salesperson ID when the available permission scope already determines which sales they are authorised to access. ' .
                'For sales data, sale_price means the base vehicle price. ' .
                'total_revenue means the final customer sale value after applicable service fees, delivery charges, additional services and discounts. ' .
                'Use total_revenue when the user asks for the total value of sales or customer sale value. ' .
                'When a request is answered using a structured presentation such as a metric or financial summary, keep the natural-language response very short. ' .
                'Do not repeat the detailed values already shown in the presentation. ' .
                'Do not use Markdown formatting, bold markers, bullet points, headings, tables, or asterisks. ' .
                'For example, for a financial summary, respond with a short sentence such as "Your financial summary is ready." ' .
                'The structured presentation will provide the detailed figures. ' 
        ],

        [
            'role' => 'user',
            'content' => $prompt
        ]
    ];


    /*
     * ---------------------------------------------------------
     * PRESENTATION DATA
     * ---------------------------------------------------------
     *
     * Keep the most recent tool information available.
     *
     * The presentation layer will use this later.
     */
    $lastToolName = null;

    $lastToolResult = null;


    /*
     * Allow multiple tool rounds.
     */
    for ($attempt = 0; $attempt < 5; $attempt++) {

        $payload = [

            'model' => 'openrouter/free',

            'messages' => $messages,

            'tools' => $tools,

            'tool_choice' => 'auto',

            'max_tokens' => 300
        ];


        /*
         * Send request to OpenRouter.
         */
        $ch = curl_init($url);

        curl_setopt_array($ch, [

            CURLOPT_POST => true,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $OPENROUTER_API_KEY
            ],

            CURLOPT_POSTFIELDS => json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
            ),

            CURLOPT_TIMEOUT => 30,
        ]);


        $response = curl_exec($ch);


        if ($response === false) {

            $error = curl_error($ch);

            curl_close($ch);

            throw new RuntimeException(
                'OpenRouter request failed: ' . $error
            );
        }


        $httpCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


        curl_close($ch);


        $data = json_decode(
            $response,
            true
        );


        /*
         * Handle OpenRouter errors.
         */
        if ($httpCode < 200 || $httpCode >= 300) {

            $message =
                $data['error']['message']
                ?? 'Unknown OpenRouter API error.';

            throw new RuntimeException(
                "OpenRouter API error ({$httpCode}): {$message}"
            );
        }


        /*
         * Extract assistant message.
         */
        $message =  $data['choices'][0]['message'] ?? null;
    $finishReason = $data['choices'][0]['finish_reason'] ?? null;
        error_log(
            'BritDriveAI response structure: ' .
            json_encode([
                'model' => $data['model'] ?? null,
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? null,
                'message_keys' => is_array($message)
                    ? array_keys($message)
                    : [],
                'reasoning_present' => !empty($message['reasoning']),
                'reasoning_content_present' => !empty($message['reasoning_content']),
                'content_type' => gettype($message['content'] ?? null),
            ])
        );

        if (!$message) {

            throw new RuntimeException(
                'OpenRouter returned an invalid response.'
            );
        }


        /*
         * ---------------------------------------------------------
         * TOOL CALL
         * ---------------------------------------------------------
         */
        if (!empty($message['tool_calls'])) {

            /*
             * Preserve the assistant's tool-call message.
             */
            $messages[] = $message;


            foreach ($message['tool_calls'] as $toolCall) {

                $toolCallId =
                    $toolCall['id']
                    ?? null;

                $toolName =
                    $toolCall['function']['name']
                    ?? null;

                $argumentsJson =
                    $toolCall['function']['arguments']
                    ?? '{}';


                /*
                 * Validate tool call.
                 */
                if (!$toolCallId || !$toolName) {

                    throw new RuntimeException(
                        'Invalid tool call returned by OpenRouter.'
                    );
                }


                /*
                 * Decode AI arguments.
                 */
                $arguments =
                    json_decode(
                        $argumentsJson,
                        true
                    );


                /*
                 * Invalid/missing arguments become
                 * an empty array.
                 */
                if (!is_array($arguments)) {
                    $arguments = [];
                }


                /*
                 * -------------------------------------------------
                 * CENTRAL TOOL REGISTRY
                 * -------------------------------------------------
                 */
                $toolResult =
                    aiExecuteTool(
                        $toolName,
                        $arguments,
                        $db
                    );


                /*
                 * -------------------------------------------------
                 * RETAIN TOOL RESULT
                 * -------------------------------------------------
                 *
                 * This does NOT change what is sent to the AI.
                 *
                 * We simply keep the trusted PHP result available
                 * for the presentation layer after the AI finishes.
                 */
                $lastToolName = $toolName;

                $lastToolResult = $toolResult;


                /*
                 * Return the PHP tool result to the AI.
                 */
                $messages[] = [

                    'role' => 'tool',

                    'tool_call_id' => $toolCallId,

                    'content' => json_encode(
                        $toolResult,
                        JSON_UNESCAPED_SLASHES
                    )
                ];
            }


            /*
             * Continue the conversation.
             */
            continue;
        }


        /*
         * ---------------------------------------------------------
         * FINAL ANSWER
         * ---------------------------------------------------------
         */
        
$content = $message['content'] ?? null;

// Extract only the user-facing answer.
if (is_string($content) && trim($content) !== '') {
    $content = trim($content);

    // Some models expose their internal reasoning separately.
    // Never use reasoning as the spoken or displayed response.
    if (!empty($message['reasoning'])) {
        // The final content is handled below; reasoning is ignored.
    }

    error_log(
        'BritDriveAI final response: ' .
        substr($content, 0, 500)
    );

    if ($finishReason === 'length') {
    throw new RuntimeException(
        'OpenRouter response was truncated by the token limit.'
    );
}
    return [
        'response' => $content,
        'tool_name' => $lastToolName,
        'tool_result' => $lastToolResult
    ];
}


        /*
         * No tool call and no final answer.
         */
        throw new RuntimeException(
            'OpenRouter returned no final answer.'
        );
    }


    /*
     * Prevent an endless tool loop.
     */
    throw new RuntimeException(
        'BritDriveAI exceeded the maximum number of tool-call rounds.'
    );
}
?>