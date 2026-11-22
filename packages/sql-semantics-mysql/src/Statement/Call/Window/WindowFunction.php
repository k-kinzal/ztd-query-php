<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Window;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\WindowResults;
use SqlSemantics\Platform\MySql\Rules\Call\Windows;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;

/**
 * A call of a function that is only a window function: ranking, distribution, and the value functions LEAD, LAG, FIRST_VALUE, LAST_VALUE and NTH_VALUE.
 *
 * Rule: MYSQL-WINDOW-FUNCTION-001. The window is required. The count of
 * NTILE and the offset of LEAD and LAG are an integer, a parameter marker,
 * a user variable or a routine variable (stable_integer); the row number of
 * NTH_VALUE is a primary expression. The result follows
 * MYSQL-WINDOW-RESULT-001. Terminates: the arguments and the window are
 * strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-function-descriptions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing an offset that is not a stable integer
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction(\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::Tile, [new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()], new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('w')) // throws \SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction
 */
final class WindowFunction implements Scalar
{
    use Snapshot;

    /**
     * @var list<Scalar> The arguments in order
     */
    public readonly array $arguments;

    /**
     * @param WindowFunctionKind $kind The function
     * @param list<Scalar> $arguments The arguments in order
     * @param Name|WindowSpecification $over The window written after OVER
     * @param NullTreatment|null $nulls The written RESPECT NULLS or IGNORE NULLS
     * @param CountingEdge|null $edge The written FROM FIRST or FROM LAST of NTH_VALUE
     */
    public function __construct(
        public readonly WindowFunctionKind $kind,
        array $arguments,
        public readonly Name|WindowSpecification $over,
        public readonly ?NullTreatment $nulls = null,
        public readonly ?CountingEdge $edge = null,
    ) {
        $this->arguments = Check::listOf($arguments, Scalar::class, 'Window function arguments are expressions.');
        [$minimum, $maximum] = $kind->arity();
        Check::input(count($arguments) >= $minimum && count($arguments) <= $maximum, 'The grammar does not accept this number of arguments for ' . $kind->value . '.');
        Check::input($nulls === null || $kind->treatsNulls(), $kind->value . ' takes no NULL treatment.');
        Check::input($edge === null || $kind === WindowFunctionKind::NthValue, 'Only NTH_VALUE counts from an edge.');
        $counted = $arguments[$kind->counted() ?? -1] ?? null;
        Check::input($counted === null || $counted instanceof NumberLiteral || $counted instanceof Parameter || $counted instanceof UserVariable || $counted instanceof RoutineVariable, 'The count is an integer, a parameter marker or a variable.');
        Check::input($kind !== WindowFunctionKind::NthValue || (new Precedence())->admits($arguments[1], Precedence::SIMPLE_EXPR), 'The row number of NTH_VALUE needs a grouping.');
    }

    /**
     * Derives the arguments, the window and the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $facts = [];
        foreach ($this->arguments as $argument) {
            $facts[] = (new Arguments())->one($argument, $derivation, $environment);
        }
        (new Windows())->derive($this->over, $derivation, $environment);

        return (new WindowResults())->result($this, $facts, $derivation);
    }

    /**
     * Writes the call, its options and its window.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->glue()->symbol('(')->list($this->arguments)->symbol(')');
        if ($this->edge !== null) {
            $out->keyword(...explode(' ', $this->edge->value));
        }
        if ($this->nulls !== null) {
            $out->keyword(...explode(' ', $this->nulls->value));
        }
        (new Windows())->render($this->over, $out);
    }
}
