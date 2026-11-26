<?php

declare(strict_types=1);

/**
 * Reads native metadata for each registered SELECT expression in its original position.
 */
function mysqlTextCollationObservations(PDO $pdo, string $sql): array
{
    [$expressions, $source] = mysqlTextCollationLayout($sql);
    $columns = [];
    foreach ($expressions as $expression) {
        $columns[] = "CHARSET(($expression)), COLLATION(($expression)), COERCIBILITY(($expression))";
    }
    // The original evaluation observes the rows under all original clauses.
    // This companion probe also sees static metadata for empty keyed groups.
    $cursor = $pdo->query('SELECT ' . implode(', ', $columns) . $source . ' LIMIT 1');
    $values = $cursor->fetch(PDO::FETCH_NUM);
    $cursor->closeCursor();
    if (!is_array($values) || count($values) !== count($expressions) * 3) {
        throw new RuntimeException('The registered SELECT did not yield one metadata triple per position.');
    }

    return array_chunk($values, 3);
}

/**
 * Finds the top-level projection and source in the evidence suite's SELECT forms.
 */
function mysqlTextCollationLayout(string $sql): array
{
    $token = <<<'PATTERN'
~'(?:\\[\s\S]|''|[^'\\])*'|`(?:``|[^`])*`|[a-zA-Z_][a-zA-Z0-9_]*|[(),]|\s+|.~s
PATTERN;
    if (preg_match_all($token, $sql, $matches, PREG_OFFSET_CAPTURE) === false
        || strtoupper($matches[0][0][0] ?? '') !== 'SELECT') {
        throw new RuntimeException('Expected a registered SELECT projection.');
    }
    $expressions = [];
    $start = 6;
    $alias = null;
    $depth = 0;
    $from = null;
    $end = strlen($sql);
    foreach (array_slice($matches[0], 1) as [$value, $offset]) {
        if ($value === '(') {
            ++$depth;
        } elseif ($value === ')') {
            --$depth;
        }
        if ($depth !== 0) {
            continue;
        }
        $kind = strtoupper($value);
        if (in_array($kind, ['WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT'], true)) {
            $end = $offset;
            break;
        }
        if ($from !== null) {
            continue;
        }
        if ($kind === 'AS') {
            $alias = $offset;
        } elseif ($kind === ',' || $kind === 'FROM') {
            $expressions[] = trim(substr($sql, $start, ($alias ?? $offset) - $start));
            $start = $offset + 1;
            $alias = null;
            if ($kind === 'FROM') {
                $from = $offset;
            }
        }
    }
    if ($from === null) {
        $selectionEnd = $alias ?? $end;
        $expressions[] = trim(substr($sql, $start, $selectionEnd - $start));
    }
    if ($depth !== 0 || $expressions === [] || count($expressions) > 6 || in_array('', $expressions, true)) {
        throw new RuntimeException('Unexpected registered SELECT projection layout.');
    }

    return [$expressions, $from === null ? '' : ' ' . trim(substr($sql, $from, $end - $from))];
}
