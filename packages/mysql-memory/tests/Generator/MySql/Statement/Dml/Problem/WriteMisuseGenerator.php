<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem;

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
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use Tests\Generator\MySql\Statement\Relation\TableSourceGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates WriteMisuse values from the generators of its operands.
 *
 * @implements ErisGenerator<WriteMisuse>
 */
final class WriteMisuseGenerator implements ErisGenerator
{
    /** @var ErisGenerator<WriteMisuse> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of WriteMisuse from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): WriteMisuse => new WriteMisuse(rule: $parts[0], table: $parts[1], target: $parts[2]),
            Generators::tuple(
                new WriteRuleGenerator($release),
                Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                Generators::oneOf(Generators::constant(null), new TableSourceGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (WriteMisuse $value) use ($release): bool {
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
     * @return GeneratedValueSingle<WriteMisuse>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<WriteMisuse>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
