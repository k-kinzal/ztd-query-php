<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_index_observation.php';

const FK_TRUNCATE_SCHEMAS = ['ir_fixture', 'ir_fk_decoy'];
const FK_TRUNCATE_MARKER = 'ir_fixture.ir_fk_truncate_marker';

function initTruncateReferences(PDO $catalog): void
{
    $catalog->exec('CREATE DATABASE IF NOT EXISTS ir_fk_decoy');
    $catalog->exec('DROP TABLE IF EXISTS ' . FK_TRUNCATE_MARKER);
    $catalog->exec('CREATE TABLE ' . FK_TRUNCATE_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . FK_TRUNCATE_MARKER . ' VALUES (0)');
}

function truncateReferenceDdl(string $sql): bool
{
    $pattern = '/\ACREATE (?:TEMPORARY )?TABLE (?:\x60(?:ir_fixture|ir_fk_decoy)\x60\.\x60ir_truncate_(?:parent|child|copy)\x60) '
        . '(?:\([A-Za-z0-9_,. ()\x60]+\) ENGINE=(?:InnoDB|MyISAM)|LIKE \x60ir_fk_decoy\x60\.\x60ir_truncate_child\x60)\z/D';
    return strlen($sql) <= 480 && preg_match($pattern, $sql) === 1;
}

function truncateReferenceRequest(array $request): bool
{
    if (!is_int($request['offset']) || $request['offset'] < 0 || $request['offset'] > 80
        || !is_bool($request['occupied']) || !is_bool($request['temporary'])
        || !in_array($request['schema'], FK_TRUNCATE_SCHEMAS, true)
        || !in_array($request['form'], ['none', 'same', 'cross-explicit', 'cross-local', 'self', 'inline', 'ignored-engine', 'shadow-child', 'like'], true)
        || !is_array($request['declarations']) || count($request['declarations']) < 2 || count($request['declarations']) > 5) {
        return false;
    }
    if (count(array_filter($request['declarations'], truncateReferenceDdl(...))) !== count($request['declarations'])) {
        return false;
    }

    return preg_match('/\ATRUNCATE (?:TABLE )?(?:\x60(?:ir_fixture|ir_fk_decoy)\x60\.)?\x60ir_truncate_parent\x60\z/D', $request['sql']) === 1;
}

function truncateReferenceChild(string $form): string
{
    $schema = in_array($form, ['cross-explicit', 'cross-local', 'like'], true) ? 'ir_fk_decoy' : 'ir_fixture';

    return $schema . '.ir_truncate_child';
}

function prepareTruncateReferences(PDO $catalog, PDO $owner, array $request): void
{
    foreach (FK_TRUNCATE_SCHEMAS as $schema) {
        foreach (['ir_truncate_child', 'ir_truncate_parent'] as $label) {
            $owner->exec('DROP TEMPORARY TABLE IF EXISTS ' . $schema . '.' . $label);
        }
    }
    foreach (['ir_truncate_copy', 'ir_truncate_child', 'ir_truncate_parent'] as $label) {
        foreach (FK_TRUNCATE_SCHEMAS as $schema) {
            $catalog->exec('DROP TABLE IF EXISTS ' . $schema . '.' . $label);
        }
    }
    $catalog->exec('UPDATE ' . FK_TRUNCATE_MARKER . ' SET n=0');
    foreach ($request['declarations'] as $sql) {
        (str_starts_with($sql, 'CREATE TEMPORARY') ? $owner : $catalog)->exec($sql);
    }
    $offset = $request['offset'];
    $form = $request['form'];
    $primary = 101 + $offset;
    $other = 201 + $offset;
    $insert = $catalog->prepare('INSERT INTO ir_fixture.ir_truncate_parent (id,pid) VALUES (?,?)');
    $insert->execute([$primary, null]);
    $insert->execute([$primary + 1, $form === 'self' ? $primary : null]);
    $insert = $catalog->prepare('INSERT INTO ir_fk_decoy.ir_truncate_parent (id,pid) VALUES (?,NULL),(?,NULL)');
    $insert->execute([$other, $other + 1]);
    if ($request['occupied'] && $form !== 'none' && $form !== 'self') {
        $child = truncateReferenceChild($form);
        $insert = $catalog->prepare('INSERT INTO ' . $child . ' (id,pid) VALUES (?,?)');
        $insert->execute([301 + $offset, $form === 'cross-local' ? $other : $primary]);
    }
    if ($request['temporary']) {
        $selected = $request['schema'] . '.ir_truncate_parent';
        $insert = $owner->prepare('INSERT INTO ' . $selected . ' (id,pid) VALUES (?,NULL),(?,NULL)');
        $insert->execute([401 + $offset, 402 + $offset]);
    }
}

function readTruncateReferenceRows(PDO $connection, string $relation): ?array
{
    if (mysqlFixtureTableDefinition($connection, $relation) === null) {
        return null;
    }

    return $connection->query('SELECT id,pid FROM ' . $relation . ' ORDER BY id')->fetchAll(PDO::FETCH_NUM);
}

function snapshotTruncateReferences(PDO $catalog, PDO $owner, array $request): array
{
    $referrers = $catalog->query("SELECT TABLE_SCHEMA,TABLE_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME "
        . "FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA IN ('ir_fixture','ir_fk_decoy') "
        . "AND TABLE_NAME IN ('ir_truncate_parent','ir_truncate_child','ir_truncate_copy') AND REFERENCED_TABLE_NAME IS NOT NULL "
        . "ORDER BY TABLE_SCHEMA,TABLE_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_NUM);
    return [
        'first' => readTruncateReferenceRows($catalog, 'ir_fixture.ir_truncate_parent'),
        'other' => readTruncateReferenceRows($catalog, 'ir_fk_decoy.ir_truncate_parent'),
        'child' => readTruncateReferenceRows($catalog, truncateReferenceChild($request['form'])),
        'copy' => readTruncateReferenceRows($catalog, 'ir_fixture.ir_truncate_copy'),
        'shadow' => $request['temporary'] ? readTruncateReferenceRows($owner, $request['schema'] . '.ir_truncate_parent') : null,
        'referrers' => $referrers,
    ];
}

function observeTruncateReferences(PDO $catalog, PDO $owner, array $request): array
{
    if (!truncateReferenceRequest($request)) {
        return ['status' => 'outside-observer-scope'];
    }
    prepareTruncateReferences($catalog, $owner, $request);
    $settings = $owner->query('SELECT @@foreign_key_checks, @@autocommit, @@auto_increment_increment, @@auto_increment_offset')->fetch(PDO::FETCH_NUM);
    $before = snapshotTruncateReferences($catalog, $owner, $request);
    $effect = observeMysqlIndexEffect($catalog, $owner, FK_TRUNCATE_MARKER, $request['sql']);
    $after = snapshotTruncateReferences($catalog, $owner, $request);
    $owner->exec('INSERT INTO ' . $request['schema'] . '.ir_truncate_parent (pid) VALUES (NULL)');

    return ['status' => 'mysql-truncate-foreign-keys', 'before' => $before, 'after' => $after,
        ...$effect, 'settings' => $settings, 'next' => (int) $owner->lastInsertId()];
}

runMysqlIndexObserver($argv, initTruncateReferences(...), observeTruncateReferences(...));
