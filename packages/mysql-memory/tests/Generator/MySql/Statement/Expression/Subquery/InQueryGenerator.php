<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Subquery;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\DerivedOptions;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\SubqueryOptions;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Statement\Query;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\Semantics\Statement\QueryGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates InQuery values from the generators of its operands.
 *
 * @implements ErisGenerator<InQuery>
 */
final class InQueryGenerator implements ErisGenerator
{
    /** @var ErisGenerator<InQuery> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): InQuery => new InQuery(operand: (new Precedence())->fits($parts[0], Precedence::PREDICATE, Precedence::BIT_EXPR) ? $parts[0] : new Grouped($parts[0]), query: $parts[1], negated: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4]),
            Generators::suchThat(
                static fn (array $parts): bool => ((new RowShapes())->query(((new Precedence())->fits($parts[0], Precedence::PREDICATE, Precedence::BIT_EXPR) ? $parts[0] : new Grouped($parts[0])), ($parts[1])) === null && (new ReadColumns())->problem(($parts[1])) === null)
                && ((new RowShapes())->query(((new Precedence())->fits($parts[0], Precedence::PREDICATE, Precedence::BIT_EXPR) ? $parts[0] : new Grouped($parts[0])), ($parts[1])) === null && (new ReadColumns())->problem(($parts[1])) === null && (new DerivedOptions())->problem(($parts[1])) === null)
                && ((new RowShapes())->query(((new Precedence())->fits($parts[0], Precedence::PREDICATE, Precedence::BIT_EXPR) ? $parts[0] : new Grouped($parts[0])), ($parts[1])) === null && (new ReadColumns())->problem(($parts[1])) === null && (new SubqueryOptions())->problem($parts[1]) === null),
                Generators::tuple(
                    Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ScalarGenerator($release, $depth - 1),
                        1 => new RowOperandGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    Generators::suchThat(
                        static fn (Query $query): bool => (new ReadColumns())->problem($query) === null && (new DerivedOptions())->problem($query) === null && (new SubqueryOptions())->problem($query) === null,
                        new QueryGenerator($release, $depth - 1),
                        1000
                    ),
                    Generators::bool(),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (InQuery $value) use ($release): bool {
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
     * @return GeneratedValue<InQuery>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<InQuery> $element The generated counterexample
     * @return GeneratedValue<InQuery>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
