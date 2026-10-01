<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\SchemaChangeReader;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\DropColumn;
use SqlSemantics\Statement\Schema\DropIndex;
use SqlSemantics\Statement\Schema\DropTable;
use SqlSemantics\Statement\Schema\DropTrigger;
use SqlSemantics\Statement\Schema\DropView;
use SqlSemantics\Statement\Schema\RenameColumn;
use SqlSemantics\Statement\Schema\RenameTable;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(SchemaChangeReader::class)]
#[Medium]
final class SchemaChangeReaderTest extends TestCase
{
    #[TestWith(['DROP TABLE users', DropTable::class])]
    #[TestWith(['DROP TABLE IF EXISTS main.users', DropTable::class])]
    #[TestWith(['DROP VIEW main.users', DropView::class])]
    #[TestWith(['DROP VIEW IF EXISTS users', DropView::class])]
    #[TestWith(['DROP INDEX user_id', DropIndex::class])]
    #[TestWith(['DROP INDEX IF EXISTS main.user_id', DropIndex::class])]
    #[TestWith(['DROP TRIGGER user_log', DropTrigger::class])]
    #[TestWith(['DROP TRIGGER IF EXISTS main.user_log', DropTrigger::class])]
    #[TestWith(['ALTER TABLE main.users RENAME TO people', RenameTable::class])]
    #[TestWith(['ALTER TABLE users RENAME COLUMN id TO key', RenameColumn::class])]
    #[TestWith(['ALTER TABLE users RENAME id TO key', RenameColumn::class])]
    #[TestWith(['ALTER TABLE users DROP COLUMN id', DropColumn::class])]
    #[TestWith(['ALTER TABLE users DROP id', DropColumn::class])]
    public function testReadStructuresRemovalAndRenameTargets(string $sql, string $class): void
    {
        $parser = new SqliteParser();
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $reader = new SchemaChangeReader();
        $operation = $reader->read($parser->parse($sql)->find('cmd')[0], $catalog);
        self::assertSame($class, $operation::class);
        self::assertSame($sql, $operation->toString());
        self::assertTrue((new SemanticGraph())->isSemanticOperation($operation));
        $reconstructed = $reader->read($parser->parse($operation->toString())->find('cmd')[0], $catalog);
        self::assertSame((new SemanticGraph())->fingerprint($operation), (new SemanticGraph())->fingerprint($reconstructed));
    }

    public function testReadRefersToTheOriginalColumnWithoutApplyingTheRename(): void
    {
        $column = new Column(new Name('id'), new TypeDescriptor(Builtin::Integer));
        $table = new Table(new QualifiedName(new Name('users')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, $table);
        $operation = (new SchemaChangeReader())->read((new SqliteParser())->parse('ALTER TABLE users RENAME COLUMN id TO key')->find('cmd')[0], $catalog);
        self::assertInstanceOf(RenameColumn::class, $operation);
        self::assertInstanceOf(ResolvedColumn::class, $operation->column->resolution);
        self::assertSame($column, $operation->column->resolution->column);
        self::assertSame($table, $operation->column->resolution->table);
        self::assertSame('key', $operation->newName->value);
        self::assertSame([$column], $table->matchingColumns('id', Comparison::Sensitive));
        self::assertSame([], $table->matchingColumns('key', Comparison::Sensitive));
    }
}
