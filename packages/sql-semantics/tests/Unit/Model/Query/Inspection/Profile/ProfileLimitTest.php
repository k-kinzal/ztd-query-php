<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Inspection\Profile\ProfileLimit;
use SqlSemantics\Model\Statement\Inspection\Server\ShowProfileStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProfileLimit::class)]
#[Medium]
final class ProfileLimitTest extends TestCase
{
    public function testCommaWindowListsTheOffsetBeforeTheCount(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROFILE LIMIT 2, 5');
        self::assertInstanceOf(ShowProfileStatement::class, $statement);
        self::assertNotNull($statement->limit);
        self::assertSame('5', $statement->limit->count->spelling());
        self::assertSame('2', $statement->limit->offset?->spelling());
        self::assertSame('SHOW PROFILE LIMIT 5 OFFSET 2', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testOffsetIsOptional(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROFILE LIMIT ?');
        self::assertInstanceOf(ShowProfileStatement::class, $statement);
        self::assertNotNull($statement->limit);
        self::assertSame('?', $statement->limit->count->spelling());
        self::assertNull($statement->limit->offset);
    }

    public function testRejectsOperandsFromAnotherDialect(): void
    {
        $select = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $select);
        $this->expectException(InvalidStructure::class);
        new ProfileLimit($select->outputs[0]->expression);
    }
}
