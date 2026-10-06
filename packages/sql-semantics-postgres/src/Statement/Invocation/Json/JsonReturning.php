<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `RETURNING type [FORMAT JSON ...]`: the type an SQL/JSON function returns.
 *
 * Mirrors PostgreSQL's `JsonOutput` node (PostgreSQL 16 spells the grammar
 * rule `json_output_clause_opt`, 17 `json_returning_clause_opt`).
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE.
 *
 * @visibility public
 * @example Reading a returning clause
 *     $returning = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonReturning(new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('text')]))));
 *     $returning->format // => null
 */
final class JsonReturning implements Clause
{
    use Snapshot;

    /**
     * @param TypeName $type The type returned
     * @param JsonFormat|null $format The format written
     */
    public function __construct(public readonly TypeName $type, public readonly ?JsonFormat $format = null)
    {
    }

    /**
     * Derives the modifiers of the type.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->type->deriveClause($derivation, $environment);
    }

    /**
     * Writes RETURNING, the type and the format.
     */
    public function render(Output $out): void
    {
        $out->keyword('RETURNING')->node($this->type)->node($this->format);
    }
}
