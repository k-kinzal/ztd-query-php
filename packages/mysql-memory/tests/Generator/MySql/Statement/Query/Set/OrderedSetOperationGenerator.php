<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody;
use Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator;
use Tests\Generator\MySql\Statement\Query\OrderItemGenerator;
use Tests\Generator\MySql\Statement\Query\SelectGenerator;
use Tests\Generator\MySql\Statement\Query\With\DeferredBodyGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredOrderItemGenerator;
use Tests\Generator\Semantics\Statement\QueryGenerator;

/**
 * Generates OrderedSetOperation values from the generators of its operands.
 *
 * @implements ErisGenerator<OrderedSetOperation>
 */
final class OrderedSetOperationGenerator implements ErisGenerator
{
    /** @var ErisGenerator<OrderedSetOperation> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): OrderedSetOperation => new OrderedSetOperation(left: $parts[0], operator: $parts[1], quantifier: $parts[2], right: $parts[3], orderBy: $parts[4], limit: $parts[5], queryInformation: $parts[6], inputSource: $parts[7]),
            Generators::suchThat(
                static fn (array $parts): bool => (($parts[4]) !== [] || ($parts[5]) !== null)
                && (static function (Select|DeferredBody $right): bool {
                    $block = $right instanceof Select ? $right : $right->selection;
                    return $block->orderBy !== [] || $block->limit !== null || ($right instanceof Select && $right->locking !== []);
                })(($parts[3]))
                && (!($parts[0]) instanceof DeferredBody || (($parts[0])->selection->orderBy === [] && ($parts[0])->selection->procedure === null))
                && (!($parts[0]) instanceof SetOperation || !($parts[1])->tighter(($parts[0])->operator))
                && (!($parts[0]) instanceof LeadingUnion || ($parts[1]) === SetOperator::Union)
                && (!($parts[0]) instanceof OrderedSetOperation && !($parts[0]) instanceof QueryStatement && !(($parts[0]) instanceof QueryExpression && ($parts[0])->with !== null))
                && ((($parts[4]) !== [] || ($parts[5]) !== null)
                && (($parts[3]) instanceof DeferredBody ? (($parts[3])->selection->orderBy !== [] || ($parts[3])->selection->limit !== null) && ($parts[3])->selection->procedure === null : ($parts[3])->trailed() && ($parts[3])->late === null && ($parts[3])->into === null && ($parts[3])->procedure === null)
                && (!($parts[0]) instanceof SetOperation || !($parts[1])->tighter(($parts[0])->operator))
                && (!($parts[0]) instanceof LeadingUnion || ($parts[1]) === SetOperator::Union)
                && !($parts[0]) instanceof OrderedSetOperation && !($parts[0]) instanceof QueryStatement && !(($parts[0]) instanceof QueryExpression && ($parts[0])->with !== null)),
                Generators::tuple(
                    Generators::bind(Generators::elements(array_keys(array_filter([511, $depth > 0 ? 3 : 0], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new QueryGenerator($release, $depth - 1),
                        1 => new LeadingUnionGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    new SetOperatorGenerator($release),
                    Generators::oneOf(Generators::constant(null), new SetQuantifierGenerator($release)),
                    Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new SelectGenerator($release, $depth - 1),
                        1 => new DeferredBodyGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new OrderItemGenerator($release, $depth - 1),
                        1 => new DeferredOrderItemGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))) : Generators::constant([]),
                    Generators::oneOf(Generators::constant(null), new RowLimitGenerator($release)),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (OrderedSetOperation $value) use ($release): bool {
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
     * @return GeneratedValue<OrderedSetOperation>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<OrderedSetOperation> $element The generated counterexample
     * @return GeneratedValue<OrderedSetOperation>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
