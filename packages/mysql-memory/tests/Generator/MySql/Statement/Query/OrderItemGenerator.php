<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use Tests\Generator\MySql\Statement\Query\Clause\OutputOrdinalGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates OrderItem values from the generators of its operands.
 *
 * @implements ErisGenerator<OrderItem>
 */
final class OrderItemGenerator implements ErisGenerator
{
    /** @var ErisGenerator<OrderItem> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of OrderItem from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): OrderItem => new OrderItem(expression: match (true) {
                $parts[0] instanceof NumberLiteral && $parts[0]->form === NumberForm::Integer => new OutputOrdinal($parts[0]),
                $parts[0] instanceof BooleanLiteral && ((new ReleaseDomain())->bit($release) & 3) !== 0 => new OutputOrdinal(new NumberLiteral('1')),
                default => $parts[0],
            }, direction: $parts[1], inputSource: null),
            Generators::tuple(
                Generators::bind(Generators::bool(), static fn (bool $ordinal): ErisGenerator => $ordinal ? new OutputOrdinalGenerator($release) : new ScalarGenerator($release, $depth - 1)),
                Generators::oneOf(Generators::constant(null), new DirectionGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (OrderItem $value) use ($release): bool {
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
     * @return GeneratedValueSingle<OrderItem>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<OrderItem>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
