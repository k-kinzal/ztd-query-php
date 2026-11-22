<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\AttributeReader;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

/**
 * One attribute of a definition, `name` or `name = value`, as the defining command reads it.
 *
 * Mirrors PostgreSQL's `DefElem` together with the meaning its command gives
 * it. A recognized attribute names the member of the command's attribute set
 * and holds its value read the way the command reads it, such as a routine
 * name for `function` or a type for `leftarg`. The value stays as written
 * when the command does not read it: for an attribute the command does not
 * recognize, for an option of a text search template, for an obsolete
 * attribute whose value is ignored, and for a value the command rejects,
 * such as a number where it expects a name.
 * Source: https://www.postgresql.org/docs/17/sql-createoperator.html, `src/backend/commands/define.c` of PostgreSQL 17.
 *
 * @visibility SqlSemantics
 * @example Rejecting a recognized attribute whose value is left as written although the command reads it
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Attribute(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('function'), \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorAttribute::Function, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('int4eq')) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class Attribute implements Clause
{
    use Snapshot;

    /**
     * @param Name $name The attribute name as written, decoded
     * @param KnownAttribute|null $known The member of the command's attribute set the name is; null when the command does not recognize the name
     * @param AttributeArgument|TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null $value The value as the command reads it, or as written when the command does not read it; null when given alone
     */
    public function __construct(
        public readonly Name $name,
        public readonly ?KnownAttribute $known,
        public readonly AttributeArgument|TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null $value = null,
    ) {
        Check::input($known === null || $known->text() === $name->value, 'A recognized attribute is the member its name names.');
        if ($value instanceof AttributeArgument) {
            Check::input($known !== null && $value->fits($known->reading()), 'A read value belongs to a recognized attribute that reads it that way.');
        } else {
            Check::input($known === null || !(new AttributeReader())->read($known->reading(), $value) instanceof AttributeArgument, 'A value the command reads is kept as read.');
        }
    }

    /**
     * Tells whether a value is written after the name.
     */
    public function valued(): bool
    {
        return $this->value instanceof BooleanArgument ? $this->value->written !== null : $this->value !== null;
    }

    /**
     * Derives the value, which may hold type modifier expressions.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->value?->deriveClause($derivation, $environment);
    }

    /**
     * Writes the name and the value.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Label);
        if ($this->valued()) {
            $out->symbol('=')->node($this->value);
        }
    }
}
