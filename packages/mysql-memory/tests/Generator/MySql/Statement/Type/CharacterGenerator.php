<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

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
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Keyword\CharacterAlias;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use Tests\Generator\MySql\Statement\Type\Kind\CharacterKindGenerator;

/**
 * Generates Character values from the generators of its operands.
 *
 * @implements ErisGenerator<Character>
 */
final class CharacterGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Character> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            Generators::tuple(new CharacterKindGenerator($release), Generators::bool()),
            static function (array $head) use ($release): ErisGenerator {
                [$kind, $national] = $head;
                $national = $national && in_array($kind, [CharacterKind::Char, CharacterKind::VarChar], true);
                $aliases = array_values(array_filter(CharacterAlias::cases(), static fn (CharacterAlias $alias): bool => $alias->kind() === $kind && $alias->national() === $national));
                $sized = in_array($kind, [CharacterKind::Char, CharacterKind::VarChar, CharacterKind::CharVarying, CharacterKind::Text], true);
                $required = in_array($kind, [CharacterKind::VarChar, CharacterKind::CharVarying], true);

                return Generators::map(
                    static fn (array $parts): Character => new Character(kind: $kind, length: $parts[0], national: $national, charset: $parts[1], inputSource: null, alias: $parts[2]),
                    Generators::tuple(
                        $required ? Generators::map(static fn (int $number): string => (string) $number, Generators::choose(0, 99)) : ($sized ? Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $number): string => (string) $number, Generators::choose(0, 99))) : Generators::constant(null)),
                        $national ? Generators::oneOf(Generators::constant(null), Generators::constant(new CharsetAttribute(CharsetForm::Binary))) : Generators::oneOf(Generators::constant(null), new CharsetAttributeGenerator($release)),
                        $aliases === [] ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), Generators::elements($aliases))
                    )
                );
            }
        );
        $this->delegate = Generators::suchThat(
            static function (Character $value) use ($release): bool {
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
     * @return GeneratedValue<Character>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Character> $element The generated counterexample
     * @return GeneratedValue<Character>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
