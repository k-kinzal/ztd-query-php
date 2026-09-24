<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Statement\Configuration\Show\ShowAllSettingsStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(ShowAllSettingsStatement::class)]
#[Medium]
final class ShowAllSettingsStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW ALL');
        self::assertInstanceOf(ShowAllSettingsStatement::class, $statement);
        self::assertSame(StatementKind::Show, $statement->withOrigin($statement->origin)->kind);
        self::assertSame('SHOW ALL', $statement->toString());
    }

    public function testResultColumnsDeclareNameSettingAndDescription(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW ALL');
        self::assertInstanceOf(ShowAllSettingsStatement::class, $statement);
        $columns = $statement->resultColumns();
        self::assertSame(['name', 'setting', 'description'], array_map(static fn (OutputColumn $column): ?string => $column->name, $columns));
        self::assertSame(Nullability::MaybeNull, $columns[2]->expression->nullability);
    }

    public function testRequiresPostgreSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW ALL');
        $this->expectException(InvalidStructure::class);
        new ShowAllSettingsStatement(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::Sqlite));
    }
}
