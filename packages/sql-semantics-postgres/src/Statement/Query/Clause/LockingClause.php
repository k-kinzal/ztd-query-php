<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Clause;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One locking clause: FOR UPDATE, FOR NO KEY UPDATE, FOR SHARE or FOR KEY SHARE, optionally limited to some FROM items.
 *
 * Mirrors PostgreSQL's `LockingClause`. The names refer to FROM items of the
 * query by their alias or table name; without names every table of the query
 * is locked.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-FOR-UPDATE-SHARE.
 *
 * @visibility public
 * @example Reading a locking clause
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 FROM t FOR SHARE OF t SKIP LOCKED');
 *     [$query->statement->options->locking[0]->strength->value, $query->statement->options->locking[0]->relations[0]->name->value, $query->statement->options->locking[0]->wait->value] // => ['SHARE', 't', 'SKIP LOCKED']
 */
final class LockingClause implements Node
{
    use Snapshot;

    /**
     * @var list<QualifiedName> The FROM items locked; empty for every table
     */
    public readonly array $relations;

    /**
     * @param LockStrength $strength The lock taken
     * @param list<QualifiedName> $relations The FROM items locked; empty for every table
     * @param LockWait|null $wait What to do with rows locked by another transaction; null to wait
     */
    public function __construct(public readonly LockStrength $strength, array $relations = [], public readonly ?LockWait $wait = null)
    {
        $this->relations = Check::listOf($relations, QualifiedName::class, 'A locking clause names relations.');
    }

    /**
     * Writes FOR, the strength, the relations after OF and the wait policy.
     */
    public function render(Output $out): void
    {
        $out->keyword('FOR', ...explode(' ', $this->strength->value));
        foreach ($this->relations as $position => $relation) {
            if ($position === 0) {
                $out->keyword('OF');
            } else {
                $out->symbol(',');
            }
            (new Spelling())->qualified($out, $relation);
        }
        if ($this->wait !== null) {
            $out->keyword(...explode(' ', $this->wait->value));
        }
    }
}
