<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine;

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
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\DeclaredCollation;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use Tests\Generator\MySql\Statement\Name\CollationNameGenerator;
use Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates Parameter values from the generators of its operands.
 *
 * @implements ErisGenerator<Parameter>
 */
final class ParameterGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Parameter> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): Parameter => new Parameter(name: $parts[0], type: $parts[1], collation: (static fn (?CollationName $collation): ?CollationName => $collation === null || ($collation->name === null && ($bit & 3) === 0) ? null : $collation)((new DeclaredCollation())->problem($parts[1], $parts[2]) === null ? $parts[2] : null), mode: $parts[3], inputSource: null),
            Generators::tuple(
                new NameGenerator($release),
                new DeclaredNameGenerator($release),
                Generators::oneOf(Generators::constant(null), new CollationNameGenerator($release)),
                Generators::oneOf(Generators::constant(null), new ParameterModeGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Parameter $value) use ($release): bool {
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
     * @return GeneratedValue<Parameter>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Parameter> $element The generated counterexample
     * @return GeneratedValue<Parameter>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
