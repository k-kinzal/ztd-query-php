<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\AssignedConditionOperand;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredAssignmentGenerator;
use Tests\Generator\MySql\Statement\Variable\VariableAssignmentGenerator;

/**
 * Generates AssignedConditionOperand values from the generators of its operands.
 *
 * @implements ErisGenerator<AssignedConditionOperand>
 */
final class AssignedConditionOperandGenerator implements ErisGenerator
{
    /** @var ErisGenerator<AssignedConditionOperand> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): AssignedConditionOperand => new AssignedConditionOperand(operand: $parts[0]),
            Generators::tuple(
                Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new VariableAssignmentGenerator($release),
                    1 => new DeferredAssignmentGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                })
            )
        );
        $this->delegate = Generators::suchThat(
            static function (AssignedConditionOperand $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<AssignedConditionOperand>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<AssignedConditionOperand> $element The generated counterexample
     * @return GeneratedValue<AssignedConditionOperand>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
