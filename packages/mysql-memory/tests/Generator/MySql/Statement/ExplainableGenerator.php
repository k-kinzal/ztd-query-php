<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement;

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
use SqlSemantics\Platform\MySql\Statement\Explainable;
use Tests\Generator\MySql\Statement\Dml\DeleteGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertQueryGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertRowsGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertSetGenerator;
use Tests\Generator\MySql\Statement\Dml\MultipleDeleteGenerator;
use Tests\Generator\MySql\Statement\Dml\UpdateGenerator;

/**
 * Generates Explainable values from the generators of its operands.
 *
 * @implements ErisGenerator<Explainable>
 */
final class ExplainableGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Explainable> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Explainable from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = $depth > 0
            ? Generators::bind(Generators::choose(0, 6), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new DeleteGenerator($release, $depth - 1),
                1 => new InsertQueryGenerator($release, $depth - 1),
                2 => new InsertRowsGenerator($release, $depth - 1),
                3 => new InsertSetGenerator($release, $depth - 1),
                4 => new MultipleDeleteGenerator($release, $depth - 1),
                5 => new UpdateGenerator($release, $depth - 1),
                6 => new QueryRootGenerator($release, $depth - 1),
                default => throw new LogicException('Unreachable alternative.'),
            })
            : Generators::bind(Generators::choose(0, 0), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new DeleteGenerator($release, $depth - 1),
                default => throw new LogicException('Unreachable alternative.'),
            });
        $this->delegate = Generators::suchThat(
            static function (Explainable $value) use ($release): bool {
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
     * @return GeneratedValueSingle<Explainable>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Explainable>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
