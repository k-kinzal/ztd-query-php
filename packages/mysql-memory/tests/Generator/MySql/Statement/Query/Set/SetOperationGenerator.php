<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Set;

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
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody;
use Tests\Generator\Semantics\Statement\QueryGenerator;

/**
 * Generates SetOperation values from the generators of its operands.
 *
 * @implements ErisGenerator<SetOperation>
 */
final class SetOperationGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SetOperation> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of SetOperation from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): SetOperation => new SetOperation(left: $parts[0], operator: $parts[1], quantifier: $parts[2], right: $parts[3], queryInformation: $parts[4], inputSource: $parts[5]),
            Generators::suchThat(
                static fn (array $parts): bool => (!($parts[3]) instanceof SetOperation || ($parts[3])->operator->tighter(($parts[1])))
                && (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\DerivedOptions())->problem($parts[3]) === null
                && ((new ReleaseDomain())->operand($parts[3]) & $bit) !== 0
                && (!($parts[0]) instanceof DeferredBody || (($parts[0])->selection->orderBy === [] && ($parts[0])->selection->procedure === null))
                && (!($parts[0]) instanceof SetOperation || !($parts[1])->tighter(($parts[0])->operator))
                && (!($parts[0]) instanceof LeadingUnion || ($parts[1]) === SetOperator::Union)
                && !($parts[0]) instanceof QueryStatement && !($parts[3]) instanceof QueryStatement
                && !($parts[0]) instanceof OrderedSetOperation && !($parts[3]) instanceof OrderedSetOperation
                && !($parts[3]) instanceof QueryExpression && !(($parts[0]) instanceof QueryExpression && ($parts[0])->with !== null)
                && (!($parts[0]) instanceof Select || (($parts[0])->orderBy === [] && ($parts[0])->into === null && ($parts[0])->procedure === null))
                && (!($parts[3]) instanceof Select || (($parts[3])->orderBy === [] && ($parts[3])->limit === null && ($parts[3])->into === null && ($parts[3])->locking === [] && ($parts[3])->procedure === null))
                && (!($parts[3]) instanceof DeferredBody || (($parts[3])->selection->orderBy === [] && ($parts[3])->selection->limit === null && ($parts[3])->selection->procedure === null))
                && (($bit & 3) !== 0 || (!(($parts[0]) instanceof Select && ($parts[0])->trailed()) && !($parts[0]) instanceof QueryExpression))
                && (($bit & 1) !== 0 || (!(($parts[0]) instanceof Select && ($parts[0])->limit !== null) && !(($parts[0]) instanceof DeferredBody && ($parts[0])->selection->limit !== null))),
                Generators::tuple(
                    Generators::bind(Generators::elements(array_keys(array_filter([511, $depth > 0 ? 3 : 0], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new QueryGenerator($release, $depth - 1),
                        1 => new LeadingUnionGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    new SetOperatorGenerator($release),
                    Generators::oneOf(Generators::constant(null), new SetQuantifierGenerator($release)),
                    new QueryGenerator($release, $depth - 1),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (SetOperation $value) use ($release): bool {
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
     * @return GeneratedValueSingle<SetOperation>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<SetOperation>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
