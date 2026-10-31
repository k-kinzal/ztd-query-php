<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates InvalidTypeSize values from the generators of its operands.
 *
 * @implements ErisGenerator<InvalidTypeSize>
 */
final class InvalidTypeSizeGenerator implements ErisGenerator
{
    /** @var ErisGenerator<InvalidTypeSize> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of InvalidTypeSize from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): InvalidTypeSize => new InvalidTypeSize(rule: $parts[0], name: $parts[1], actual: $parts[2], maximum: $parts[3], scope: $parts[4]),
            Generators::tuple(
                new TypeLimitGenerator($release),
                Generators::map(static fn (int $number): string => 'text_' . $number, Generators::choose(0, 99)),
                Generators::choose(0, 100),
                Generators::choose(0, 100),
                Generators::oneOf(Generators::constant(null), new QualifiedNameGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (InvalidTypeSize $value) use ($release): bool {
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
     * @return GeneratedValueSingle<InvalidTypeSize>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<InvalidTypeSize>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
