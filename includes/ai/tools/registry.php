<?php
declare(strict_types=1);
//    LOAD TOOL ADAPTERS
require_once __DIR__ . '/sales.php';
/**
 * Return every tool currently available to BritDriveAI.
 */
function aiGetAllTools(): array
{
    $tools = [];
    $tools = array_merge(
        $tools,
        aiGetSalesTools()
    );
    return $tools;
}

/* EXECUTE AI TOOL */
/**
 * Route an AI tool call to the correct domain adapter.
 *
 * @param string $toolName
 * @param array  $arguments
 * @param PDO    $db
 *
 * @return array
 */
function aiExecuteTool(
    string $toolName,
    array $arguments,
    PDO $db
): array {
    /*
     * Sales tools
     */
    if (str_starts_with($toolName, 'sales_')) {
        return aiExecuteSalesTool(
            $toolName,
            $arguments,
            $db
        );
    }

    throw new RuntimeException(
        'Unknown BritDriveAI tool: ' . $toolName
    );
}