<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\NullOnly;
use SqlSemantics\Platform\PostgreSql\Rules\Query\StarExpansion;
use SqlSemantics\Platform\PostgreSql\Rules\Query\TargetNames;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnStar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Indirection;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\AllFields;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * A select-list item that is an expression with an optional output name.
 *
 * Mirrors PostgreSQL's `ResTarget` with a value. Rule: PG-TARGET-001. The
 * item contributes one field, named by its alias, else by PG-TARGET-NAME-001.
 * Its type and NULL fact are those of the expression, except that an
 * untyped string constant or NULL has type `text` once the query resolves
 * its output. A `t.*` or `(value).*` without an alias expands into the
 * fields it selects (PG-STAR-001); with an alias it is one composite value.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-SELECT-LIST,
 * https://www.postgresql.org/docs/17/typeconv-select.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ExpressionTarget implements Target
{
    use Snapshot;

    /**
     * @param Scalar $expression The projected expression
     * @param Name|null $alias The output name written for it
     */
    public function __construct(public readonly Scalar $expression, public readonly ?Name $alias = null)
    {
    }

    /**
     * Answers the name of the field: the alias, else the name the expression gives.
     */
    public function name(): Name
    {
        return $this->alias ?? (new TargetNames())->name($this->expression);
    }

    /**
     * Derives the expression and answers its field, or the fields its star selects.
     */
    public function project(Derivation $derivation, Environment $environment, int $position): array
    {
        $fact = $derivation->scalar($this->expression, $environment);
        if ($this->alias === null && $this->expression instanceof ColumnStar) {
            return (new StarExpansion())->qualified($environment, $this->expression->qualifiers, $position);
        }
        if ($this->alias === null && $this->expression instanceof Indirection && $this->expression->steps[count($this->expression->steps) - 1] instanceof AllFields) {
            return (new StarExpansion())->composite($derivation, $fact);
        }
        $type = $fact->type instanceof NullOnly || ($fact->type instanceof Known && $fact->type->descriptor === Builtin::Unknown) ? new Known(Builtin::Text) : $fact->type;
        $origin = $fact->resolution instanceof ResolvedColumn ? $fact->resolution->slot : null;

        return [new Field($position, new OutputSlot($this->name(), $type, $fact->nullability, null, $origin), $this->expression, $fact->resolution)];
    }

    /**
     * Writes the expression and the output name after AS.
     */
    public function render(Output $out): void
    {
        $out->node($this->expression);
        if ($this->alias !== null) {
            $out->keyword('AS')->name($this->alias, NameUse::Label);
        }
    }
}
