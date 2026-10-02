<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithClause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The head of an INSERT statement: the verb, the conflict resolution, the written table and the column list.
 *
 * REPLACE is the short spelling of INSERT OR REPLACE; the model keeps which
 * one is written.
 * Source: https://sqlite.org/lang_insert.html, https://sqlite.org/lang_replace.html.
 *
 * @visibility public
 * @example Reading the head of an INSERT
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('INSERT OR IGNORE INTO t (a, b) VALUES (1, 2)');
 *     [$insert->statement->into->resolution, $insert->statement->into->target->name->name->value, count($insert->statement->into->columns)] // => [\SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution::Ignore, 't', 2]
 * @example Refusing a conflict resolution next to REPLACE
 *     new \SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertInto(new \SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))), [], true, \SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution::Ignore) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class InsertInto implements Node
{
    use Snapshot;

    /**
     * @var list<Name> The columns that receive values, in written order; none when the list is absent
     */
    public readonly array $columns;

    /**
     * @param MutationTarget $target The written table
     * @param list<Name> $columns The columns that receive values; none when the list is absent
     * @param bool $replace Whether the verb is REPLACE
     * @param ConflictResolution|null $resolution The algorithm written after INSERT OR
     * @param WithClause|null $with The WITH clause of the statement
     */
    public function __construct(
        public readonly MutationTarget $target,
        array $columns = [],
        public readonly bool $replace = false,
        public readonly ?ConflictResolution $resolution = null,
        public readonly ?WithClause $with = null,
    ) {
        $this->columns = Check::listOf($columns, Name::class, 'The column list of an INSERT holds column names.');
        Check::input(!$replace || $resolution === null, 'REPLACE takes no conflict resolution.');
        Check::input($target->index === null, 'The table of an INSERT takes no index choice.');
    }

    /**
     * Writes the head up to the column list.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->keyword($this->replace ? 'REPLACE' : 'INSERT');
        if ($this->resolution !== null) {
            $out->keyword('OR', $this->resolution->value);
        }
        $out->keyword('INTO')->node($this->target);
        if ($this->columns !== []) {
            $out->symbol('(');
            foreach ($this->columns as $position => $column) {
                if ($position > 0) {
                    $out->symbol(',');
                }
                $out->name($column, NameUse::Column);
            }
            $out->symbol(')');
        }
    }
}
