<?php

declare(strict_types=1);

require_once __DIR__ . '/server_predicate_scope.php';

/**
 * Admits only the registered ordinary writes against the two disposable fixture tables.
 */
function mysqlVarcharEffectAdmitted(string $sql): bool
{
    $quote = chr(96);
    $name = static fn (string $part): string => '(?:' . $quote . $part . $quote . '|' . $part . ')';
    $table = $name('ir_fixture') . '\\.' . $name('ir_varchar_(?:parent|secondary)');
    $alias = $name('(?:__ztd_source|r)');
    $qualifier = $name('(?:__ztd_source|r|ir_varchar_parent|ir_varchar_secondary)');
    $signedSlot = '(?:' . $qualifier . '\\.)?' . $name('id');
    $column = '(?:' . $qualifier . '\\.)?' . $name('(?:id|label|alternate|empty_value)');
    $signed = '(?:' . $signedSlot . '|NULL|-?[0-9]{1,10})';
    $text = <<<'PATTERN'
'(?:\\[\s\S]|''|[^'\\])*'
PATTERN;
    $operand = '(?:' . $column . '|' . $text . '|NULL|-?[0-9]{1,10})';
    $where = '(?: WHERE (?&truth))?';
    $update = 'UPDATE ' . $table . '(?: AS ' . $alias . ')? SET ' . $signedSlot . '\\h*=\\h*' . $signed . $where;
    $delete = 'DELETE FROM ' . $table . $where;

    return preg_match('/\\A(?:' . $update . '|' . $delete . ')\\z'
        . fixturePredicateDefinition($operand, '(?:<=>|<=|<>|!=|>=|=|<|>)') . '/iD', $sql) === 1;
}

/**
 * Measures exact physical and triggered effects before rolling the fixture back.
 */
function mysqlVarcharEffect(PDO $pdo, string $sql): array
{
    if (!mysqlVarcharEffectAdmitted($sql) || $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
        return ['status' => 'outside-observer-scope'];
    }
    $before = mysqlVarcharSnapshot($pdo);
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute();
        $response = ['status' => 'mutation', 'count' => $statement->rowCount(), 'columns' => $statement->columnCount()];
        $statement->closeCursor();
        $response['after'] = mysqlVarcharSnapshot($pdo);
    } catch (PDOException $error) {
        $response = ['status' => 'db-error', 'state' => $error->errorInfo[0]];
    } finally {
        $pdo->rollBack();
    }

    return [...$response, 'before' => $before, 'restored' => mysqlVarcharSnapshot($pdo)];
}

function mysqlVarcharSnapshot(PDO $pdo): array
{
    return [
        'parent' => $pdo->query('SELECT label, id, alternate, empty_value FROM ir_fixture.ir_varchar_parent')->fetchAll(PDO::FETCH_NUM),
        'control' => $pdo->query('SELECT label, id, alternate, empty_value FROM ir_fixture.ir_varchar_secondary')->fetchAll(PDO::FETCH_NUM),
        'events' => $pdo->query('SELECT relation_name, operation_name, old_label, old_id, new_id FROM ir_fixture.ir_varchar_events')->fetchAll(PDO::FETCH_NUM),
    ];
}
