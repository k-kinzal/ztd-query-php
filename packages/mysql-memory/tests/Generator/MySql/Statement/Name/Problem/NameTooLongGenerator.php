<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name\Problem;

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
use SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Name\ObjectNameKindGenerator;

/**
 * Generates NameTooLong values from the generators of its operands.
 *
 * @implements ErisGenerator<NameTooLong>
 */
final class NameTooLongGenerator implements ErisGenerator
{
    /** @var ErisGenerator<NameTooLong> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): NameTooLong => new NameTooLong(name: new Name(str_repeat('x', 65 + $parts[0])), kind: $parts[1]),
            Generators::tuple(
                Generators::choose(0, 9),
                Generators::oneOf(Generators::constant(null), new ObjectNameKindGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (NameTooLong $value) use ($release): bool {
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
     * @return GeneratedValue<NameTooLong>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<NameTooLong> $element The generated counterexample
     * @return GeneratedValue<NameTooLong>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
