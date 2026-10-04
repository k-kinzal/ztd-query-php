<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Query\Carriers::class)]
#[Small]
final class CarriersTest extends TestCase
{
    public function testCoreUnwrapsParenthesesAndClauses(): void
    {
        $values = new \SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList([new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'))])]);
        self::assertSame($values, (new \SqlSemantics\Platform\PostgreSql\Rules\Query\Carriers())->core(new \SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery(new \SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression(null, $values, new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions(readOnly: true)))));
    }

    public function testCarryAddsAnOccurrenceNoNameReaches(): void
    {
        $context = (new \SqlSemantics\Platform\PostgreSql\Platform())->context(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172), null, [], true);
        $derivation = new \SqlSemantics\Construction\Derivation($context);
        $carrier = new \SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression(null, new \SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList([new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'))])]), new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions(readOnly: true));
        $environment = (new \SqlSemantics\Platform\PostgreSql\Rules\Query\Carriers())->carry($derivation->environment(), $carrier);
        self::assertSame([null, null, [], []], [$environment->relations[0]->alias, $environment->relations[0]->name, $environment->relations[0]->shape->slots, $environment->commonTables]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Rules\Query\Carrier::class, $environment->relations[0]->relation);
    }

    public function testTakeAnswersTheCarriersOfAQuery(): void
    {
        $context = (new \SqlSemantics\Platform\PostgreSql\Platform())->context(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172), null, [], true);
        $derivation = new \SqlSemantics\Construction\Derivation($context);
        $values = new \SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList([new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'))])]);
        $carrier = new \SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression(null, $values, new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions(readOnly: true));
        [$environment, $carriers] = (new \SqlSemantics\Platform\PostgreSql\Rules\Query\Carriers())->take((new \SqlSemantics\Platform\PostgreSql\Rules\Query\Carriers())->carry($derivation->environment(), $carrier), $values);
        self::assertSame([[], [$carrier]], [$environment->relations, $carriers]);
    }
}
