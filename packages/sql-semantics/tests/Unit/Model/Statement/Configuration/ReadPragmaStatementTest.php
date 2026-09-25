<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\ReadPragmaStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReadPragmaStatement::class)]
#[Medium]
final class ReadPragmaStatementTest extends TestCase
{
    public function testBindsAQualifiedPragmaNameWithoutAssignments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('PRAGMA main.cache_size');
        self::assertInstanceOf(ReadPragmaStatement::class, $statement);
        self::assertSame(['main', 'cache_size'], $statement->name->parts);
        self::assertSame(StatementKind::Pragma, $statement->kind);
        self::assertSame([], $statement->assignments());
        self::assertSame('PRAGMA "main"."cache_size"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginPreservesTheNameAndReplacesProvenance(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('PRAGMA cache_size');
        self::assertInstanceOf(ReadPragmaStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->name, $copy->name);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }
}
