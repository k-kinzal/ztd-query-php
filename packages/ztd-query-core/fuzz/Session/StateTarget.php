<?php

declare(strict_types=1);

namespace Fuzz\Session;

use Fuzz\Shared\Input\Bytes;
use Fuzz\Shared\Oracle\Comparison;
use Fuzz\Shared\Oracle\Finding;
use Fuzz\Shared\Oracle\Rejection;
use ZtdQuery\Connection\Exception\DatabaseException;

/**
 * Checks isolation, commit/rollback/savepoints and failed-statement atomicity after every action.
 *
 * Each four-byte block selects session plus operation, table, row identity,
 * and value plus savepoint name. Values are opaque to core, including arrays.
 * Both independent sessions are checked after every action; up to 128 actions
 * exercise order-dependent state without involving SQL parsing.
 */
final class StateTarget
{
    /**
     * Decode actions for two sessions and verify both after every operation.
     * @throws Finding
     */
    public function __invoke(string $input): void
    {
        $sessions = [new VirtualSession(), new VirtualSession()];
        $models = [new ReferenceState(), new ReferenceState()];
        foreach (str_split(($input === '' ? "\0" : substr($input, 0, 512)), 4) as $step => $chunk) {
            $bytes = new Bytes($chunk);
            $choice = $bytes->next();
            $session = $choice % 2;
            $operation = intdiv($choice, 2) % 13;
            $table = $bytes->next(2) === 0 ? 'items' : 'other';
            $id = $bytes->next(8) + 1;
            $valueChoice = $bytes->next();
            $value = [$valueChoice, (string) $valueChoice, null, '', "O'Brien", ['opaque' => $valueChoice]][$valueChoice % 6];
            $name = 'point_' . ($valueChoice % 3);
            $expected = $models[$session]->apply($operation, $table, $id, $value, $name);
            $failure = null;
            try {
                $sessions[$session]->apply($operation, $table, $id, $value, $name);
            } catch (DatabaseException $error) {
                $failure = $error;
            }
            if ($expected !== null) {
                Rejection::verify($failure, $expected);
            } elseif ($failure !== null) {
                throw new Finding('Valid core operation was rejected.', 0, $failure);
            }
            foreach ($sessions as $index => $actual) {
                self::verify($models[$index], $actual);
            }
        }
    }

    /**
     * Compare rows, schema and physical queries with the independent model.
     * @throws Finding
     */
    public static function verify(ReferenceState $model, VirtualSession $actual): void
    {
        $expected = $model->tables;
        foreach ($expected as &$rows) {
            $rows = array_values($rows);
        }
        unset($rows);
        Comparison::same($expected, $actual->store->getAll(), 'Virtual rows differ from the model.');
        Comparison::same(array_keys($expected), array_keys($actual->registry->getAll()), 'Virtual catalog differs from the model.');
        foreach (array_keys($expected) as $table) {
            Comparison::same(['id', 'value'], $actual->session->tableDefinition($table)?->columns, 'Rollback lost the column definitions.');
        }
        Comparison::same([], $actual->connection->queries, 'A core state operation touched the physical connection.');
    }
}
