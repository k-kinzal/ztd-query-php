<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Inspection\Server\ShowProfilesStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowProfilesStatement::class)]
#[Medium]
final class ShowProfilesStatementTest extends TestCase
{
    public function testResultColumnsSummarizeTheProfiledStatements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW /* recent */ PROFILES');
        self::assertInstanceOf(ShowProfilesStatement::class, $statement);
        self::assertSame(['Query_ID', 'Duration', 'Query'], array_column($statement->resultColumns(), 'name'));
        self::assertSame(['bigint', 'numeric', 'varchar'], array_map(static fn (\SqlSemantics\Model\OutputColumn $column): string => $column->expression->type->name, $statement->resultColumns()));
        self::assertSame('SHOW PROFILES', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginProducesAnEquivalentRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROFILES');
        self::assertInstanceOf(ShowProfilesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testRejectsAnOriginFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new ShowProfilesStatement(new Origin('s0', $statement->source, Dialect::PostgreSql));
    }
}
