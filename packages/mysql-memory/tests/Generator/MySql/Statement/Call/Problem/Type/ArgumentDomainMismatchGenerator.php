<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem\Type;

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
use SqlSemantics\Platform\MySql\Statement\Call\Problem\Type\ArgumentDomainMismatch;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates ArgumentDomainMismatch values from the generators of its operands.
 *
 * @implements ErisGenerator<ArgumentDomainMismatch>
 */
final class ArgumentDomainMismatchGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ArgumentDomainMismatch> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ArgumentDomainMismatch => new ArgumentDomainMismatch(function: $parts[0], argument: $parts[1]),
            Generators::tuple(
                new NameGenerator($release),
                Generators::map(static fn (int $number): string => 'text_' . $number, Generators::choose(0, 99))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ArgumentDomainMismatch $value) use ($release): bool {
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
     * @return GeneratedValue<ArgumentDomainMismatch>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<ArgumentDomainMismatch> $element The generated counterexample
     * @return GeneratedValue<ArgumentDomainMismatch>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
