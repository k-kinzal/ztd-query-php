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
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A stored generic call with an argument that requires invocation-time scalar preparation.
 *
 * The call's name, qualification, arguments and aliases remain semantic values.
 * The surrounding program can be defined and can skip the instruction. This
 * expression never implements Scalar; ordinary calls use FunctionCall.
 *
 * @visibility public
 * @example Refusing a scalar-only deferred call
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall(new \SqlSemantics\Statement\Identifier\Name('ABS'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredCall implements DeferredOperand
{
    use Snapshot;

    /**
     * @var non-empty-list<CallArgument|DeferredCallArgument> Arguments in written order
     */
    public readonly array $arguments;

    /**
     * @param Name $name The generic function name
     * @param list<CallArgument|DeferredCallArgument> $arguments At least one deferred argument
     * @param Name|null $schema The database of a qualified stored function
     */
    public function __construct(public readonly Name $name, array $arguments, public readonly ?Name $schema = null)
    {
        $accepted = [];
        foreach (Check::listOf($arguments, Node::class, 'A deferred call has argument operands.', 1) as $argument) {
            Check::input($argument instanceof CallArgument || $argument instanceof DeferredCallArgument, 'Call operands retain their argument role.');
            $accepted[] = $argument;
        }
        $this->arguments = $accepted;
        Check::input(array_filter($accepted, static fn ($argument): bool => $argument instanceof DeferredCallArgument) !== [], 'A deferred call contains a non-scalar argument.');
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
        foreach ($this->arguments as $argument) {
            $derivation->scalar($argument->expression, $environment);
        }
        return new ScalarFact(new Invalid($this->problem()), Nullability::Dependent);
    }

    /**
     * Exposes the arguments in written order.
     *
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand>
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
    public function problem(): OperandColumns
    {
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
        $out->name($this->name, NameUse::Routine)->glue()->symbol('(')->list($this->arguments)->symbol(')');
    }
}
