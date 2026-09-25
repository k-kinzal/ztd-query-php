<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Definition\DropTableTriggerStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropBehavior::class)]
#[Medium]
final class DropBehaviorTest extends TestCase
{
    public function testRepresentsEveryDependentObjectPolicy(): void
    {
        self::assertSame(['', 'RESTRICT', 'CASCADE'], array_column(DropBehavior::cases(), 'value'));
    }

    #[TestWith(['DROP TRIGGER tr ON t CASCADE', DropBehavior::Cascade, 'DROP TRIGGER "tr" ON "t" CASCADE'])]
    #[TestWith(['DROP TRIGGER tr ON t RESTRICT', DropBehavior::Restrict, 'DROP TRIGGER "tr" ON "t" RESTRICT'])]
    #[TestWith(['DROP TRIGGER tr ON t', DropBehavior::Default, 'DROP TRIGGER "tr" ON "t"'])]
    public function testBindsTheDeclaredBehaviorAndWritesItBack(string $sql, DropBehavior $behavior, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql, strict: false);
        self::assertInstanceOf(DropTableTriggerStatement::class, $statement);
        self::assertSame($behavior, $statement->behavior);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
