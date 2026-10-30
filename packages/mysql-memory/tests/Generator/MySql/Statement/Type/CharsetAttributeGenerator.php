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
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use Tests\Generator\MySql\Statement\Type\Kind\BinaryMarkGenerator;
use Tests\Generator\MySql\Statement\Type\Kind\CharsetFormGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates CharsetAttribute values from the generators of its operands.
 *
 * @implements ErisGenerator<CharsetAttribute>
 */
final class CharsetAttributeGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CharsetAttribute> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new CharsetFormGenerator($release),
            static fn (CharsetForm $form): ErisGenerator => Generators::map(
                static fn (array $parts): CharsetAttribute => new CharsetAttribute(form: $form, charset: $form->named() ? $parts[0] : null, mark: in_array($form, [CharsetForm::Ascii, CharsetForm::Unicode, CharsetForm::Named, CharsetForm::CharacterSet, CharsetForm::CharSet], true) ? $parts[1] : BinaryMark::Absent, inputSource: null),
                Generators::tuple(new NameGenerator($release), new BinaryMarkGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (CharsetAttribute $value) use ($release): bool {
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
     * @return GeneratedValue<CharsetAttribute>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<CharsetAttribute> $element The generated counterexample
     * @return GeneratedValue<CharsetAttribute>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
