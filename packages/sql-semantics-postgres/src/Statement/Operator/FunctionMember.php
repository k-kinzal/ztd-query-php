<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\RoutineSignature;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `FUNCTION support_number [(op_type, ...)] function`: a support function of an operator class or family.
 *
 * The parenthesized types, when written, are the operand types the function
 * is registered for in the family.
 * Source: https://www.postgresql.org/docs/17/sql-createopclass.html, https://www.postgresql.org/docs/17/sql-alteropfamily.html.
 *
 * @visibility public
 * @example Reading the support number
 *     $member = new \SqlSemantics\Platform\PostgreSql\Statement\Operator\FunctionMember(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'), new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\RoutineSignature(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('btint4cmp')])));
 *     [$member->support->digits, $member->operandTypes] // => ['1', null]
 */
final class FunctionMember implements OperatorClassItem
{
    use Snapshot;

    /**
     * @var non-empty-list<TypeName>|null The operand types written in parentheses; null when not written
     */
    public readonly ?array $operandTypes;

    /**
     * @param IntegerConstant $support The support function number
     * @param RoutineSignature $function The function
     * @param list<TypeName>|null $operandTypes The operand types written in parentheses, at least one; null when not written
     */
    public function __construct(public readonly IntegerConstant $support, public readonly RoutineSignature $function, ?array $operandTypes = null)
    {
        $this->operandTypes = $operandTypes === null ? null : Check::listOf($operandTypes, TypeName::class, 'The operand types of a support function are at least one type name.', 1);
    }

    /**
     * Derives the operand types and the function signature.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->operandTypes ?? [] as $type) {
            $type->deriveClause($derivation, $environment);
        }
        $this->function->deriveClause($derivation, $environment);
    }

    /**
     * Writes FUNCTION, the number, the operand types and the function.
     */
    public function render(Output $out): void
    {
        $out->keyword('FUNCTION')->node($this->support);
        if ($this->operandTypes !== null) {
            $out->symbol('(')->list($this->operandTypes)->symbol(')');
        }
        $out->node($this->function);
    }
}
