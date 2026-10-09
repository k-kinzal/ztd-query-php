<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * Refuses an index hint that names an index the table does not have (ER_KEY_DOES_NOT_EXITS).
 *
 * The server checks the USE, FORCE and IGNORE INDEX hints of the tables of a query block when
 * it starts to resolve the block, before its derived tables and the names of its clauses; those
 * of the tables an UPDATE or a DELETE names outside its query blocks come before any name. Index names compare without regard to case; PRIMARY names the primary key. A view has
 * no index, and the error names the table by its alias when it has one. A reference to a common
 * table expression takes any hint (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0
 * servers). The tables of the system databases are not checked.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/index-hints.html.
 *
 * @visibility MySqlMemory
 */
final class IndexHints
{
    /**
     * Raises the error of the first index hint of a table an UPDATE or a DELETE writes or reads outside its query blocks, which the server checks before it resolves any name; the tables of a query block are checked where the block is resolved (see Locator).
     *
     * @param list<Node> $reached The nodes of the statement the server reads, in written order
     * @throws SqlError When a hint names such an index
     */
    public function check(Node $statement, array $reached, Session $session): void
    {
        $blocks = [];
        foreach ((new Walker())->find($statement, Select::class) as $select) {
            foreach ($select->from === null ? [] : (new Walker())->find($select->from, TableReference::class, false) as $reference) {
                $blocks[spl_object_id($reference)] = true;
            }
        }
        $common = $this->common($statement);
        foreach ($reached as $node) {
            $refusal = $node instanceof TableReference && !isset($blocks[spl_object_id($node)]) ? $this->refusal($node, $common, $session) : null;
            if ($refusal !== null) {
                throw $refusal;
            }
        }
    }

    /**
     * Answers the names of the common table expressions of a statement.
     *
     * @return list<string>
     */
    public function common(Node $statement): array
    {
        return array_map(static fn (CommonTableExpression $table): string => $table->name->value, (new Walker())->find($statement, CommonTableExpression::class));
    }

    /**
     * Answers the error of the first index a hint of a table reference names that the table does not have, or null.
     *
     * @param list<string> $common The names of the common table expressions of the statement
     */
    public function refusal(TableReference $reference, array $common, Session $session): ?SqlError
    {
        if ($reference->indexHints === [] || ($reference->name->schema === null && in_array($reference->name->name->value, $common, true))) {
            return null;
        }
        $keys = $this->keys($reference, $session);
        foreach ($keys === null ? [] : $reference->indexHints as $hint) {
            foreach ($hint->indexes as $index) {
                $named = $index instanceof Name ? $index->value : 'PRIMARY';
                if (!in_array(strtolower($named), (array) $keys, true)) {
                    return SchemaError::KeyMissing->error($named, ($reference->alias ?? $reference->name->name)->value);
                }
            }
        }

        return null;
    }

    /**
     * Answers the lower-case names of the indexes of the table a reference names, an empty list for a view, or null when the table is not one the emulator checks.
     *
     * @return list<string>|null
     */
    public function keys(TableReference $reference, Session $session): ?array
    {
        $database = $reference->name->schema->value ?? $session->variables->database;
        $schema = $session->instance->dictionary->schema($database);
        if ($schema === null) {
            return null;
        }
        $table = $session->instance->dictionary->table($database, $reference->name->name->value);
        if ($table !== null) {
            return array_map(static fn ($key): string => strtolower($key->name), $table->definition->keys);
        }

        return isset($schema->views[$reference->name->name->value]) ? [] : null;
    }
}
