<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `OPERATOR strategy (types)` or `FUNCTION support (types)`: a member ALTER OPERATOR FAMILY ... DROP removes.
 *
 * Source: https://www.postgresql.org/docs/17/sql-alteropfamily.html.
 *
 * @visibility public
 * @example Reading the removed member
 *     $type = new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('int4')])));
 *     $removal = new \SqlSemantics\Platform\PostgreSql\Statement\Operator\MemberRemoval(\SqlSemantics\Platform\PostgreSql\Statement\Operator\MemberKind::Operator, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'), [$type, $type]);
 *     [$removal->kind->value, count($removal->types)] // => ['OPERATOR', 2]
 */
final class MemberRemoval implements Clause
{
    use Snapshot;

    /**
     * @var non-empty-list<TypeName> The operand types
     */
    public readonly array $types;

    /**
     * @param MemberKind $kind Operator or support function
     * @param IntegerConstant $number The strategy or support number
     * @param list<TypeName> $types The operand types; at least one
     */
    public function __construct(public readonly MemberKind $kind, public readonly IntegerConstant $number, array $types)
    {
        $this->types = Check::listOf($types, TypeName::class, 'A removed member names at least one operand type.', 1);
    }

    /**
     * Derives the type modifiers.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->types as $type) {
            $type->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes the kind, the number and the types.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->node($this->number)->symbol('(')->list($this->types)->symbol(')');
    }
}
