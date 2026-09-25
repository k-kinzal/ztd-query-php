<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Definition\Routine\Stored\OneTimeSchedule;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateEventStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(OneTimeSchedule::class)]
#[Medium]
final class OneTimeScheduleTest extends TestCase
{
    public function testParenthesizesAComputedTime(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP + INTERVAL 1 HOUR DO DO 1');
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        self::assertInstanceOf(OneTimeSchedule::class, $statement->schedule);
        self::assertSame('CREATE EVENT `e` ON SCHEDULE AT(DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 HOUR)) DO DO 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnotherDialectTime(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT now()');
        self::assertInstanceOf(BoundQuery::class, $query);
        $this->expectException(InvalidStructure::class);
        new OneTimeSchedule($query->resultColumns()[0]->expression);
    }
}
