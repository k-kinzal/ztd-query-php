<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml\Read;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Dml\Read\Ordering;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Scalar;

#[CoversClass(Ordering::class)]
#[Small]
final class OrderingTest extends TestCase
{
    /**
     * @return iterable<string, array{Scalar, ?string}>
     */
    public static function providerOperands(): iterable
    {
        $one = new M\Literal\NumberLiteral('01');
        yield 'integer' => [$one, '01'];
        yield 'zero' => [new M\Literal\NumberLiteral('0'), '0'];
        yield 'unsigned-maximum' => [new M\Literal\NumberLiteral('18446744073709551615'), '18446744073709551615'];
        yield 'grouped-plus' => [new M\Expression\Grouped(new M\Expression\Operator\Unary(M\Expression\Operator\UnaryOperator::Plus, new M\Expression\Grouped($one))), '01'];
        yield 'decimal' => [new M\Literal\NumberLiteral('1.0'), null];
        yield 'overflow-decimal' => [new M\Literal\NumberLiteral('18446744073709551616'), null];
        yield 'float' => [new M\Literal\NumberLiteral('1e0'), null];
        yield 'minus' => [new M\Expression\Operator\Unary(M\Expression\Operator\UnaryOperator::Minus, $one), null];
        yield 'text' => [new M\Literal\StringLiteral(['1']), null];
        yield 'column' => [new M\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('1')), null];
    }

    #[DataProvider('providerOperands')]
    public function testPositionDistinguishesPositionsFromConstantValues(Scalar $operand, ?string $position): void
    {
        self::assertEquals($position === null ? null : new M\Dml\Problem\Read\OrderingPosition(new M\Literal\NumberLiteral($position)), (new Ordering())->position($operand));
    }

    public function testProblemKeepsTheFirstPositionAndAcceptsAnEmptyClause(): void
    {
        $position = new M\Literal\NumberLiteral('2');
        $items = [new M\Query\OrderItem(new M\Literal\StringLiteral(['1'])), new M\Query\OrderItem($position), new M\Query\OrderItem(new M\Literal\NumberLiteral('3'))];
        self::assertSame($position, (new Ordering())->problem($items)?->position);
        self::assertNull((new Ordering())->problem([]));
    }
    public function testPositionKeepsLegacyBooleanPositionsSeparateFromModernValues(): void
    {
        $operand = new M\Literal\BooleanLiteral(true);
        self::assertNull((new Ordering())->position($operand));
        self::assertSame($operand, (new Ordering())->position(new M\Expression\Grouped($operand), true)?->position);
        self::assertSame("Unknown column 'TRUE' in 'order clause'", (new Ordering())->position($operand, true)?->message());
    }

}
