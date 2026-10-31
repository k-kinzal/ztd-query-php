<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\NativeFunctions;
use SqlSemantics\Platform\MySql\Statement\Call\CallArgument;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCallArgument;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A native-call spelling retained in an uncontextualized clause or CTE definition.
 *
 * This is not a Scalar or an invocable stored expression. Statement constructor
 * checks restrict it to unused definitions and clauses of INSERT/REPLACE VALUES sources.
 * No SQL text or parser node is retained.
 *
 * @visibility public
 * @example Retaining a native signature outside the Scalar domain
 *     $call = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DormantCall(new \SqlSemantics\Statement\Identifier\Name('ABS'));
 *     [$call instanceof \SqlSemantics\Statement\Scalar, $call->operands()] // => [false, []]
 */
final class DormantCall implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<CallArgument|DeferredCallArgument> The complete written arguments
     */
    public readonly array $arguments;

    /**
     * @param Name $name The unqualified native function name
     * @param list<CallArgument|DeferredCallArgument> $arguments Written arguments and aliases
     */
    public function __construct(public readonly Name $name, array $arguments = [], ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        $items = [];
        foreach (Check::listOf($arguments, Node::class, 'A dormant call retains argument roles.') as $argument) {
            Check::input($argument instanceof CallArgument || $argument instanceof DeferredCallArgument, 'Dormant call arguments have an argument role.');
            $items[] = $argument;
        }
        $this->arguments = $items;
        Check::input(!(new NativeFunctions())->possible($name, count($items), $this->named()), 'A dormant call has no invocable native signature.');
    }

    /**
     * Answers whether an argument carries an alias.
     */
    public function named(): bool
    {
        return array_filter($this->arguments, static fn ($argument): bool => $argument->alias !== null) !== [];
    }

    /**
     * @return list<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|DeferredOperand> Retained arguments in written order
     */
    public function operands(): array
    {
        return array_map(static fn ($argument) => $argument->expression, $this->arguments);
    }

    /**
     * Derives retained dependencies without inventing a scalar result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->report($this->problem());
        foreach ($this->arguments as $argument) {
            $derivation->scalar($argument->expression, $environment);
        }
        return new ScalarFact(new Invalid($this->problem()), Nullability::Dependent);
    }

    /**
     * Answers the call's fixed contextualization condition.
     */
    public function problem(): \SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount|\SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument|\SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction
    {
        $problem = (new NativeFunctions())->problem(GrammarRelease::MySql847, $this->name, count($this->arguments), $this->named());
        Check::invariant($problem !== null, 'A dormant native call retains its signature condition.');
        return $problem;
    }

    /**
     * Writes the retained name and complete argument list.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Routine)->glue()->symbol('(')->list($this->arguments)->symbol(')');
    }
}
