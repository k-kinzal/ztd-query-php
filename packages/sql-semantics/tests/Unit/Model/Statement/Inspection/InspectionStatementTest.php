<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\MetadataColumn;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowDatabasesStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InspectionStatement::class)]
#[Medium]
final class InspectionStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', true])]
    #[TestWith(['mysql-5.7.44', true])]
    #[TestWith(['mysql-8.0.44', false])]
    #[TestWith(['mysql-8.4.7', false])]
    public function testLegacyReleaseIdentifiesGrammarsBefore8(string $version, bool $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('SHOW COLLATION');
        self::assertInstanceOf(InspectionStatement::class, $statement);
        self::assertSame($expected, $statement->legacyRelease());
        self::assertSame(StatementKind::Show, $statement->kind);
        self::assertCount($expected ? 6 : 7, $statement->resultColumns());
    }

    public function testResultColumnsCarryTheProducingStatementIdentityAndConsecutivePositions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW OPEN TABLES');
        self::assertInstanceOf(InspectionStatement::class, $statement);
        self::assertSame([0, 1, 2, 3], array_column($statement->resultColumns(), 'ordinal'));
        self::assertSame(['Database', 'Table', 'In_use', 'Name_locked'], array_column($statement->resultColumns(), 'name'));
        $column = $statement->resultColumns()[2]->expression;
        self::assertInstanceOf(MetadataColumn::class, $column);
        self::assertSame($statement->scopeId, $column->scopeId);
        self::assertSame('bigint', $column->type->name);
    }

    public function testRejectsAnOriginFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new ShowDatabasesStatement(new Origin('s0', $statement->source, Dialect::PostgreSql));
    }
}
