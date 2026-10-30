<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Write;

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
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredDelete;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\MySql\Statement\Dml\DeleteOptionGenerator;
use Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator;
use Tests\Generator\MySql\Statement\Hint\OptimizerHintGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator;
use Tests\Generator\MySql\Statement\Query\DirectionGenerator;
use Tests\Generator\MySql\Statement\Query\With\CommonTableExpressionGenerator;
use Tests\Generator\MySql\Statement\Query\With\WithGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates DeferredDelete values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredDelete>
 */
final class DeferredDeleteGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredDelete> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredDelete => new DeferredDelete(with: new With(false, [$parts[8]]), options: $parts[1], table: new WriteTarget(new QualifiedName($parts[8]->name), null, $parts[2]->partitions), where: $parts[3], orderBy: $parts[4], limit: $parts[5] instanceof RowLimit && $parts[5]->offset !== null ? new RowLimit($parts[5]->count) : $parts[5], hints: $parts[6], inputSource: $parts[7]),
            Generators::tuple(
                $depth > 0 && ($bit & 508) !== 0 ? Generators::oneOf(Generators::constant(null), new WithGenerator($release, $depth - 1)) : Generators::constant(null),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new DeleteOptionGenerator($release))),
                new WriteTargetGenerator($release),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), new ScalarGenerator($release, $depth - 1)) : Generators::constant(null),
                $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::map(static fn (array $item): OrderItem => new OrderItem($item[0], $item[1]), Generators::tuple(new ScalarGenerator($release, $depth - 1), Generators::oneOf(Generators::constant(null), new DirectionGenerator($release)))))) : Generators::constant([]),
                Generators::oneOf(Generators::constant(null), new RowLimitGenerator($release)),
                ($bit & 510) !== 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new OptimizerHintGenerator($release))) : Generators::constant([]),
                Generators::constant(null),
                new CommonTableExpressionGenerator($release, $depth - 1)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredDelete $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredDelete>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredDelete> $element The generated counterexample
     * @return GeneratedValue<DeferredDelete>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
