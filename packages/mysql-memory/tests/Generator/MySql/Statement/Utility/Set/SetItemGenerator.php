<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem;

/**
 * Generates SetItem values from the generators of its operands.
 *
 * @implements ErisGenerator<SetItem>
 */
final class SetItemGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SetItem> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of SetItem from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = $depth > 0
            ? Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 1, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new NameAssignmentGenerator($release, $depth - 1),
                1 => new NamesAssignmentGenerator($release, $depth - 1),
                2 => new PasswordAssignmentGenerator($release),
                3 => new SetCharacterSetGenerator($release),
                4 => new SetNamesGenerator($release),
                5 => new SystemAssignmentGenerator($release, $depth - 1),
                6 => new UserAssignmentGenerator($release, $depth - 1),
                default => throw new LogicException('Unreachable alternative.'),
            })
            : Generators::bind(Generators::elements(array_keys(array_filter([1, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new PasswordAssignmentGenerator($release),
                1 => new SetCharacterSetGenerator($release),
                2 => new SetNamesGenerator($release),
                default => throw new LogicException('Unreachable alternative.'),
            });
        $this->delegate = Generators::suchThat(
            static function (SetItem $value) use ($release): bool {
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
     * @return GeneratedValueSingle<SetItem>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<SetItem>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
