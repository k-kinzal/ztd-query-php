<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_drop_index_observer.php';

function admitMysqlAlterIndex(string $sql): bool
{
    if (admitDropIndex($sql)) {
        return true;
    }
    if (strlen($sql) > 800) {
        return false;
    }
    $quote = chr(96);
    $pattern = '/\AALTER (?:IGNORE )?TABLE (?:%ir_fixture%\.)?%ir_index_removal% (?&directive)(?:, (?&directive)){0,4}\z'
        . '(?(DEFINE)(?<identifier>' . DROP_INDEX_NAME . ')'
        . '(?<directive>DROP PRIMARY KEY|DROP (?:INDEX|KEY) (?:(?:%ir_fixture%|%other_database%)\.)?'
        . '(?:(?:%ir_index_removal%|%other_table%)\.)?(?&identifier)(?: RESTRICT)?'
        . '|' . ltrim(MYSQL_INDEX_ALGORITHM) . '|' . ltrim(MYSQL_INDEX_LOCK) . '|ALGORITHM=SPELLING))/Du';
    $match = preg_match(str_replace('%', $quote, $pattern), $sql);
    if ($match === false) {
        throw new RuntimeException('ALTER TABLE index admission failed: ' . preg_last_error_msg());
    }

    return $match === 1;
}

runMysqlIndexObserver($argv, initDropIndex(...),
    static fn (PDO $catalog, PDO $owner, array $request): array => observeDropIndex($catalog, $owner, $request, admitMysqlAlterIndex(...)));
