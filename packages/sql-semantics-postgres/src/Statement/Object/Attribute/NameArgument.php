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
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ArgumentText;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\AttributeReader;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

/**
 * An attribute value read as the name of an object of a fixed kind, such as the function of an operator.
 *
 * `defGetQualifiedName` accepts a name written as a type name, an operator,
 * a string (one name, as written) or a reserved keyword (one name, in lower
 * case). A plain, possibly qualified, name is kept as a dotted name. A name
 * written with type modifiers, array bounds, SETOF, `%TYPE` or as a type
 * keyword is kept as that type name, of which the server reads only the
 * names: a type keyword such as `integer` names `pg_catalog.int4`. The kind
 * is the object the command looks the name up as; for `multirange_type_name`
 * it is the type the command creates.
 * Source: https://www.postgresql.org/docs/17/sql-createoperator.html, `defGetQualifiedName` in `src/backend/commands/define.c` of PostgreSQL 17.
 *
 * @visibility SqlSemantics
 * @example Rejecting a bare type name, which is written as a dotted name
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\NameArgument(\SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind::Function, new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('f')])))) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class NameArgument implements AttributeArgument
{
    use Snapshot;

    /**
     * @param ObjectKind $kind The kind of object the name stands for
     * @param DottedName|TypeName|OperatorName|StringConstant|KeywordWord $name The name as written
     */
    public function __construct(public readonly ObjectKind $kind, public readonly DottedName|TypeName|OperatorName|StringConstant|KeywordWord $name)
    {
        Check::input(in_array($kind, Reading::kinds(), true), 'A definition attribute names a function, an operator, an operator class, a collation, a text search object or a type.');
        Check::input(!$name instanceof TypeName || !(new AttributeReader())->plain($name), 'A plain name is written as a dotted name, not as a type name.');
    }

    /**
     * Answers the names the server reads, from the outermost qualifier to the object name.
     *
     * @return non-empty-list<Name>
     */
    public function parts(): array
    {
        $name = $this->name;

        return match (true) {
            $name instanceof DottedName => $name->parts,
            $name instanceof TypeName => (new ArgumentText())->names($name),
            $name instanceof OperatorName => [...$name->qualifiers, $name->name],
            $name instanceof StringConstant => [new Name($name->value)],
            $name instanceof KeywordWord => [$name->word],
        };
    }

    /**
     * Tells whether the reading looks up an object of this kind.
     */
    public function fits(Reading $reading): bool
    {
        return $reading->kind() === $this->kind;
    }

    /**
     * Derives the modifier expressions of a name written as a type name.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->name->deriveClause($derivation, $environment);
    }

    /**
     * Writes the name as written; a dotted name is written where the grammar reads a type name.
     */
    public function render(Output $out): void
    {
        if ($this->name instanceof DottedName) {
            (new Spelling())->dotted($out, $this->name->parts, NameUse::Routine);

            return;
        }
        $out->node($this->name);
    }
}
