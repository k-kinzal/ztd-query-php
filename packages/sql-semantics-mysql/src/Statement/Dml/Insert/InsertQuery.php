<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Query;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Dml\InsertFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;

/**
 * INSERT or REPLACE of the rows of a query (SELECT, TABLE, VALUES or a set operation), with the optional ON DUPLICATE KEY UPDATE.
 *
 * Rule: MYSQL-INSERT-QUERY-001. The facts follow MYSQL-INSERT-001 with the
 * query as the source.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/insert-select.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class InsertQuery implements Statement
{
    use Snapshot;

    /**
     * @var list<Assignment> The assignments of ON DUPLICATE KEY UPDATE in written order
     */
    public readonly array $onDuplicate;

    /**
     * @param InsertInto $into The head: verb, modifiers, table and column list
     * @param Query $source The query whose rows are written
     * @param list<Assignment> $onDuplicate The assignments of ON DUPLICATE KEY UPDATE
     * @throws InvalidConstruction When REPLACE has ON DUPLICATE KEY UPDATE
     */
    public function __construct(public readonly InsertInto $into, public readonly Query $source, array $onDuplicate = [])
    {
        $this->onDuplicate = Check::listOf($onDuplicate, Assignment::class, 'ON DUPLICATE KEY UPDATE holds assignments.');
        Check::input(!$into->replace || $this->onDuplicate === [], 'REPLACE has no ON DUPLICATE KEY UPDATE.');
    }

    /**
     * Derives the written table, the columns, the query and the assignments; the statement returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new InsertFacts())->query($this, $derivation, $derivation->environment());
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->into)->node($this->source);
        if ($this->onDuplicate !== []) {
            $out->keyword('ON', 'DUPLICATE', 'KEY', 'UPDATE')->list($this->onDuplicate);
        }
    }
}
