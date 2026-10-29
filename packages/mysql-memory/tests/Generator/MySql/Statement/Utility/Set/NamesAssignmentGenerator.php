<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NamesAssignment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Source\Span;
use Tests\Generator\Composition;
use Tests\Generator\Operands;

/**
 * Composes the entire expression of a refused NAMES assignment before execution.
 *
 * @implements ErisGenerator<NamesAssignment>
 */
final class NamesAssignmentGenerator implements ErisGenerator
{
    /** @use Composition<NamesAssignment> */
    use Composition;

    /**
     * @param ErisGenerator<Scalar|RowOperand|DeferredOperand> $value The complete generated expression
     * @param ErisGenerator<Span|null>|null $operatorSource Optional assignment-operator locations
     * @param ErisGenerator<Span|null>|null $inputSource Optional complete-item locations
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\AssignmentOperator>|null $operator Generated assignment sign
     */
    public function __construct(ErisGenerator $value, ?ErisGenerator $operatorSource = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $operator = null)
    {
        $this->delegate = Generators::map(static fn (array $parts): NamesAssignment => new NamesAssignment($parts[0], $parts[1], $parts[2], $parts[3]), Operands::tuple($value, $operatorSource ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $operator ?? new \Tests\Generator\MySql\Statement\AssignmentOperatorGenerator()));
    }
}
