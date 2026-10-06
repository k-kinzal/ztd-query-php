<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to define a cast between two types.
 *
 * Rule: PG-CAST-001. Mirrors `CreateCastStmt`: source and target type, the
 * function (or binary coercion, or I/O conversion) and the `CoercionContext`;
 * no context means the cast is explicit only. The types and the function are
 * looked up when the cast is created, so the statement records no facts
 * beyond the type names. Source: https://www.postgresql.org/docs/17/sql-createcast.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a binary-coercible implicit cast
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE CAST (int4 AS myint) WITHOUT FUNCTION AS IMPLICIT');
 *     [$operation->statement->method, $operation->statement->context] // => [\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\CastConversion::Binary, \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\CastContext::Implicit]
 */
final class CreateCast implements Statement
{
    use Snapshot;

    /**
     * @param TypeName $source The source type
     * @param TypeName $target The target type
     * @param ObjectReference|CastConversion $method The function, or how the cast converts without one
     * @param CastContext|null $context Where the cast applies unwritten; null for explicit casts only
     */
    public function __construct(public readonly TypeName $source, public readonly TypeName $target, public readonly ObjectReference|CastConversion $method, public readonly ?CastContext $context = null)
    {
    }

    /**
     * Derives the type names and the function signature.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ClauseFacts())->derive($derivation, [$this->source, $this->target, $this->method]);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'CAST')->symbol('(')->node($this->source)->keyword('AS')->node($this->target)->symbol(')');
        if ($this->method instanceof CastConversion) {
            $out->node($this->method);
        } else {
            $out->keyword('WITH', 'FUNCTION')->node($this->method);
        }
        if ($this->context !== null) {
            $out->keyword('AS', $this->context->value);
        }
    }
}
