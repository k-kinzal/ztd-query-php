<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ColumnDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A constant of a stated type, written `type 'string'`.
 *
 * `DATE '2024-01-01'`, `INTERVAL '1' DAY`, `N'text'` and `float8 '1.5'` are
 * typed constants: the string is converted by the input routine of the type.
 * The raw parser reads the form as a cast of the string to the type.
 *
 * Rule: PG-TYPED-CONSTANT-001. Facts: the type the type name denotes, where
 * `bit` and `character` without a length are unconstrained; never NULL; an
 * unaliased result column is named after the catalog name of the type.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-CONSTANTS-GENERIC. Status: Implemented.
 *
 * @visibility public
 * @example Reading a typed constant
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT date '2024-01-01'", []);
 *     [$query->statement->targets[0]->expression->value->value, $query->field(0)->type->descriptor->name(), $query->field(0)->name->value] // => ['2024-01-01', 'date', 'date']
 * @example Rejecting a set-returning type
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Literal\TypedLiteral(
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalDesignation(), true),
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('1 day'),
 *     ) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class TypedLiteral implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param TypeName $type The type of the constant: a designation without SETOF, array part or column reference
     * @param StringConstant $value The string the type's input routine reads
     */
    public function __construct(public readonly TypeName $type, public readonly StringConstant $value)
    {
        Check::input(!$type->setOf && $type->array === null && !$type->designation instanceof ColumnDesignation, 'A typed constant names a plain type.');
    }

    /**
     * Names an unaliased result column after the catalog name of the type.
     */
    public function outputName(): Name
    {
        return $this->type->designation->catalogName();
    }

    /**
     * Derives the modifier expressions and the type of the constant.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $this->type->deriveClause($derivation, $environment);

        $type = $this->type->typeFact($derivation->context, true);
        if ($type instanceof Invalid) {
            $derivation->report($type->cause);
        }

        return new ScalarFact($type, Nullability::NotNull);
    }

    /**
     * Writes the type and the string; an interval writes its fields after the string.
     */
    public function render(Output $out): void
    {
        if ($this->type->designation instanceof IntervalDesignation) {
            $this->type->designation->constant($out, $this->value);

            return;
        }
        $out->node($this->type)->node($this->value);
    }
}
