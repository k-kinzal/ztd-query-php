<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ColumnDesignation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * A type as SQL writes it: a designation, an optional array part, and SETOF for a function result.
 *
 * Mirrors PostgreSQL's `TypeName` node. The type it denotes is not stored:
 * `typeFact()` derives it against a context, and whoever holds the type name
 * derives its modifier expressions through `deriveClause()`.
 *
 * Rule: PG-TYPE-NAME-001. Facts: the designation's type; with an array part
 * the array type over it; SETOF does not change the element type.
 * Source: https://www.postgresql.org/docs/17/datatype.html, https://www.postgresql.org/docs/17/arrays.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the type a type name denotes
 *     $type = new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation(\SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword::Integer),
 *         false,
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Type\ArraySpecifier([new \SqlSemantics\Platform\PostgreSql\Statement\Type\ArrayBound()]),
 *     );
 *     $analysis = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->context([]);
 *     $type->typeFact($analysis)->descriptor->name() // => 'integer[]'
 */
final class TypeName implements OptionArgument
{
    use Snapshot;

    /**
     * @param TypeDesignation $designation The element type
     * @param bool $setOf Whether SETOF is written
     * @param ArraySpecifier|null $array The array part
     */
    public function __construct(public readonly TypeDesignation $designation, public readonly bool $setOf = false, public readonly ?ArraySpecifier $array = null)
    {
        Check::input($array === null || !$designation instanceof ColumnDesignation, 'A column type reference takes no array part.');
    }

    /**
     * Answers the type the name denotes in a context.
     *
     * @param bool $constant Whether the name types a constant
     */
    public function typeFact(AnalysisContext $context, bool $constant = false): TypeFact
    {
        $fact = $this->designation->typeFact($context, $constant);

        return $this->array !== null && $fact instanceof Known ? new Known(new ArrayOf($fact->descriptor)) : $fact;
    }

    /**
     * Derives the modifier expressions of the designation.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->designation->deriveClause($derivation, $environment);
    }

    /**
     * Writes SETOF, the designation and the array part.
     */
    public function render(Output $out): void
    {
        if ($this->setOf) {
            $out->keyword('SETOF');
        }
        $out->node($this->designation)->node($this->array);
    }
}
