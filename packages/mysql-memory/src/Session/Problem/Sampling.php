<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Settings;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\StatementSemantics;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Refuses the TABLESAMPLE clause of a table reference, which only a secondary engine runs.
 *
 * Once the server has opened the tables of a statement, a resolved sampling condition of an UPDATE or DELETE
 * fails as not supported (ER_NOT_SUPPORTED_YET style 6033). Any other statement fails
 * first when it samples a table that is not a base table (a common table expression, a view or a
 * table of INFORMATION_SCHEMA), then when a literal percentage lies outside 0 to 100; both come
 * before column conditions are reported. A statement that passes every check fails when it is optimized,
 * because no secondary engine is defined (ER_SECONDARY_ENGINE). MySQL 9.1 names the first table of
 * the statement in that message when it is a base table (verified on live 8.4.7 and 9.1.0 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility MySqlMemory
 */
final class Sampling
{
    /**
     * Raises the error of a sampled table the server refuses once the tables are open.
     *
     * @throws SqlError When the statement samples a table it cannot
     */
    public function opened(Node $statement, StatementSemantics $facts, Settings $settings, Dictionary $dictionary): void
    {
        $this->write($facts);
        $sampled = array_values(array_filter((new Walker())->resolved($statement, $facts), static fn (Node $reference): bool => $reference instanceof TableReference && $reference->sample !== null));
        if ($sampled === []) {
            return;
        }
        foreach ($sampled as $reference) {
            if (!$this->base($reference, $facts, $settings, $dictionary)) {
                throw StatementError::SampleOnNonBaseTable->error();
            }
        }
        foreach ($sampled as $reference) {
            $percentage = $reference->sample?->percentage;
            if ($percentage instanceof NumberLiteral && ((float) $percentage->text > 100.0 || (float) $percentage->text < 0.0)) {
                throw StatementError::SamplePercentageOutOfRange->error();
            }
        }
    }

    /**
     * Consumes the sampling role resolved by Semantics after dependencies open.
     *
     * @throws SqlError When the retained write has a reached sampling condition
     */
    public function write(StatementSemantics $facts): void
    {
        foreach ($facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof WriteMisuse && $diagnostic->rule === WriteRule::SampledWrite) {
                throw (new Errors())->write($diagnostic, null);
            }
        }
    }

    /**
     * Raises the error the server reports when it optimizes a statement that samples a table: no secondary engine runs it.
     *
     * @throws SqlError When the statement samples a table
     */
    public function optimized(Node $statement, StatementSemantics $facts, Settings $settings, Dictionary $dictionary): void
    {
        foreach ((new Walker())->resolved($statement, $facts) as $reference) {
            if ($reference instanceof TableReference && $reference->sample !== null) {
                throw $this->unengined($statement, $facts, $settings, $dictionary);
            }
        }
    }

    /**
     * Answers the error of a statement no secondary engine is defined for; MySQL 9.1 names the first table of the statement when it is a base table.
     */
    public function unengined(Node $statement, StatementSemantics $facts, Settings $settings, Dictionary $dictionary): SqlError
    {
        if ($settings->release() !== GrammarRelease::MySql910) {
            return SchemaError::SecondaryEngineFailed->error('No secondary engine defined for at least one of the query tables');
        }
        $named = '';
        foreach ((new Walker())->find($statement, Select::class) as $select) {
            if ($select->from === null) {
                continue;
            }
            $first = $select->from;
            while ($first instanceof TableList || $first instanceof JoinedTable || $first instanceof NestedRelation || $first instanceof EscapedRelation || $first instanceof OdbcJoin) {
                $first = match (true) {
                    $first instanceof TableList => $first->members[0],
                    $first instanceof JoinedTable => $first->left,
                    default => $first->relation,
                };
            }
            if ($first instanceof TableReference && $this->base($first, $facts, $settings, $dictionary)) {
                $resolution = $first->relationInformation()->table;
                $schema = $resolution instanceof DeclaredTable ? ($resolution->table->name->schema->value ?? $settings->database) : $settings->database;
                $named = ' [' . $schema . '.' . $first->name->name->value . ']';
            }
            break;
        }

        return SchemaError::SecondaryEngineFailed->error('You have not defined the secondary engine for at least one of the query tables' . $named . '.');
    }

    /**
     * Tells whether a table reference names a base table: not a common table expression, a view or a table of INFORMATION_SCHEMA.
     */
    public function base(TableReference $reference, StatementSemantics $facts, Settings $settings, Dictionary $dictionary): bool
    {
        $resolution = $reference->relationInformation()->table;
        if (!$resolution instanceof DeclaredTable) {
            return false;
        }
        $name = $resolution->table->name;
        $schema = $name->schema->value ?? $settings->database;
        if (strtolower($schema) === 'information_schema') {
            return false;
        }
        $view = $dictionary->schema($schema)?->views[$name->name->value] ?? null;

        return !($view !== null && $resolution->table->kind === \SqlSemantics\Statement\Declaration\RelationKind::View);
    }
}
