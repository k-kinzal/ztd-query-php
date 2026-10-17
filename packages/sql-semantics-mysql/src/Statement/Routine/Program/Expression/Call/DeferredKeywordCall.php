<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A stored keyword call with a retained invocation-time operand condition.
 *
 * The keyword fixes the function identity; it is not a generic stored-function
 * lookup. Every argument retains its role, including unselected IF results.
 *
 * @visibility public
 * @example Preserving the conditional function and its deferred result
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     $call = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredKeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::If, [new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('0'), $row, new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()]);
 *     $call->function->value // => 'IF'
 */
final class DeferredKeywordCall implements DeferredOperand
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar|RowOperand|DeferredOperand> Arguments in preparation order
     */
    public readonly array $arguments;

    /**
     * @param KeywordFunction $function The fixed keyword function
     * @param list<Scalar|RowOperand|DeferredOperand> $arguments The arguments, including at least one deferred use
     */
    public function __construct(public readonly KeywordFunction $function, array $arguments)
    {
        $items = [];
        $deferred = false;
        foreach (Check::listOf($arguments, Operand::class, 'A deferred keyword call has argument operands.', 1) as $argument) {
            Check::input($argument instanceof Scalar || $argument instanceof RowOperand || $argument instanceof DeferredOperand, 'Keyword call operands retain their scalar, row or deferred role.');
            $items[] = $argument;
            $deferred = $deferred || !$argument instanceof Scalar;
        }
        Check::input($deferred, 'A deferred keyword call retains a preparation condition.');
        [$minimum, $maximum] = $function->arity();
        Check::input(count($items) >= $minimum && ($maximum === -1 || count($items) <= $maximum), 'The grammar does not accept this number of arguments for ' . $function->value . '.');
        $this->arguments = $items;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds every argument without invoking the retained condition.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        if ($this->function === KeywordFunction::OldPassword) {
            Deprecation::raise(Deprecated::OldPassword, $derivation);
        }
        Deprecation::spatial($this->function->value, $derivation);
        foreach ($this->arguments as $argument) {
            $derivation->scalar($argument, $environment);
        }
        return new ScalarFact(new Invalid($this->problem()), Nullability::Dependent);
    }

    /**
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand> The complete ordered arguments
     */
    public function operands(): array
    {
        return $this->arguments;
    }

    /**
     * Answers the first deferred argument condition.
     */
    public function problem(): Diagnostic
    {
        return (new DeferredArguments())->problem($this->arguments);
    }

    /**
     * Writes the fixed keyword and its complete argument list.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->function->value)->glue()->symbol('(')->list($this->arguments)->symbol(')');
    }
}
