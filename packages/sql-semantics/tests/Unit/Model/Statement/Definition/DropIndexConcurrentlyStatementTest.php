<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\DropIndexConcurrentlyStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropIndexConcurrentlyStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DropIndexConcurrentlyStatementTest extends TestCase
{
    public function testWithOriginPreservesRequiredOperandsAndSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DROP INDEX CONCURRENTLY IF EXISTS ix', strict: false);
        self::assertInstanceOf(DropIndexConcurrentlyStatement::class, $statement);
        self::assertSame(['ix'], $statement->name->parts);
        self::assertTrue($statement->ifExists);
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('new-scope', $statement->source, Dialect::PostgreSql));
        self::assertSame('new-scope', $changed->scopeId);
        self::assertNotSame($statement, $changed);
        self::assertSame('DROP INDEX CONCURRENTLY IF EXISTS "ix"', $changed->toString());
        self::assertSame($changed->toString(), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($changed->toString(), strict: false)));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['DROP INDEX CONCURRENTLY a, b'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['DROP INDEX CONCURRENTLY a CASCADE'])]
    public function testRejectsMultipleOrCascadingTargets(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $binder->bind($sql);
    }
}
