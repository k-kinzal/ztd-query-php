<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\ExcludeForeignTables;
use SqlSemantics\Model\Statement\Definition\PostgreSql\ImportForeignSchemaStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\ForeignOperands::class)]
#[Medium]
final class ForeignOperandsTest extends TestCase
{
    public function testRelationRetainsRemoteQualificationAndDescendantScope(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('IMPORT FOREIGN SCHEMA ext EXCEPT (ONLY (app.users), logs*) FROM SERVER remote INTO app');
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $statement);
        self::assertInstanceOf(ExcludeForeignTables::class, $statement->selection);
        self::assertSame(['app', 'users'], $statement->selection->tables[0]->name->parts);
        self::assertFalse($statement->selection->tables[0]->includeDescendants);
        self::assertSame(['logs'], $statement->selection->tables[1]->name->parts);
        self::assertTrue($statement->selection->tables[1]->includeDescendants);
    }

    #[TestWith(['app.users[1]'])]
    #[TestWith(['app.*'])]
    #[TestWith(['a.b.c.d'])]
    public function testRelationDiagnosesNonRelationNameForms(string $name): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('relation name');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('IMPORT FOREIGN SCHEMA ext LIMIT TO (' . $name . ') FROM SERVER remote INTO app');
    }

    public function testOptionPreservesTheLiteralSpellingAndItsIdentifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("IMPORT FOREIGN SCHEMA ext FROM SERVER remote INTO app OPTIONS (\"Case\" E'value')");
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $statement);
        self::assertSame('Case', $statement->options[0]->name);
        self::assertSame("E'value'", $statement->options[0]->value->text);
    }

}
