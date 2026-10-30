<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name;

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
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates ColumnUse values from the generators of its operands.
 *
 * @implements ErisGenerator<ColumnUse>
 */
final class ColumnUseGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ColumnUse> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $qualifiers = Generators::oneOf(Generators::constant(null), Generators::map(
            static fn (array $names): QualifiedName => new QualifiedName(name: $names[0], schema: $names[1]),
            Generators::tuple(new NameGenerator($release), Generators::oneOf(Generators::constant(null), new NameGenerator($release)))
        ));
        $this->delegate = Generators::bind(
            $qualifiers,
            static fn (?QualifiedName $qualifier): ErisGenerator => Generators::map(
                static fn (array $parts): ColumnUse => new ColumnUse(name: $parts[0], qualifier: $qualifier, dot: $parts[1], scalarInformation: null, inputSource: null),
                Generators::tuple(
                    new NameGenerator($release),
                    $qualifier !== null && $qualifier->schema === null && ((new ReleaseDomain())->bit($release) & 3) !== 0 ? new OptionalWordsGenerator($release) : Generators::constant(OptionalWords::Omitted)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ColumnUse $value) use ($release): bool {
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
     * @return GeneratedValue<ColumnUse>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<ColumnUse> $element The generated counterexample
     * @return GeneratedValue<ColumnUse>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
