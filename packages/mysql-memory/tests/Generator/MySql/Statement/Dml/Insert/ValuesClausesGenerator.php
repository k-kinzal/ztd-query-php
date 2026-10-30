<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Insert;

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
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClauses;
use Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator;
use Tests\Generator\MySql\Statement\Query\OrderItemGenerator;
use Tests\Generator\MySql\Statement\Query\ValuesQueryGenerator;
use Tests\Generator\MySql\Statement\Query\With\WithGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredOrderItemGenerator;

/**
 * Generates ValuesClauses values from the generators of its operands.
 *
 * @implements ErisGenerator<ValuesClauses>
 */
final class ValuesClausesGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ValuesClauses> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ValuesClauses => new ValuesClauses(with: $parts[0], body: $parts[1], orderBy: [$parts[2], ...$parts[3]], limit: $parts[4], queryInformation: null, inputSource: null),
            Generators::tuple(
                $depth > 0 ? Generators::oneOf(Generators::constant(null), new WithGenerator($release, $depth - 1)) : Generators::constant(null),
                new ValuesQueryGenerator($release, $depth - 1),
                new DeferredOrderItemGenerator($release, $depth - 1),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::bool(), static fn (bool $deferred): ErisGenerator => $deferred ? new DeferredOrderItemGenerator($release, $depth - 1) : new OrderItemGenerator($release, $depth - 1)))),
                Generators::oneOf(Generators::constant(null), new RowLimitGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ValuesClauses $value) use ($release): bool {
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
     * @return GeneratedValue<ValuesClauses>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<ValuesClauses> $element The generated counterexample
     * @return GeneratedValue<ValuesClauses>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
