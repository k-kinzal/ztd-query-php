<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler as H;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(H\RefusedRead::class)]
#[Small]
final class RefusedReadTest extends TestCase
{
    public function testMessageKeepsTheIdentityAndOriginalPredicate(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $read = new H\RefusedRead(new H\OpenHandler(new Name('h')), H\ScanDirection::First, where: $row);
        self::assertSame($row, $read->where);
        self::assertSame('h', $read->handler->name->value);
        self::assertSame('A HANDLER read retains a refused operand.', $read->message());
    }

    public function testMessageRequiresAnIndexModeWithoutAnIndex(): void
    {
        $this->expectExceptionMessage('An indexed handler read names its index.');
        new H\RefusedRead(new H\OpenHandler(new Name('h')), H\IndexDirection::First, where: new Row([new NumberLiteral('1'), new NumberLiteral('2')]));
    }

    public function testMessageRequiresKeyValuesOnAScan(): void
    {
        $this->expectExceptionMessage('A handler key comparison has at least one value; other reads have none.');
        new H\RefusedRead(new H\OpenHandler(new Name('h')), H\ScanDirection::First, values: [new Row([new NumberLiteral('1'), new NumberLiteral('2')])]);
    }

    public function testMessageRequiresAnOrdinaryReadWithoutARetainedCondition(): void
    {
        $this->expectExceptionMessage('A refused handler read retains a non-scalar operand or a stored function.');
        new H\RefusedRead(new H\OpenHandler(new Name('h')), H\KeyComparison::Equal, new Name('i'), [new NumberLiteral('1')]);
    }

    public function testMessageRetainsStoredCallsAndLimitByIdentity(): void
    {
        $call = new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new Name('f'));
        $limit = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new NumberLiteral('2'));
        $read = new H\RefusedRead(new H\OpenHandler(new Name('h')), H\ScanDirection::Next, where: $call, limit: $limit);
        self::assertSame($call, $read->where);
        self::assertSame($limit, $read->limit);
        self::assertSame('A HANDLER read retains a refused operand.', $read->message());
    }
}
