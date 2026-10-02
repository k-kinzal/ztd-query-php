<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;

/**
 * A select-list item that is an expression with an optional output name.
 *
 * Rule: PG-TARGET-001 (slice — the query family completes or replaces
 * this). The item contributes one field. Its name is the alias, else the name
 * the expression gives, else `?column?`. Its type and NULL fact are those of
 * the expression, except that a string constant left untyped is `text`.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-SELECT-LIST,
 * https://www.postgresql.org/docs/17/typeconv-select.html. Status: Specified.
 *
 * @visibility public
 * @example Reading the field of an unnamed constant
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT 'a'");
 *     [$query->field(0)->name->value, $query->field(0)->type->descriptor->name()] // => ['?column?', 'text']
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
     * Derives the expression and answers its one output field.
     */
    public function project(Derivation $derivation, Environment $environment, int $position): array
    {
        $fact = $derivation->scalar($this->expression, $environment);
        $name = $this->alias ?? ($this->expression instanceof OutputNaming ? $this->expression->outputName() : null) ?? new Name('?column?');
        $type = $fact->type instanceof Known && $fact->type->descriptor === Builtin::Unknown ? new Known(Builtin::Text) : $fact->type;
        $origin = $fact->resolution instanceof ResolvedColumn ? $fact->resolution->slot : null;

        return [new Field($position, new OutputSlot($name, $type, $fact->nullability, null, $origin), $this->expression, $fact->resolution)];
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
