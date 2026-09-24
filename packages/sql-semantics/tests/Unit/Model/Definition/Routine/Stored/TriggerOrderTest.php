<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Stored\TriggerOrder;
use SqlSemantics\Model\Definition\Routine\Stored\TriggerOrdering;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateTriggerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TriggerOrder::class)]
#[Medium]
final class TriggerOrderTest extends TestCase
{
    public function testReadsTheAnchorTrigger(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind("CREATE TRIGGER tr AFTER UPDATE ON t FOR EACH ROW FOLLOWS 'audit' DO 1");
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertNotNull($statement->order);
        self::assertSame(TriggerOrdering::Follows, $statement->order->position);
        self::assertSame('audit', $statement->order->trigger);
    }

    public function testRejectsAnUnnamedAnchor(): void
    {
        $this->expectException(InvalidStructure::class);
        new TriggerOrder(TriggerOrdering::Precedes, '');
    }
}
