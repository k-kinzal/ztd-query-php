<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table;

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
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;
use Tests\Generator\MySql\Statement\Dml\DuplicateHandlingGenerator;
use Tests\Generator\Semantics\Statement\QueryGenerator;

/**
 * Generates TableQuery values from the generators of its operands.
 *
 * @implements ErisGenerator<TableQuery>
 */
final class TableQueryGenerator implements ErisGenerator
{
    /** @var ErisGenerator<TableQuery> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): TableQuery => new TableQuery(query: $parts[0], duplicate: $parts[1], inputSource: $parts[2], as: $parts[3]),
            Generators::tuple(
                new QueryGenerator($release, $depth - 1),
                Generators::oneOf(Generators::constant(null), new DuplicateHandlingGenerator($release)),
                Generators::constant(null),
                Generators::bool()
            )
        );
        $this->delegate = Generators::suchThat(
            static function (TableQuery $value) use ($release): bool {
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
     * @return GeneratedValue<TableQuery>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<TableQuery> $element The generated counterexample
     * @return GeneratedValue<TableQuery>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
