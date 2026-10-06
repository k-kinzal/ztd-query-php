<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * TABLESAMPLE method (arguments) [REPEATABLE (seed)]: a sample of the rows of a table.
 *
 * Mirrors PostgreSQL's `RangeTableSample`. The arguments and the seed are
 * evaluated once and see no column of the sampled table.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM.
 *
 * @visibility public
 * @example Reading a table sample
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 FROM t TABLESAMPLE bernoulli (10) REPEATABLE (1)');
 *     [$query->statement->from->sample->method->parts[0]->value, $query->statement->from->sample->seed !== null] // => ['bernoulli', true]
 */
final class TableSample implements Clause
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar> The arguments of the sampling method
     */
    public readonly array $arguments;

    /**
     * @param DottedName $method The sampling method
     * @param list<Scalar> $arguments The arguments of the method; at least one
     * @param Scalar|null $seed The seed written after REPEATABLE
     */
    public function __construct(public readonly DottedName $method, array $arguments, public readonly ?Scalar $seed = null)
    {
        $this->arguments = Check::listOf($arguments, Scalar::class, 'A sampling method takes at least one argument.', 1);
    }

    /**
     * Derives the arguments and the seed.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ([...$this->arguments, ...($this->seed === null ? [] : [$this->seed])] as $expression) {
            $derivation->scalar($expression, $environment);
        }
    }

    /**
     * Writes TABLESAMPLE, the method, its arguments and the seed.
     */
    public function render(Output $out): void
    {
        $out->keyword('TABLESAMPLE')->node($this->method)->symbol('(')->list($this->arguments)->symbol(')');
        if ($this->seed !== null) {
            $out->keyword('REPEATABLE')->symbol('(')->node($this->seed)->symbol(')');
        }
    }
}
