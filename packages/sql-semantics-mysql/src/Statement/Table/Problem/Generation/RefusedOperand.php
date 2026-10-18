<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A generated column's intrinsic expression-role violation, without retained source input.
 *
 * @visibility public
 * @example Retaining a refused session function
 *     (new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand(new \SqlSemantics\Statement\Identifier\Name('g'), \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\OperandKind::FunctionCall, 'user'))->message() // => "Generated column 'g' excludes function user."
 */
final class RefusedOperand implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The owning column
     * @param OperandKind $kind The forbidden operand role
     * @param string|null $function The canonical function name, present exactly for a function refusal
     */
    public function __construct(public readonly Name $column, public readonly OperandKind $kind, public readonly ?string $function = null)
    {
        Check::input(($kind === OperandKind::FunctionCall) === ($function !== null && $function !== ''), 'Only a function refusal names a nonempty function.');
        Check::input($kind === OperandKind::FunctionCall || $function === null, 'A non-function refusal has no function name.');
    }

    /**
     * Describes the incompatible expression role.
     */
    public function message(): string
    {
        return "Generated column '" . $this->column->value . "' excludes " . match ($this->kind) {
            OperandKind::FunctionCall => 'function ' . $this->function,
            OperandKind::Subquery => 'a subquery',
            OperandKind::Variable => 'a session variable',
        } . '.';
    }
}
