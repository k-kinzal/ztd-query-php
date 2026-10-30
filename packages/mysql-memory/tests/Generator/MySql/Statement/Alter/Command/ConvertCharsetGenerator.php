<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Command;

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
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ConvertCharset;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use Tests\Generator\MySql\Statement\Name\CharsetNameGenerator;
use Tests\Generator\MySql\Statement\Name\CollationNameGenerator;

/**
 * Generates ConvertCharset values from the generators of its operands.
 *
 * @implements ErisGenerator<ConvertCharset>
 */
final class ConvertCharsetGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ConvertCharset> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ConvertCharset => new ConvertCharset(charset: $parts[0], collation: $parts[1], inputSource: $parts[2], form: $parts[3]),
            Generators::tuple(
                new CharsetNameGenerator($release),
                Generators::oneOf(Generators::constant(null), new CollationNameGenerator($release)),
                Generators::constant(null),
                Generators::elements(array_values(array_filter(CharsetForm::cases(), static fn (CharsetForm $form): bool => $form->named())))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ConvertCharset $value) use ($release): bool {
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
     * @return GeneratedValue<ConvertCharset>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<ConvertCharset> $element The generated counterexample
     * @return GeneratedValue<ConvertCharset>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
