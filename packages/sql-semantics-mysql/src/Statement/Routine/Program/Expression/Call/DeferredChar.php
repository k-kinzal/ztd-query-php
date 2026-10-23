<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Rules\Typing\Texts;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
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
 * A CHAR call retaining a row or deferred operand until its template is read.
 *
 * The character set remains a declaration dependency even when no read reaches
 * the call. The retained argument condition does not make this value a Scalar.
 *
 * @visibility public
 * @example Refusing a deferred call without a deferred argument
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredChar([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('65')]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredChar implements DeferredOperand
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar|RowOperand|DeferredOperand> The complete character codes
     */
    public readonly array $arguments;

    /**
     * @param list<Scalar|RowOperand|DeferredOperand> $arguments At least one retained preparation condition
     * @param CharsetName|null $charset The explicitly named character set
     */
    public function __construct(array $arguments, public readonly ?CharsetName $charset = null)
    {
        Check::input($charset === null || $charset->name !== null, 'CHAR USING names a character set rather than DEFAULT.');
        $items = [];
        $deferred = false;
        foreach (Check::listOf($arguments, Operand::class, 'CHAR retains at least one code operand.', 1) as $argument) {
            Check::input($argument instanceof Scalar || $argument instanceof RowOperand || $argument instanceof DeferredOperand, 'CHAR code operands retain their scalar, row or deferred role.');
            $items[] = $argument;
            $deferred = $deferred || !$argument instanceof Scalar;
        }
        Check::input($deferred, 'A deferred CHAR call retains a preparation condition.');
        $this->arguments = $items;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds all dependencies while retaining the argument condition in the type.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        foreach ($this->arguments as $argument) {
            $derivation->scalar($argument, $environment);
        }
        (new Texts(Settings::of($derivation->context)))->character(count($this->arguments), $this->charset?->name?->value, $derivation);
        return new ScalarFact(new Invalid($this->problem()), Nullability::Dependent);
    }

    /**
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand> Dependencies in preparation order
     */
    public function operands(): array
    {
        return $this->arguments;
    }

    /**
     * Describes the first retained code condition.
     */
    public function problem(): Diagnostic
    {
        return (new DeferredArguments())->problem($this->arguments);
    }

    /**
     * Writes every code and the explicit character set.
     */
    public function render(Output $out): void
    {
        $out->keyword('CHAR')->glue()->symbol('(')->list($this->arguments);
        if ($this->charset !== null) {
            $out->keyword('USING')->node($this->charset);
        }
        $out->symbol(')');
    }
}
