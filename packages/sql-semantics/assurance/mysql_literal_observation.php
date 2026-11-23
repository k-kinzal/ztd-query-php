<?php

declare(strict_types=1);

/**
 * Observes plain or explicitly collated literal tokens in the registered test SQL.
 */
function mysqlLiteralObservations(PDO $pdo, string $sql): array
{
    $pattern = <<<'PATTERN'
~
(?(DEFINE)(?<quoted>'(?:\\[\s\S]|''|[^'\\])*'|"(?:\\[\s\S]|""|[^"\\])*"))
`(?:``|[^`])*`
|
(?<literal>(?&quoted)(?:[ \t\r\n]+(?&quoted))*
    (?:[ \t\r\n]+COLLATE[ \t\r\n]+(?:utf8mb4_general_ci|utf8mb4_0900_ai_ci)\b)?)
~ix
PATTERN;
    if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL) === false) {
        throw new RuntimeException('The literal metadata probe could not read the registered SQL spelling.');
    }
    $result = [];
    foreach ($matches as $match) {
        $literal = $match['literal'];
        if ($literal === null) {
            continue;
        }
        $query = $pdo->query("SELECT HEX(($literal)), CHARSET(($literal)), COLLATION(($literal)), COERCIBILITY(($literal))");
        $result[] = $query->fetch(PDO::FETCH_NUM);
        $query->closeCursor();
    }

    return $result;
}
