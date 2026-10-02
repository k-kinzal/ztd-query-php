<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Schema\Definition\SqliteColumnDefinition;
use SqlSemantics\Statement\Schema\Definition\SqliteCreateTable;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\SqliteDeclaration;

#[CoversClass(SqliteCreateTable::class)]
#[Small]
final class SqliteCreateTableTest extends TestCase
{
    public function testToStringSharesTheDeclaredColumnObjectsWithTheTable(): void
    {
        $column = new SqliteColumnDefinition(new Name('foo'), new SqliteDeclaration('INTEGER'));
        $create = new SqliteCreateTable(new QualifiedName(new Name('bar')), columns: $column);
        self::assertSame('CREATE TABLE bar (foo INTEGER)', $create->toString());
        self::assertSame($column->column, $create->table->columns[0]);
        self::assertSame($column, $create->columns[0]);
        self::assertSame('main', $create->table->name->schema?->value);
        self::assertTrue((new SemanticGraph())->isSemanticOperation($create));
    }

    public function testDeclaredTablesKeepsConditionalCreationIndependentOfExistingState(): void
    {
        $column = new SqliteColumnDefinition(new Name('foo'), new SqliteDeclaration('INTEGER'));
        $create = new SqliteCreateTable(new QualifiedName(new Name('bar')), ifNotExists: true, columns: $column);
        self::assertSame([$create->table], $create->declaredTables());
    }

    public function testToStringDescribesTemporaryConditionalCreationInItsOwnNamespace(): void
    {
        $column = new SqliteColumnDefinition(new Name('foo'), new SqliteDeclaration('ANY', strict: true));
        $create = new SqliteCreateTable(new QualifiedName(new Name('bar')), true, true, true, new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $column);
        self::assertSame('CREATE TEMP TABLE IF NOT EXISTS bar (foo ANY) STRICT', $create->toString());
        self::assertSame('temp', $create->table->name->schema?->value);
    }
}
