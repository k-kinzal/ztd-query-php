<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Set;

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
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody;
use Tests\Generator\MySql\Statement\Query\SelectGenerator;
use Tests\Generator\MySql\Statement\Query\With\DeferredBodyGenerator;
use Tests\Generator\Semantics\Statement\QueryGenerator;

/**
 * Generates LeadingUnion values from the generators of its operands.
 *
 * @implements ErisGenerator<LeadingUnion>
 */
final class LeadingUnionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<LeadingUnion> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): LeadingUnion => new LeadingUnion(left: $parts[0], quantifier: $parts[1], right: $parts[2], inputSource: $parts[3]),
            Generators::suchThat(
                static fn (array $parts): bool => (!$parts[0] instanceof DeferredBody || ($parts[0]->selection->orderBy === [] && $parts[0]->selection->procedure === null))
                    && (!$parts[0] instanceof SetOperation || $parts[0]->operator === SetOperator::Union)
                    && (!$parts[0] instanceof Select || ($parts[0]->orderBy === [] && $parts[0]->into === null && $parts[0]->procedure === null))
                    && !$parts[0] instanceof QueryStatement && !$parts[0] instanceof OrderedSetOperation && !($parts[0] instanceof QueryExpression && $parts[0]->with !== null),
                Generators::tuple(
                    Generators::bind(Generators::elements(array_keys(array_filter([511, $depth > 0 ? 3 : 0], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new QueryGenerator($release, $depth - 1),
                        1 => new LeadingUnionGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    Generators::oneOf(Generators::constant(null), new SetQuantifierGenerator($release)),
                    Generators::suchThat(
                        static function (Select|DeferredBody $right) use ($bit): bool {
                            $block = $right instanceof Select ? $right : $right->selection;
                            $own = $right instanceof DeferredBody ? $block->limit !== null : $right->trailed() && $right->into === null && $right->late === null;

                            return $own && $block->orderBy === [] && $block->procedure === null && (($bit & 1) !== 0 || ($right instanceof Select && $right->limit === null));
                        },
                        ($bit & 1) !== 0 ? Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                            0 => new SelectGenerator($release, $depth - 1),
                            1 => new DeferredBodyGenerator($release, $depth - 1),
                            default => throw new LogicException('Unreachable alternative.'),
                        }) : new SelectGenerator($release, $depth - 1),
                        1000
                    ),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (LeadingUnion $value) use ($release): bool {
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
     * @return GeneratedValue<LeadingUnion>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<LeadingUnion> $element The generated counterexample
     * @return GeneratedValue<LeadingUnion>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
