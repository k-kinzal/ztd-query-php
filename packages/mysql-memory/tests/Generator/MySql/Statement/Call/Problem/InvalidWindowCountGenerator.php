<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

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
use SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidWindowCount;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;

/**
 * Generates InvalidWindowCount values from the generators of its operands.
 *
 * @implements ErisGenerator<InvalidWindowCount>
 */
final class InvalidWindowCountGenerator implements ErisGenerator
{
    /** @var ErisGenerator<InvalidWindowCount> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): InvalidWindowCount => new InvalidWindowCount(function: $parts[0]),
            Generators::tuple(
                Generators::elements(array_values(array_filter(WindowFunctionKind::cases(), static fn (WindowFunctionKind $kind): bool => $kind->counted() !== null || $kind === WindowFunctionKind::NthValue)))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (InvalidWindowCount $value) use ($release): bool {
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
     * @return GeneratedValue<InvalidWindowCount>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<InvalidWindowCount> $element The generated counterexample
     * @return GeneratedValue<InvalidWindowCount>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
