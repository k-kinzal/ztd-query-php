<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * An aggregate named with its argument list, as `aggregate_with_argtypes` writes it.
 *
 * Mirrors PostgreSQL's `ObjectWithArgs` for aggregates.
 * Source: https://www.postgresql.org/docs/17/sql-dropaggregate.html.
 *
 * @visibility public
 * @example Reading the aggregate name
 *     $signature = new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateSignature(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('total')]), new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateArguments([]));
 *     [$signature->name->last()->value, $signature->arguments->star()] // => ['total', true]
 */
final class AggregateSignature implements ObjectReference
{
    use Snapshot;

    /**
     * @param DottedName $name The aggregate name
     * @param AggregateArguments $arguments The argument list
     */
    public function __construct(public readonly DottedName $name, public readonly AggregateArguments $arguments)
    {
    }

    /**
     * Derives the argument list.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->arguments->deriveClause($derivation, $environment);
    }

    /**
     * Writes the name and the argument list.
     */
    public function render(Output $out): void
    {
        $out->node($this->name)->node($this->arguments);
    }
}
