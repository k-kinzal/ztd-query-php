<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Into;

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
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoOutfile;
use Tests\Generator\MySql\Statement\Dml\Load\TextFileFormatGenerator;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;

/**
 * Generates IntoOutfile values from the generators of its operands.
 *
 * @implements ErisGenerator<IntoOutfile>
 */
final class IntoOutfileGenerator implements ErisGenerator
{
    /** @var ErisGenerator<IntoOutfile> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of IntoOutfile from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): IntoOutfile => new IntoOutfile(file: $parts[0], format: $parts[1], inputSource: $parts[2]),
            Generators::tuple(
                new TextGenerator($release),
                new TextFileFormatGenerator($release),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (IntoOutfile $value) use ($release): bool {
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
     * @return GeneratedValueSingle<IntoOutfile>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<IntoOutfile>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
