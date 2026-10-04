<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\CopyFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Sources;
use SqlSemantics\Platform\PostgreSql\Rules\Query\ClosedList;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Modification;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * COPY of the rows of a query to a file, a program or the client.
 *
 * Mirrors PostgreSQL's `CopyStmt` with a `query`. The query is a query
 * statement or a data-modifying statement with RETURNING. The facts follow
 * PG-COPY-001.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a COPY of a query
 *     $copy = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("COPY (SELECT 1) TO '/tmp/x' CSV");
 *     [$copy->statement->query instanceof \SqlSemantics\Platform\PostgreSql\Statement\Query\Select, $copy->toString()] // => [true, "COPY (SELECT 1) TO '/tmp/x' CSV"]
 */
final class CopyQuery implements Statement
{
    use Snapshot;

    /**
     * @var list<CopyFlag|CopyText|CopyForce> The options of the old syntax in written order
     */
    public readonly array $legacy;

    /**
     * @var list<CopyOption> The parenthesized options in written order
     */
    public readonly array $options;

    /**
     * @param Query $query The query: a query statement or a data-modifying statement
     * @param bool $program Whether the file is a program
     * @param StringConstant|CopyStream $file The file name or command, or the client
     * @param list<CopyFlag|CopyText|CopyForce> $legacy The options of the old syntax
     * @param list<CopyOption> $options The parenthesized options
     *
     * @throws InvalidConstruction When the query is no statement, both kinds of options are written, or a list holds a foreign item
     */
    public function __construct(public readonly Query $query, public readonly bool $program, public readonly StringConstant|CopyStream $file, array $legacy = [], array $options = [])
    {
        Check::input((new Sources())->statement($query) || $query instanceof Modification, 'COPY copies a query statement or a data-modifying statement.');
        $this->legacy = (new ClosedList())->of($legacy, [CopyFlag::class, CopyText::class, CopyForce::class], 'The old COPY options are keyword, string and FORCE options.');
        $this->options = Check::listOf($options, CopyOption::class, 'The parenthesized COPY options are options.');
        Check::input($this->legacy === [] || $this->options === [], 'COPY takes the options of the old syntax or parenthesized options, not both.');
    }

    /**
     * Derives the query and the options.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new CopyFacts())->query($this, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('COPY')->symbol('(')->node($this->query)->symbol(')')->keyword('TO');
        if ($this->program) {
            $out->keyword('PROGRAM');
        }
        $out->node($this->file);
        foreach ($this->legacy as $item) {
            $out->node($item);
        }
        if ($this->options !== []) {
            $out->symbol('(')->list($this->options)->symbol(')');
        }
    }
}
