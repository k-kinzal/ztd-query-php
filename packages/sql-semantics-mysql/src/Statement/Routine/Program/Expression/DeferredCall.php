<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression;

use LogicException;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\NativeFunctions;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes;
use SqlSemantics\Platform\MySql\Statement\Call\CallArgument;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A generic call template with an identity or scalar-argument condition.
 *
 * The call's name, qualification, arguments and aliases remain semantic values.
 * The surrounding program can be defined and can skip the instruction. This
 * expression never implements Scalar; ordinary calls use FunctionCall. An invalid
 * routine name is retained only in unreferenced definitions and inactive clauses,
 * not in the contextualized instructions of a stored declaration.
 *
 * @visibility public
 * @example Refusing a scalar-only deferred call
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall(new \SqlSemantics\Statement\Identifier\Name('ABS'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredCall implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<CallArgument|DeferredCallArgument> Arguments in written order
     */
    public readonly array $arguments;

    /**
     * @param Name $name The generic function name
     * @param list<CallArgument|DeferredCallArgument> $arguments Complete arguments, with a retained name or argument condition
     * @param Name|null $schema The database of a qualified stored function
     */
    public function __construct(public readonly Name $name, array $arguments, public readonly ?Name $schema = null, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        $accepted = [];
        Check::input(!str_contains($name->value, "\0"), 'A routine name cannot contain NUL.');
        foreach (Check::listOf($arguments, Node::class, 'A deferred call has argument operands.') as $argument) {
            Check::input($argument instanceof CallArgument || $argument instanceof DeferredCallArgument, 'Call operands retain their argument role.');
            $accepted[] = $argument;
        }
        $this->arguments = $accepted;
        Check::input(\SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames::problem($name) !== null || array_filter($accepted, static fn ($argument): bool => $argument instanceof DeferredCallArgument) !== [], 'A deferred call contains a name or non-scalar argument condition.');
        Check::input($schema === null || !$this->named(), 'Only an unqualified call takes argument aliases.');
        Check::input($schema !== null || (new NativeFunctions())->possible($name, count($accepted), $this->named()), 'A call requires a possible native signature or stored-function interpretation.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Tells whether an argument carries an explicit alias.
     */
    public function named(): bool
    {
        return array_filter($this->arguments, static fn ($argument): bool => $argument->alias !== null) !== [];
    }

    /**
     * Tells whether the selected release resolves this name as a native function.
     */
    public function native(GrammarRelease $release): bool
    {
        return $this->schema === null && (new NativeFunctions())->exists($release, $this->name->value);
    }

    /**
     * Identifies keyword collisions when an absent stored routine is reported.
     */
    public function keywordName(GrammarRelease $release): bool
    {
        return (new \SqlSemantics\Platform\MySql\Rendering\Keywords($release))->contains($this->name->value);
    }

    /**
     * Derives argument dependencies while retaining the invocation constraint.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $name = \SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames::problem($this->name);
        if ($name !== null) {
            $derivation->report($name);
        }
        foreach ($this->arguments as $argument) {
            $derivation->scalar($argument->expression, $environment);
        }
        return new ScalarFact(new Invalid($this->problem()), Nullability::Dependent);
    }

    /**
     * Exposes the arguments in written order.
     *
     * @return list<Scalar|RowOperand|DeferredOperand>
     */
    public function operands(): array
    {
        return array_map(static fn ($argument) => $argument->expression, $this->arguments);
    }

    /**
     * Answers the first argument's fixed scalar-width constraint.
     *
     * @throws LogicException If the constructor invariant has been bypassed
     */
    public function problem(): \SqlSemantics\Statement\Fact\Diagnostic
    {
        $name = \SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames::problem($this->name);
        if ($name !== null) {
            return $name;
        }
        foreach ($this->arguments as $argument) {
            $operand = $argument->expression;
            if (!$operand instanceof Scalar) {
                return $operand instanceof DeferredOperand ? $operand->problem() : new OperandColumns(1, count((new RowShapes())->parts($operand) ?? []));
            }
        }
        throw new LogicException('A deferred call requires a non-scalar argument.');
    }

    /**
     * Writes the qualified name and every retained argument.
     */
    public function render(Output $out): void
    {
        if ($this->schema !== null) {
            $out->name($this->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name, NameUse::Routine);
        if ($this->schema === null && $this->name->quote === \SqlSemantics\Statement\Identifier\NameQuote::Bare) {
            $out->separate();
        } else {
            $out->glue();
        }
        $out->symbol('(')->list($this->arguments)->symbol(')');
    }
}
