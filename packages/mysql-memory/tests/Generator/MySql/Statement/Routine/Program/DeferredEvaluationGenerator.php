<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program;

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
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation;
use Tests\Generator\MySql\Statement\Name\TableWildcardGenerator;
use Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator;

/**
 * Generates DeferredEvaluation values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredEvaluation>
 */
final class DeferredEvaluationGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredEvaluation> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of DeferredEvaluation from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredEvaluation => new DeferredEvaluation(items: [...($parts[0] ? [new Star()] : []), $parts[1], ...$parts[2]], inputSource: null),
            Generators::tuple(
                Generators::bool(),
                new DeferredProjectionGenerator($release, $depth - 1),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new SelectExpressionGenerator($release, $depth - 1), 1 => new DeferredProjectionGenerator($release, $depth - 1), default => new TableWildcardGenerator($release)
                })))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredEvaluation $value) use ($release): bool {
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
     * @return GeneratedValueSingle<DeferredEvaluation>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<DeferredEvaluation>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
