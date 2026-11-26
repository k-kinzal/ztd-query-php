<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * One changed attribute of `ALTER OPERATOR ... SET` or `ALTER TYPE ... SET`.
 *
 * Mirrors an `operator_def_elem`: `name = NONE` and the name written alone
 * (PostgreSQL 17) both give the attribute no value, which removes a function
 * the attribute names; the written form is kept. Otherwise the attribute
 * holds its value as the command reads it.
 * Source: `operator_def_elem` in `src/backend/parser/gram.y` of PostgreSQL 17.
 *
 * @visibility SqlSemantics
 * @example Refusing NONE together with a value
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\AttributeChange(new \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Attribute(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('x'), null, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('y')), true) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
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
