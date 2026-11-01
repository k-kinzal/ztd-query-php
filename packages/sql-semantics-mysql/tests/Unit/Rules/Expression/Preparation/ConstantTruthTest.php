<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Preparation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\ConstantTruth;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ConstantTruth::class)]
#[Small]
final class ConstantTruthTest extends TestCase
{
    /**
     * @return iterable<string, array{ComparisonOperator, string, string, bool}>
     */
    public static function providerComparisons(): iterable
    {
        yield 'equal' => [ComparisonOperator::Equal, '1', '1', true];
        yield 'not equal' => [ComparisonOperator::NotEqual, '1', '1', false];
        yield 'large unsigned' => [ComparisonOperator::Greater, '18446744073709551615', '1', true];
        yield 'ordered unsigned' => [ComparisonOperator::Less, '9223372036854775808', '18446744073709551615', true];
    }

    #[DataProvider('providerComparisons')]
    public function testOfKeepsSignedAndUnsignedIntegerOrdering(ComparisonOperator $operator, string $left, string $right, bool $expected): void
    {
        self::assertSame($expected, (new ConstantTruth())->of(new Comparison($operator, new NumberLiteral($left), new NumberLiteral($right))));
    }

    public function testOfKeepsUnknownColumnsUnresolved(): void
    {
        $rules = new ConstantTruth();
        self::assertTrue($rules->of(new BooleanLiteral(true)));
        self::assertNull($rules->of(new ColumnUse(new Name('a'))));
        self::assertNull($rules->of(new Comparison(ComparisonOperator::Equal, new ColumnUse(new Name('a')), new NumberLiteral('1'))));
    }
}
