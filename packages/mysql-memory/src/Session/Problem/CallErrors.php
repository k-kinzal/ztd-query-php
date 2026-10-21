<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use LogicException;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount;
use SqlSemantics\Statement\Fact\Diagnostic;

/**
 * Maps semantic call and window constraints to their public MySQL conditions.
 *
 * @visibility MySqlMemory
 */
final class CallErrors
{
    /**
     * Preserves native function spelling when the input phase requires it.
     */
    public function error(Diagnostic $diagnostic, bool $originalCase = false): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\CharacterCastRange => \MySqlMemory\Error\Family\DataError::DataOutOfRange->error('char(n)', 'cast_as_char'),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\SpatialArithmetic => StatementError::WrongArguments->error($diagnostic->operator->value),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\Type\ArgumentDomainMismatch => \MySqlMemory\Error\Family\DataError::IncorrectType->error($diagnostic->argument, $diagnostic->function->value),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidWindowCount => StatementError::WrongArguments->error(strtolower($diagnostic->function->value)),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidJsonCast => \MySqlMemory\Error\Family\DataError::InvalidSpatialCast->error('JSON', $diagnostic->target->value),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\Window\NestingConstraint => QueryError::WindowFunctionInSpecification->error('<unnamed window>'),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinement => $this->refinement($diagnostic),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameConstraint => $this->frame($diagnostic),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowOperandConstraint => (new \MySqlMemory\Program\Expression\RowUse())->error($diagnostic->window->operand()),
            $diagnostic instanceof WrongArgumentCount => QueryError::WrongParameterCountToNativeFunction->error($diagnostic->function->value),
            $diagnostic instanceof NamedArgument => QueryError::WrongParametersToNativeFunction->error($originalCase ? $diagnostic->function->value : strtolower($diagnostic->function->value)),
            $diagnostic instanceof ReservedFunction => QueryError::NativeFunctionRejected->error($diagnostic->function->value),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidNamedConstant => ($diagnostic->nullName ? StatementError::ReservedSyntax : StatementError::WrongArguments)->error('NAME_CONST'),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint => ($diagnostic->positional ? QueryError::WindowPositionalOrdering : QueryError::WindowFrameIllegal)->error('<unnamed window>'),
            $diagnostic instanceof UnsupportedWindowing => StatementError::NotSupportedYet->error($diagnostic->limit->value),
            default => null,
        };
    }
    /**
     * Maps a retained frame condition after the owning query prepares its inputs.
     */
    public function frame(\SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameConstraint $diagnostic, string $name = '<unnamed window>'): SqlError
    {
        return (match ($diagnostic->condition) {
            \SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameCondition::RowsInterval => QueryError::WindowRowsInterval,
            \SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameCondition::RangeOrder => QueryError::WindowRangeOrderType,
            \SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameCondition::Offset => QueryError::WindowFrameIllegal,
        })->error($name);
    }

    /**
     * Maps a lexical window relationship after its enclosing read has prepared.
     *
     * @throws LogicException When an ordering constraint lacks its owning definition
     */
    public function refinement(\SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinement $diagnostic): SqlError
    {
        $condition = (new \SqlSemantics\Platform\MySql\Rules\Call\Window\Refinements())->condition($diagnostic->window, $diagnostic->ancestors);
        \SqlSemantics\Diagnostic\Check::invariant($condition !== null, 'A window refinement retains its fixed relationship.');
        return match ($condition) {
            \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinementKind::Partition => QueryError::WindowDependentPartitioning->error(),
            \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinementKind::Ordering => QueryError::WindowInheritedOrdering->error($diagnostic->name->value ?? '<unnamed window>', ((new \SqlSemantics\Platform\MySql\Rules\Call\Window\Refinements())->ordered($diagnostic->ancestors) ?? throw new LogicException('An inherited ordering condition has its owning definition.'))->name->value),
            \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinementKind::Frame => QueryError::WindowInheritedFrame->error($diagnostic->ancestors[0]->name->value),
        };
    }
}
