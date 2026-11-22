<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * A row value built from fields: `ROW(a, b)` or `(a, b)`.
 *
 * Mirrors PostgreSQL's `RowExpr` node. A field written `t.*` expands to the
 * columns of the relation.
 *
 * Rule: PG-ROW-001. Facts: an anonymous record whose fields `f1`, `f2`, …
 * have the types and NULL facts of the written fields, the row itself never
 * NULL; an unaliased result column is named `row`. The implicit spelling
 * needs at least two fields: one field in parentheses is a grouping.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-ROW-CONSTRUCTORS. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Rejecting an implicit row of one field
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowConstructor([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()], \SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowSpelling::Implicit) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class RowConstructor implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var list<Scalar> The fields in order
     */
    public readonly array $fields;

    /**
     * @param list<Scalar> $fields The fields in order
     * @param RowSpelling $spelling How the row is written
     */
    public function __construct(array $fields, public readonly RowSpelling $spelling = RowSpelling::Explicit)
    {
        $this->fields = Check::listOf($fields, Scalar::class, 'A row holds expressions.');
        Check::input($spelling === RowSpelling::Explicit || count($this->fields) >= 2, 'A row without ROW has at least two fields.');
    }

    /**
     * Names an unaliased result column `row`.
     */
    public function outputName(): Name
    {
        return new Name('row');
    }

    /**
     * Derives the fields and the record they make.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $slots = [];
        foreach ($this->fields as $position => $field) {
            $fact = $derivation->scalar($field, $environment);
            $type = $fact->type instanceof Known && $fact->type->descriptor === Builtin::Unknown ? new Known(Builtin::Text) : $fact->type;
            $slots[] = new OutputSlot(new Name('f' . ($position + 1)), $type, $fact->nullability);
        }

        return new ScalarFact(new Known(new Composite($slots)), Nullability::NotNull);
    }

    /**
     * Writes the fields in parentheses, after ROW when written so.
     */
    public function render(Output $out): void
    {
        if ($this->spelling === RowSpelling::Explicit) {
            $out->keyword('ROW');
        }
        $out->symbol('(')->list($this->fields)->symbol(')');
    }
}
