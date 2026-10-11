<?php
declare(strict_types=1);

require_once __DIR__ . '/access.php';
require_once __DIR__ . '/../config/database.php';

function aiGetTools(): array
{
    return [
        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_count',
                'description' =>
                    'Get the number of sales the current authenticated BritDrive user is authorised to access. Use this when the user asks how many sales they have access to or asks for the number of authorised sales.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new stdClass(),
                    'required' => []
                ]
            ]
        ]
    ];
}

function aiExecuteTool(
    string $toolName,
    array $arguments,
    PDO $db
): array {
    switch ($toolName) {

        case 'sales_get_count':
            return salesGetCount($db);

        default:
            throw new RuntimeException(
                'Unknown AI tool: ' . $toolName
            );
    }
}

/**
 * Return the number of sales the current user is authorised to access.
 */
function salesGetCount(PDO $db): array
{
    if (!aiCanAccess('sales')) {
        throw new RuntimeException('Sales access denied.');
    }

    $scope = aiSalesScope();

    if ($scope === 'none') {
        throw new RuntimeException('Sales access denied.');
    }

    $sql = "SELECT COUNT(*) AS total
            FROM sales s";

    $params = [];

    if ($scope === 'own') {
        if (empty($_SESSION['user_id'])) {
            throw new RuntimeException('User session not available.');
        }

        $sql .= " WHERE s.sales_person = ?";
        $params[] = (int) $_SESSION['user_id'];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $row = $stmt->fetch();

    return [
        'count' => (int) ($row['total'] ?? 0)
    ];
}


?>