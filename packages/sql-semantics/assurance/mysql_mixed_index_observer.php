<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_drop_indexes_observer.php';

function admitMixedNativeIndexes(string $sql): bool
{
    if (strlen($sql) > 40000) {
        return false;
    }
    $receiver = str_replace('%', chr(96), '%ir_fixture%.%ir_index_set%');
    $policy = ltrim(MYSQL_INDEX_ALGORITHM) . '|' . ltrim(MYSQL_INDEX_LOCK);
    $alteration = 'RENAME (?:INDEX|KEY) (?&operand) TO (?&operand)|DROP PRIMARY KEY|DROP (?:INDEX|KEY) (?&operand)';
    $pattern = '/\AALTER TABLE ' . $receiver . ' (?&action)(?:, (?&action)){1,65}\z'
        . '(?(DEFINE)(?<action>' . $alteration . '|' . $policy . ')'
        . '(?<operand>(?:(?&name)\.){0,2}(?&name))(?<name>' . DROP_INDEX_NAME . '))/Du';
    $match = preg_match($pattern, $sql);
    if ($match === false) {
        throw new RuntimeException('Mixed index alteration SQL admission failed: ' . preg_last_error_msg());
    }

    return $match === 1;
}

function observeMixedNativeIndexes(PDO $catalog, PDO $owner, array $request): array
{
    return observeNativeIndexChange(
        $catalog, $owner, $request, admitMixedNativeIndexes(...), 'mysql-mixed-index-alteration', resetMixedIndexStatistics(...)
    );
}

function resetMixedIndexStatistics(PDO $catalog): array
{
    $scope = " WHERE database_name = 'ir_fixture' AND table_name = 'ir_index_set'";
    $measured = [];
    foreach (['mysql.innodb_index_stats', 'mysql.innodb_table_stats'] as $statistics) {
        $catalog->exec('DELETE FROM ' . $statistics . $scope);
        $remaining = (int) $catalog->query('SELECT COUNT(*) FROM ' . $statistics . $scope)->fetchColumn();
        if ($remaining !== 0) {
            throw new RuntimeException('The disposable fixture retained orphaned InnoDB statistics.');
        }
        $measured[$statistics] = $remaining;
    }

    return $measured;
}

runMysqlIndexObserver($argv, initIndexRemovalSet(...), observeMixedNativeIndexes(...));
