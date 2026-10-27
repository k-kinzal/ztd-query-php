<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevel;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevelRange;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;

/**
 * A WEIGHT_STRING template retaining a non-scalar subject and every typed option.
 *
 * An unused definition or unexecuted stored instruction keeps its dependencies.
 * The subject remains outside Scalar; a reached use must prepare its operands.
 * No reconstructed SQL, parser node or substitute scalar is retained.
 *
 * @example Retaining the non-scalar subject in a skipped instruction
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() DO WEIGHT_STRING(ROW(1, 2))');
 *     $operation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure // => true
 * @visibility public
 */
final class DeferredWeightString implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<WeightLevel> The levels of the LEVEL clause
     */
    public readonly array $levels;

    /**
     * @param RowOperand|DeferredOperand $subject The string
     * @param WeightCast|null $cast The type the string is cast to
     * @param Numeral|null $length The length of the cast; present exactly with a cast
     * @param list<WeightLevel> $levels The levels of the LEVEL clause
     * @param WeightLevelRange|null $range The range of the LEVEL clause
     */
    public function __construct(
        public readonly RowOperand|DeferredOperand $subject,
        public readonly ?WeightCast $cast = null,
        public readonly ?Numeral $length = null,
        array $levels = [],
        public readonly ?WeightLevelRange $range = null,
        ?ScalarFact $scalarInformation = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
    ) {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        $this->levels = Check::listOf($levels, WeightLevel::class, 'A LEVEL clause is a list of levels.');
        Check::input(($cast === null) === ($length === null), 'A cast of WEIGHT_STRING has a length, and only a cast has one.');
        $magnitudes = new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes();
        Check::input($length === null || ($magnitudes->integral($length) && !$magnitudes->atMost($length, '0')), 'A WEIGHT_STRING cast length is a positive integer token.');
        Check::input($levels === [] || $range === null, 'A LEVEL clause is a list or a range.');
        Check::input($cast !== WeightCast::Binary || ($levels === [] && $range === null), 'WEIGHT_STRING takes no LEVEL clause after a binary cast.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the operand; the result is a binary string.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->scalar($this->subject, $environment);
        return new ScalarFact(new \SqlSemantics\Statement\Type\Invalid($this->problem()), \SqlSemantics\Statement\Type\Nullability::Dependent);
    }

    /**
     * @return non-empty-list<RowOperand|DeferredOperand> The subject's retained preparation dependencies
     */
    public function operands(): array
    {
        return [$this->subject];
    }

    /**
     * Answers the first intrinsic subject constraint.
     */
    public function problem(): \SqlSemantics\Statement\Fact\Diagnostic
    {
        return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$this->subject]);
    }

    /**
     * Writes the call.
     */
    public function render(Output $out): void
    {
        $out->keyword('WEIGHT_STRING')->glue()->symbol('(')->node($this->subject);
        if ($this->cast !== null) {
            $out->keyword('AS', $this->cast->value)->glue()->symbol('(')->node($this->length)->symbol(')');
        }
        if ($this->levels !== [] || $this->range !== null) {
            $out->keyword('LEVEL')->list($this->levels)->node($this->range);
        }
        $out->symbol(')');
    }
}
