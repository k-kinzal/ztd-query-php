<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression;

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
use SqlSemantics\Platform\MySql\Rules\Query\Structure\SubqueryOptions;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate;
use SqlSemantics\Statement\Query;
use Tests\Generator\MySql\Statement\Expression\Subquery\QuantifierGenerator;
use Tests\Generator\Semantics\Statement\QueryGenerator;

/**
 * Generates DeferredSubqueryPredicate values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredSubqueryPredicate>
 */
final class DeferredSubqueryPredicateGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredSubqueryPredicate> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static function (array $parts): DeferredSubqueryPredicate {
                [$operand, $query, $operator, $quantifier, $negated] = $parts;
                $quantified = $operator !== null && $quantifier !== null;
                $operator = $quantified ? $operator : null;
                $quantifier = $quantified ? $quantifier : null;
                $precedence = new Precedence();
                if (!$precedence->fits($operand, $quantified ? Precedence::BOOL_PRI : Precedence::PREDICATE, $quantified ? Precedence::BOOL_PRI : Precedence::BIT_EXPR)) {
                    $operand = new DeferredGrouped($operand);
                }

                return new DeferredSubqueryPredicate(operand: $operand, query: $query, operator: $operator, quantifier: $quantifier, negated: !$quantified && $negated, scalarInformation: null, inputSource: null);
            },
            Generators::tuple(
                new DeferredOperandGenerator($release, $depth - 1),
                Generators::suchThat(static fn (Query $query): bool => (new SubqueryOptions())->problem($query) === null, new QueryGenerator($release, $depth - 1)),
                Generators::oneOf(Generators::constant(null), Generators::elements(array_values(array_filter(ComparisonOperator::cases(), static fn (ComparisonOperator $operator): bool => $operator !== ComparisonOperator::NullSafeEqual)))),
                Generators::oneOf(Generators::constant(null), new QuantifierGenerator($release)),
                Generators::bool()
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredSubqueryPredicate $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredSubqueryPredicate>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredSubqueryPredicate> $element The generated counterexample
     * @return GeneratedValue<DeferredSubqueryPredicate>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
