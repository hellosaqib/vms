<?php
declare(strict_types=1);

/**
 * Determine whether a question is suitable for BritDrive AI.
 *
 * This is a local, deterministic filter.
 * It does NOT call an AI provider.
 */
function aiClassifyQuestion(string $question): string
{
    $question = trim(mb_strtolower($question));

    if ($question === '') {
        return 'blocked';
    }

    /*
     * Allow basic conversational greetings locally.
     * These do not need to be sent to the AI provider.
     */
    $greetingPatterns = [
        '/^(hi|hello|hey|hiya|good morning|good afternoon|good evening)[!. ]*$/i',
    ];

    foreach ($greetingPatterns as $pattern) {
        if (preg_match($pattern, $question)) {
            return 'greeting';
        }
    }

    /*
     * Strong indicators that the question is unrelated
     * to BritDrive/VMS work.
     */
    $blockedPatterns = [
        'weather',
        'football',
        'soccer',
        'cricket',
        'politics',
        'politician',
        'prime minister',
        'president',
        'election',
        'recipe',
        'cooking',
        'movie',
        'film',
        'tv show',
        'celebrity',
        'joke',
        'poem',
        'homework',
        'school assignment',
        'video game',
        'gaming',
        'bitcoin',
        'crypto',
        'stock market',
        'news',
    ];

    foreach ($blockedPatterns as $term) {
        if (mb_stripos($question, $term) !== false) {
            return 'blocked';
        }
    }

    /*
     * BritDrive/VMS-related terms.
     */
    $britDriveTerms = [
        'britdrive',
        'sales',
        'sale',
        'sold',
        'invoice',
        'customer',
        'vehicle',
        'car',
        'stock',
        'dealership',
        'finance',
        'financial',
        'financial summary',
        'payment',
        'balance',
        'refund',
        'return',
        'commission',
        'salesperson',
        'salesman',
        'manager',
        'valeter',
        'mechanic',
        'auction',
        'task',
        'tasks',
        'profit',
        'revenue',
        'earning',
        'earnings',
        'target',
        'performance',
        'vms',
    ];

    foreach ($britDriveTerms as $term) {
        if (mb_stripos($question, $term) !== false) {
            return 'allowed';
        }
    }

    /*
     * Anything that does not clearly relate to BritDrive
     * is rejected rather than being sent to the paid provider.
     */
    return 'blocked';
}


/**
 * Return a safe local response for blocked questions.
 */
function aiGetRestrictionMessage(): string
{
    return 'I can only help with BritDrive work, such as sales, vehicles, customers, finance, payments, tasks, performance and other authorised VMS information.';
}


/**
 * Return a local greeting.
 */
function aiGetGreetingMessage(): string
{
    return 'Hi! I’m BritDrive AI. How can I help you with BritDrive today?';
}
?>