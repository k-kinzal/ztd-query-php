<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * One changed attribute of `ALTER OPERATOR ... SET` or `ALTER TYPE ... SET`.
 *
 * Mirrors an `operator_def_elem`: `name = NONE` and the name written alone
 * (PostgreSQL 17) both give the attribute no value, which removes a function
 * the attribute names; the written form is kept. Otherwise the attribute
 * holds its value as the command reads it.
 * Source: `operator_def_elem` in `src/backend/parser/gram.y` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading an attribute set to NONE
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER OPERATOR = (int4, int4) SET (restrict = NONE)');
 *     [$operation->statement->attributes[0]->none, $operation->statement->attributes[0]->attribute->value] // => [true, null]
 * @example Refusing NONE together with a value
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\AttributeChange(new \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Attribute(new \SqlSemantics\Statement\Identifier\Name('x'), null, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('y')), true) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class AttributeChange implements Clause
{
    use Snapshot;

    /**
     * @param Attribute $attribute The attribute with its value as the command reads it; without a value for NONE
     * @param bool $none Whether the attribute is written `= NONE`
     */
    public function __construct(public readonly Attribute $attribute, public readonly bool $none = false)
    {
        Check::input(!$none || !$attribute->valued(), 'An attribute written = NONE has no other value.');
    }

    /**
     * Derives the value.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->attribute->deriveClause($derivation, $environment);
    }

    /**
     * Writes the attribute, with `= NONE` when written so.
     */
    public function render(Output $out): void
    {
        if ($this->none) {
            $out->name($this->attribute->name, NameUse::Label)->symbol('=')->keyword('NONE');
        } else {
            $out->node($this->attribute);
        }
    }
}
