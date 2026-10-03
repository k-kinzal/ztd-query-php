<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Analysis\DatabaseReader;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Connection\AttachDatabase;
use SqlSemantics\Statement\Connection\DetachDatabase;
use SqlSemantics\Statement\Expression\SqliteText;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Maintenance\Vacuum;
use SqlSemantics\Statement\Maintenance\VacuumInto;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(DatabaseReader::class)]
#[Medium]
final class DatabaseReaderTest extends TestCase
{
    #[TestWith(['VACUUM', 'main'])]
    #[TestWith(['VACUUM temp', 'temp'])]
    #[TestWith(['VACUUM "other.db"', 'other.db'])]
    public function testVacuumIdentifiesTheDatabaseBeingRebuilt(string $sql, string $schema): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $source = Tree::outer((new SqliteParser())->parse($sql), ['cmd'])[0];
        $operation = (new DatabaseReader())->vacuum($source, $catalog);
        self::assertInstanceOf(Vacuum::class, $operation);
        self::assertSame($schema, $operation->schema->value);
        self::assertSame((new SemanticGraph())->fingerprint($operation), (new SemanticGraph())->fingerprint((new Semantics(Dialect::Sqlite))->analyze($operation->toString())));
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testVacuumCopyPreservesTheDatabaseContents(bool $reconstructed): void
    {
        $path = tempnam(sys_get_temp_dir(), 'semantic-vacuum-');
        self::assertNotFalse($path);
        try {
            $pdo = new PDO('sqlite::memory:');
            $pdo->exec("CREATE TABLE example(id INTEGER PRIMARY KEY, label TEXT); INSERT INTO example VALUES(7, 'value')");
            $sql = 'VACUUM main INTO ' . $pdo->quote($path);
            $operation = (new Semantics(Dialect::Sqlite))->analyze($sql);
            self::assertInstanceOf(VacuumInto::class, $operation);
            self::assertSame([], $operation->scope->tables);
            self::assertTrue((new SemanticGraph())->isSemanticOperation($operation));
            $pdo->exec($reconstructed ? $operation->toString() : $sql);
            $copy = new PDO('sqlite:' . $path);
            $rows = $copy->query('SELECT id, label FROM example');
            self::assertInstanceOf(PDOStatement::class, $rows);
            self::assertSame([[7, 'value']], $rows->fetchAll(PDO::FETCH_NUM));
            $source = $pdo->query('SELECT id, label FROM example');
            self::assertInstanceOf(PDOStatement::class, $source);
            self::assertSame([[7, 'value']], $source->fetchAll(PDO::FETCH_NUM));
        } finally {
            unlink($path);
        }
    }

    #[TestWith(["ATTACH ':memory:' AS sample", 'sample'])]
    #[TestWith(["ATTACH DATABASE ':memory:' AS ((sample))", 'sample'])]
    #[TestWith(["ATTACH ':memory:' AS TRUE", 'TRUE'])]
    #[TestWith(["ATTACH ':memory:' AS 'extra' || 'db'", 'extradb'])]
    public function testAttachmentRetainsRootIdentifierTextAndRuntimeExpressions(string $sql, string $schema): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze($sql);
        self::assertInstanceOf(AttachDatabase::class, $operation);
        self::assertSame([], $operation->scope->tables);
        $original = new PDO('sqlite::memory:');
        $rebuilt = new PDO('sqlite::memory:');
        $original->exec($sql);
        $rebuilt->exec($operation->toString());
        $left = $original->query('PRAGMA database_list');
        $right = $rebuilt->query('PRAGMA database_list');
        self::assertInstanceOf(PDOStatement::class, $left);
        self::assertInstanceOf(PDOStatement::class, $right);
        self::assertSame(['main', $schema], array_column($left->fetchAll(PDO::FETCH_ASSOC), 'name'));
        self::assertSame(['main', $schema], array_column($right->fetchAll(PDO::FETCH_ASSOC), 'name'));
        self::assertSame((new SemanticGraph())->fingerprint($operation), (new SemanticGraph())->fingerprint($semantics->analyze($operation->toString())));
    }

    public function testAttachmentExpressionConvertsOnlyTheRootIdentifier(): void
    {
        $parser = new SqliteParser();
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $root = Tree::outer($parser->parse('DETACH ((schema_name))'), ['expr'])[0];
        $text = (new DatabaseReader())->attachmentExpression($root, $scope);
        self::assertInstanceOf(SqliteText::class, $text);
        self::assertSame('schema_name', $text->value->value);
        $nested = Tree::outer($parser->parse('DETACH schema_name || other_name'), ['expr'])[0];
        $expression = (new DatabaseReader())->attachmentExpression($nested, $scope);
        self::assertSame(Invalid::MissingColumn, $expression->type());
        self::assertCount(2, $expression->references());
        self::assertSame($scope, $expression->references()[0]->scope);
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('no such column: schema_name');
        (new PDO('sqlite::memory:'))->exec('DETACH schema_name || other_name');
    }

    public function testAttachmentRetainsTheOptionalKeyWithoutEvaluatingIt(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("ATTACH ':memory:' AS sample KEY secret");
        self::assertInstanceOf(AttachDatabase::class, $operation);
        self::assertInstanceOf(SqliteText::class, $operation->key);
        self::assertSame('secret', $operation->key->value->value);
        self::assertSame("ATTACH DATABASE ':memory:' AS 'sample' KEY 'secret'", $operation->toString());
    }

    public function testAttachmentDetachesOnlyWhenTheDatabaseExecutesTheRequest(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $attach = $semantics->analyze("ATTACH ':memory:' AS sample");
        $detach = $semantics->analyze('DETACH ((sample))', [$attach]);
        self::assertInstanceOf(DetachDatabase::class, $detach);
        self::assertSame([], $detach->scope->catalog->tables);
        self::assertSame(['temp', 'main'], array_column($detach->scope->catalog->searchPath->schemas, 'value'));
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec($attach->toString());
        $pdo->exec($detach->toString());
        $databases = $pdo->query('PRAGMA database_list');
        self::assertInstanceOf(PDOStatement::class, $databases);
        self::assertSame(['main'], array_column($databases->fetchAll(PDO::FETCH_ASSOC), 'name'));
    }
    public function testVacuumSkipsDestinationResolutionForTheTemporaryDatabase(): void
    {
        $sql = 'VACUUM temp INTO nonexistent_column';
        $operation = (new Semantics(Dialect::Sqlite))->analyze($sql);
        self::assertInstanceOf(VacuumInto::class, $operation);
        self::assertTrue($operation->isNoOp());
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TEMP TABLE example(value INTEGER); INSERT INTO example VALUES (7)');
        $pdo->exec($sql);
        $pdo->exec($operation->toString());
        $rows = $pdo->query('SELECT value FROM example');
        self::assertInstanceOf(PDOStatement::class, $rows);
        self::assertSame([7], $rows->fetchAll(PDO::FETCH_COLUMN));
    }
}
