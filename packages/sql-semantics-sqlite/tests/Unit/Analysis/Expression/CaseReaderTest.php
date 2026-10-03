<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Expression;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Analysis\Expression\CaseReader;
use SqlSemantics\Platform\Sqlite\Analysis\ExpressionReader;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Expression\Conditional\SqliteSearchedCase;
use SqlSemantics\Statement\Expression\Conditional\SqliteSimpleCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(CaseReader::class)]
#[Medium]
final class CaseReaderTest extends TestCase
{
    public function testReadLeavesEnclosingOperatorsToTheirOwnReader(): void
    {
        $source = Tree::outer((new SqliteParser())->parse('SELECT (CASE WHEN 1 THEN 2 END) + 3'), ['expr'])[0];
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        self::assertNull((new CaseReader())->read($source, $scope, new ExpressionReader()));
    }

    public function testArmsPreservesOrderAndNestedCaseBoundaries(): void
    {
        $sql = 'SELECT CASE WHEN 1 THEN CASE 2 WHEN 2 THEN 3 END WHEN 4 THEN 5 END';
        $source = Tree::outer((new SqliteParser())->parse($sql), ['case_exprlist'])[0];
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $arms = (new CaseReader())->arms($source, $scope, new ExpressionReader());
        self::assertCount(2, $arms);
        self::assertSame('1', $arms[0]->test->toString());
        self::assertInstanceOf(SqliteSimpleCase::class, $arms[0]->result);
        self::assertSame('4', $arms[1]->test->toString());
        self::assertSame('5', $arms[1]->result->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerChoices(): iterable
    {
        foreach (['NULL', '0', '1', '-1', "'text'", "'1english'", "X'31'", '1.5'] as $test) {
            foreach (['NULL', '4', "'chosen'", "X'FF'"] as $result) {
                foreach (['', ' ELSE NULL', " ELSE 'fallback'"] as $fallback) {
                    $searched = 'CASE WHEN ' . $test . ' THEN ' . $result . $fallback . ' END';
                    yield $searched => [$searched];
                    $simple = 'CASE ' . $test . ' WHEN 1 THEN ' . $result . ' WHEN NULL THEN 8' . $fallback . ' END';
                    yield $simple => [$simple];
                }
            }
        }
    }

    #[DataProvider('providerChoices')]
    public function testReadPreservesDatabaseValuesStorageClassesAndOutputNames(string $expression): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze('SELECT ' . $expression);
        self::assertInstanceOf(Select::class, $operation);
        $pdo = new PDO('sqlite::memory:');
        $original = $pdo->query('SELECT ' . $expression);
        $rebuilt = $pdo->query($operation->toString());
        self::assertInstanceOf(PDOStatement::class, $original);
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        self::assertSame($original->fetchAll(PDO::FETCH_ASSOC), $rebuilt->fetchAll(PDO::FETCH_ASSOC));
        $domain = $pdo->query('SELECT typeof(' . $expression . '), typeof(' . $operation->fields()->items[0]->expression->toString() . ')');
        self::assertInstanceOf(PDOStatement::class, $domain);
        $types = $domain->fetch(PDO::FETCH_NUM);
        self::assertIsArray($types);
        self::assertSame($types[0], $types[1]);
        $graph = new SemanticGraph();
        self::assertTrue($graph->isSemanticOperation($operation));
        self::assertSame($graph->fingerprint($operation), $graph->fingerprint($semantics->analyze($operation->toString())));
    }

    #[TestWith(["CASE 'a' COLLATE nocase WHEN 'A' THEN 1 ELSE 0 END", 1])]
    #[TestWith(["CASE 'a' COLLATE binary WHEN 'A' COLLATE nocase THEN 1 ELSE 0 END", 0])]
    #[TestWith(['CASE WHEN 0 THEN 1 ELSE CASE 1 WHEN 1 THEN 9 END END', 9])]
    #[TestWith(['(CASE WHEN 1 THEN 2 ELSE 1 / 0 END) + 3', 5])]
    #[TestWith(['CASE NULL WHEN NULL THEN 1 ELSE 8 END', 8])]
    #[TestWith(["+(CASE WHEN 1 THEN 'word' END) COLLATE nocase", 'word'])]
    public function testReadPreservesComparisonAndNestedExpressionBoundaries(string $expression, int|string $expected): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT ' . $expression);
        $result = (new PDO('sqlite::memory:'))->query($operation->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame($expected, $result->fetchColumn());
    }

    public function testReadKeepsMissingReferencesEvenInUnvisitedBranches(): void
    {
        $sql = 'SELECT CASE WHEN 0 THEN missing ELSE 1 END';
        $operation = (new Semantics(Dialect::Sqlite))->analyze($sql, []);
        self::assertInstanceOf(Select::class, $operation);
        $expression = $operation->fields()->items[0]->expression;
        self::assertInstanceOf(SqliteSearchedCase::class, $expression);
        self::assertSame(Invalid::MissingColumn, $expression->type());
        self::assertSame('missing', $expression->references()[0]->name->value);
        $this->expectException(PDOException::class);
        (new PDO('sqlite::memory:'))->query($operation->toString());
    }
}
