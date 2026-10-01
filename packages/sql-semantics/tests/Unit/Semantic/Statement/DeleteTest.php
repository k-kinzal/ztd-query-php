<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Statement;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\ModelGraph;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySql;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSql;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;
use SqlSemantics\Semantic\Expression\Binary;
use SqlSemantics\Semantic\Expression\ColumnReference;
use SqlSemantics\Semantic\Reference\ResolvedColumn;
use SqlSemantics\Semantic\Statement\Delete;
use Tests\Scenario\SemanticCases;

#[CoversClass(Delete::class)]
#[Medium]
final class DeleteTest extends TestCase
{
    #[TestWith([MySql::MySql])]
    #[TestWith([PostgreSql::PostgreSql])]
    #[TestWith([Sqlite::Sqlite])]
    public function testToStringPreservesTheResolvedTargetAndPredicate(Dialect $dialect): void
    {
        $table = SemanticCases::table($dialect);
        $semantics = new Semantics($dialect);
        $statement = $semantics->analyze('DELETE FROM bar WHERE foo = 1', [$table]);
        self::assertInstanceOf(Delete::class, $statement);
        self::assertSame($table, $statement->table->declaration);
        self::assertInstanceOf(Binary::class, $statement->where);
        self::assertInstanceOf(ColumnReference::class, $statement->where->left);
        self::assertInstanceOf(ResolvedColumn::class, $statement->where->left->binding);
        self::assertSame($table->columns[0], $statement->where->left->binding->column);
        self::assertSame('DELETE FROM bar WHERE foo = 1', $statement->toString());
        $graph = new ModelGraph();
        self::assertSame($graph->fingerprint($statement), $graph->fingerprint($semantics->analyze($statement->toString(), [$table])));
    }

    public function testWithWhereLeavesTheOriginalDeletionUnchanged(): void
    {
        $statement = (new Semantics(Sqlite::Sqlite))->analyze('DELETE FROM bar WHERE foo = 1');
        self::assertInstanceOf(Delete::class, $statement);
        $updated = $statement->withWhere(null);
        self::assertSame($statement->scope, $updated->scope);
        self::assertSame('DELETE FROM bar', $updated->toString());
        self::assertSame('DELETE FROM bar WHERE foo = 1', $statement->toString());
    }
    #[TestWith(['foo = 1'])]
    #[TestWith(['foo > 1 AND foo < 4'])]
    #[TestWith(['label IS NULL'])]
    public function testToStringDeletesTheSameRowsAsTheInput(string $predicate): void
    {
        $database = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec('CREATE TABLE bar (foo INTEGER, label TEXT)');
        $database->exec("INSERT INTO bar VALUES (1, 'one'), (2, NULL), (3, 'three'), (4, NULL)");
        $sql = 'DELETE FROM bar WHERE ' . $predicate;
        $statement = (new Semantics(Sqlite::Sqlite))->analyze($sql, [SemanticCases::table(Sqlite::Sqlite)]);
        self::assertInstanceOf(Delete::class, $statement);
        $database->beginTransaction();
        $database->exec($sql);
        $expected = $database->query('SELECT foo, label FROM bar ORDER BY foo');
        self::assertInstanceOf(PDOStatement::class, $expected);
        $expectedRows = $expected->fetchAll(PDO::FETCH_NUM);
        $database->rollBack();
        $database->exec($statement->toString());
        $actual = $database->query('SELECT foo, label FROM bar ORDER BY foo');
        self::assertInstanceOf(PDOStatement::class, $actual);
        self::assertSame($expectedRows, $actual->fetchAll(PDO::FETCH_NUM));
    }
}
