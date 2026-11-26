<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_drop_indexes_observer.php';

function admitNativeIndexRenames(string $sql): bool
{
    if (strlen($sql) > 40000) {
        return false;
    }
    $receiver = str_replace('%', chr(96), '%ir_fixture%.%ir_index_set%');
    $policy = ltrim(MYSQL_INDEX_ALGORITHM) . '|' . ltrim(MYSQL_INDEX_LOCK);
    $pattern = '/\AALTER TABLE ' . $receiver . ' (?&action)(?:, (?&action)){0,65}\z'
        . '(?(DEFINE)(?<action>RENAME (?:INDEX|KEY) (?&operand) TO (?&operand)|' . $policy . ')'
        . '(?<operand>(?:(?&name)\.){0,2}(?&name))(?<name>' . DROP_INDEX_NAME . '))/Du';
    $match = preg_match($pattern, $sql);
    if ($match === false) {
        throw new RuntimeException('Native index rename SQL admission failed: ' . preg_last_error_msg());
    }

    return $match === 1;
}

function observeNativeIndexRenames(PDO $catalog, PDO $owner, array $request): array
{
    return observeNativeIndexChange($catalog, $owner, $request, admitNativeIndexRenames(...), 'mysql-index-renames');
}

runMysqlIndexObserver($argv, initIndexRemovalSet(...), observeNativeIndexRenames(...));
