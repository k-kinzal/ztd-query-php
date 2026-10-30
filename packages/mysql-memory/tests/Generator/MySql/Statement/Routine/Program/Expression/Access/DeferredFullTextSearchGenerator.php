<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Access;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextMode;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Access\DeferredFullTextSearch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\MySql\Statement\Expression\Access\FullTextModeGenerator;
use Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\MySql\Statement\Name\ColumnUseGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;

/**
 * Generates DeferredFullTextSearch values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredFullTextSearch>
 */
final class DeferredFullTextSearchGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredFullTextSearch> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static function (array $parts): DeferredFullTextSearch {
                [$columns, $against, $mode, $parentheses, $stated] = $parts;
                $stated = $mode === FullTextMode::Boolean ? OptionalWords::Omitted : $stated;
                $precedence = new Precedence();
                if (!$precedence->admits($against, Precedence::BIT_EXPR) || (($mode === FullTextMode::Boolean || $stated === OptionalWords::Written) && $precedence->absorbs($against, Precedence::PREDICATE, Precedence::BIT_EXPR))) {
                    $against = $against instanceof Scalar ? new Grouped($against) : ($against instanceof RowOperand ? new GroupedRow($against) : new DeferredGrouped($against));
                }

                return new DeferredFullTextSearch(columns: $columns, against: $against, mode: $mode, parentheses: $parentheses, stated: $stated, scalarInformation: null, inputSource: null);
            },
            Generators::tuple(
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => Generators::vector($count, new ColumnUseGenerator($release))),
                Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => $choice === 0 ? new RowOperandGenerator($release, $depth - 1) : new DeferredOperandGenerator($release, $depth - 1)),
                new FullTextModeGenerator($release),
                new OptionalWordsGenerator($release),
                new OptionalWordsGenerator($release)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredFullTextSearch $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredFullTextSearch>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredFullTextSearch> $element The generated counterexample
     * @return GeneratedValue<DeferredFullTextSearch>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
