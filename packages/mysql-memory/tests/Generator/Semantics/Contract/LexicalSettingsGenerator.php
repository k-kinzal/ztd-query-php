<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Contract;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;

/**
 * Generates LexicalSettings values from the generators of its operands.
 *
 * @implements ErisGenerator<LexicalSettings>
 */
final class LexicalSettingsGenerator implements ErisGenerator
{
    /** @var ErisGenerator<LexicalSettings> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $flags): LexicalSettings => new LexicalSettings(settings: implode(',', array_keys(array_filter($flags)))),
            Generators::associative([
                'ANSI_QUOTES' => Generators::bool(),
                'PIPES_AS_CONCAT' => Generators::bool(),
                'HIGH_NOT_PRECEDENCE' => Generators::bool(),
                'NO_BACKSLASH_ESCAPES' => Generators::bool(),
                'IGNORE_SPACE' => Generators::bool(),
            ])
        );
        $this->delegate = Generators::suchThat(
            static function (LexicalSettings $value) use ($release): bool {
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
     * @return GeneratedValue<LexicalSettings>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<LexicalSettings> $element The generated counterexample
     * @return GeneratedValue<LexicalSettings>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
