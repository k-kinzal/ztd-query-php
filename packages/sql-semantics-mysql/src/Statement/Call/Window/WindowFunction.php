<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Window;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\WindowResults;
use SqlSemantics\Platform\MySql\Rules\Call\Windows;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

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
 * @visibility public
 * @example Typing a ranking function
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT RANK() OVER w WINDOW w AS ()');
 *     [$query->field(0)->type->descriptor->name(), $query->field(0)->nullability] // => ['BIGINT', \SqlSemantics\Statement\Type\Nullability::NotNull]
 * @example Refusing an offset that is not a stable integer
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction(\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::Tile, [new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()], new \SqlSemantics\Statement\Identifier\Name('w')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a fractional ROWS offset in an ordinary invocation
 *     $window = new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec(frame: new \SqlSemantics\Platform\MySql\Statement\Call\Window\Frame(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Rows, new \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::Preceding, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1.5'))));
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction(\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::RowNumber, [], $window) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a fixed frame in an ordinary scalar
 *     $window = new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec(frame: new \SqlSemantics\Platform\MySql\Statement\Call\Window\Frame(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Groups, new \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::CurrentRow)));
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction(\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::RowNumber, [], $window) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class WindowFunction implements Scalar
{
    use Snapshot;

    /**
     * @var list<Scalar|RoutineVariable> The arguments in order
     */
    public readonly array $arguments;

    /**
     * @param WindowFunctionKind $kind The function
     * @param list<Scalar|RoutineVariable> $arguments The arguments in order
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
        $values = [];
        foreach ((new \SqlSemantics\Platform\MySql\Rules\Call\Window\Arguments())->checked($kind, $arguments, $nulls, $edge) as $argument) {
            Check::input($argument instanceof Scalar || $argument instanceof RoutineVariable, 'An ordinary window call requires scalar arguments.');
            $values[] = $argument;
        }
        $limit = (new \SqlSemantics\Platform\MySql\Rules\Call\Window\Arguments())->limitation($nulls, $edge);
        Check::input($limit === null, 'An ordinary window call cannot use an unsupported option: ' . ($limit->value ?? '') . '.');
        Check::input(!(new \SqlSemantics\Platform\MySql\Rules\Call\Window\Counts())->invalid($kind, $values), 'An ordinary window count or offset literal belongs to its signed integer domain.');
        $this->arguments = $values;
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Call\Window\Constraints())->problem($over) === null, 'An ordinary scalar invocation requires a window without fixed contradictions.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the arguments, the window and the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $facts = [];
        foreach ($this->arguments as $argument) {
            $facts[] = $argument instanceof RoutineVariable ? $derivation->scalar($argument, $environment) : (new Arguments())->one($argument, $derivation, $environment);
        }
        (new Windows())->derive($this->over, $derivation, $environment);
        $fact = (new WindowResults())->result($this, $facts, $derivation);
        $arguments = (new Precision())->all(array_map(static fn (ScalarFact $argument): TypeFact => $argument->type, $facts));
        $results = new WindowResults();
        $domain = $arguments === null ? null : $results->resolved($this, $arguments, $derivation, false);

        return $domain === null ? $fact : new ScalarFact(new Known($domain), $fact->nullability);
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
