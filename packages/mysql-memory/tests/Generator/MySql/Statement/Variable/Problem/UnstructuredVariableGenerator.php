<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Variable\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable;

/**
 * Generates UnstructuredVariable values from the generators of its operands.
 *
 * @implements ErisGenerator<UnstructuredVariable>
 */
final class UnstructuredVariableGenerator implements ErisGenerator
{
    /** @var ErisGenerator<UnstructuredVariable> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): UnstructuredVariable => new UnstructuredVariable(name: $parts[0]),
            Generators::tuple(
                Generators::map(static fn (int $number): string => 'text_' . $number, Generators::choose(0, 99))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (UnstructuredVariable $value) use ($release): bool {
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
     * @return GeneratedValue<UnstructuredVariable>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<UnstructuredVariable> $element The generated counterexample
     * @return GeneratedValue<UnstructuredVariable>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
