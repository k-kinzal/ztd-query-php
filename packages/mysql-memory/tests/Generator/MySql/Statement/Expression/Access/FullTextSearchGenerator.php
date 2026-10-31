<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Access;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
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
use SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextSearch;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator;
use Tests\Generator\MySql\Statement\Name\ColumnUseGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates FullTextSearch values from the generators of its operands.
 *
 * @implements ErisGenerator<FullTextSearch>
 */
final class FullTextSearchGenerator implements ErisGenerator
{
    /** @var ErisGenerator<FullTextSearch> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of FullTextSearch from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static function (array $parts): FullTextSearch {
                [$columns, $against, $mode, $parentheses, $stated] = $parts;
                $stated = $mode === FullTextMode::Boolean ? OptionalWords::Omitted : $stated;
                $precedence = new Precedence();
                if (!$precedence->admits($against, Precedence::BIT_EXPR) || (($mode === FullTextMode::Boolean || $stated === OptionalWords::Written) && $precedence->absorbs($against, Precedence::PREDICATE, Precedence::BIT_EXPR))) {
                    $against = new Grouped($against);
                }

                return new FullTextSearch(columns: $columns, against: $against, mode: $mode, parentheses: $parentheses, stated: $stated, scalarInformation: null, inputSource: null);
            },
            Generators::tuple(
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => Generators::vector($count, new ColumnUseGenerator($release))),
                new ScalarGenerator($release, $depth - 1),
                new FullTextModeGenerator($release),
                new OptionalWordsGenerator($release),
                new OptionalWordsGenerator($release)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (FullTextSearch $value) use ($release): bool {
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
     * @return GeneratedValueSingle<FullTextSearch>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<FullTextSearch>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
