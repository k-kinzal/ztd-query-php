<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Server;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\ExplainableGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\DeferredSelectionGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredQueryGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredDeleteGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertQueryGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertRowsGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertSetGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredMultipleDeleteGenerator;
use Tests\Generator\MySql\Statement\Utility\Explain\ExplainModifierGenerator;
use Tests\Generator\MySql\Statement\Utility\Explain\ExplainVerbGenerator;
use Tests\Generator\MySql\Statement\Variable\UserVariableGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates DeferredExplain values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredExplain>
 */
final class DeferredExplainGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredExplain> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of DeferredExplain from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static function (array $parts) use ($bit): DeferredExplain {
                [$statement, $format, $into, $database, $analyze, $modifier, $emptyInto, $verb] = $parts;
                $analyze = ($bit & 508) !== 0 && $analyze;
                $into = ($bit & 504) !== 0 ? $into : null;
                $database = ($bit & 496) !== 0 ? $database : null;
                $emptyInto = ($bit & 48) !== 0 && $emptyInto;
                if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Explainable) {
                    return new DeferredExplain(statement: $statement, format: new Name('TRADITIONAL'), into: $into, database: $database, analyze: true, modifier: null, emptyInto: false, inputSource: null, verb: $verb);
                }
                if ($modifier !== null) {
                    return new DeferredExplain(statement: $statement, format: null, into: null, database: null, analyze: false, modifier: $modifier, emptyInto: false, inputSource: null, verb: $verb);
                }
                if ($emptyInto) {
                    return new DeferredExplain(statement: $statement, format: $format, into: null, database: $database, analyze: false, modifier: null, emptyInto: true, inputSource: null, verb: $verb);
                }

                return new DeferredExplain(statement: $statement, format: $format, into: $into, database: $database, analyze: $analyze, modifier: null, emptyInto: false, inputSource: null, verb: $verb);
            },
            Generators::tuple(
                Generators::bind(Generators::elements(array_keys(array_filter([508, 511, 511, 511, 511, 511, 508, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ExplainableGenerator($release, $depth - 1),
                    1 => new DeferredSelectionGenerator($release, $depth - 1),
                    2 => new DeferredQueryGenerator($release, $depth - 1),
                    3 => new DeferredInsertRowsGenerator($release, $depth - 1),
                    4 => new DeferredInsertSetGenerator($release, $depth - 1),
                    5 => new DeferredInsertQueryGenerator($release, $depth - 1),
                    6 => new DeferredDeleteGenerator($release, $depth - 1),
                    7 => new DeferredMultipleDeleteGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                Generators::oneOf(Generators::constant(null), new UserVariableGenerator($release)),
                Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                Generators::bool(),
                ($bit & 3) !== 0 ? ((new ReleaseDomain())->bit($release) & 3) !== 0 ? Generators::oneOf(Generators::constant(null), new ExplainModifierGenerator($release)) : Generators::constant(null) : Generators::constant(null),
                Generators::bool(),
                new ExplainVerbGenerator($release)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredExplain $value) use ($release): bool {
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
     * @return GeneratedValueSingle<DeferredExplain>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<DeferredExplain>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
