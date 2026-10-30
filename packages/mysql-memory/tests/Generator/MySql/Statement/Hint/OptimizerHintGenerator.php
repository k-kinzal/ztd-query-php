<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Hint;

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
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use Tests\Generator\MySql\Statement\Hint\Form\BlockNameHintGenerator;
use Tests\Generator\MySql\Statement\Hint\Form\ExecutionTimeHintGenerator;
use Tests\Generator\MySql\Statement\Hint\Form\KeyHintGenerator;
use Tests\Generator\MySql\Statement\Hint\Form\ResourceGroupHintGenerator;
use Tests\Generator\MySql\Statement\Hint\Form\StrategyHintGenerator;
use Tests\Generator\MySql\Statement\Hint\Form\TableHintGenerator;
use Tests\Generator\MySql\Statement\Hint\Form\VariableHintGenerator;

/**
 * Generates OptimizerHint values from the generators of its operands.
 *
 * @implements ErisGenerator<OptimizerHint>
 */
final class OptimizerHintGenerator implements ErisGenerator
{
    /** @var ErisGenerator<OptimizerHint> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::bind(Generators::elements(array_keys(array_filter([510, 510, 510, 508, 510, 510, 508], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
            0 => new BlockNameHintGenerator($release),
            1 => new ExecutionTimeHintGenerator($release),
            2 => new KeyHintGenerator($release),
            3 => new ResourceGroupHintGenerator($release),
            4 => new StrategyHintGenerator($release),
            5 => new TableHintGenerator($release),
            6 => new VariableHintGenerator($release),
            default => throw new LogicException('Unreachable alternative.'),
        });
        $this->delegate = Generators::suchThat(
            static function (OptimizerHint $value) use ($release): bool {
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
     * @return GeneratedValue<OptimizerHint>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<OptimizerHint> $element The generated counterexample
     * @return GeneratedValue<OptimizerHint>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
