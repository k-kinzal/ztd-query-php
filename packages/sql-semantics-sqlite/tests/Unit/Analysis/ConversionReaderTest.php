<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Analysis\ConversionReader;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Declaration\Affinity;
use SqlSemantics\Statement\Expression\Conversion\SqliteCast;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(ConversionReader::class)]
#[Medium]
final class ConversionReaderTest extends TestCase
{
    public function testReadLeavesOrdinaryExpressionsToTheirOwnReaders(): void
    {
        $tree = (new SqliteParser())->parse('SELECT 1 + 2');
        $source = Tree::outer($tree, ['expr'])[0];
        $scope = new \SqlSemantics\Statement\Relation\Scope(new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main'))));
        self::assertNull((new ConversionReader())->read($source, $scope, new \SqlSemantics\Platform\Sqlite\Analysis\ExpressionReader()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCasts(): iterable
    {
        foreach (['NULL', '12', '-12', '1.5', "'12.5suffix'", "'text'", "X'31322E35'", "''"] as $operand) {
            foreach (['', 'INTEGER', 'REAL', 'BLOB', 'TEXT', 'NUMERIC', 'FLOATING POINT', 'VARCHAR(4)', 'CHARBLOB', '"TEXT" "INT"', '[BLOB] A', 'A /*INT*/ B'] as $target) {
                $expression = 'CAST(' . $operand . ' AS ' . $target . ')';
                yield $expression => [$expression];
            }
        }
    }

    #[DataProvider('providerCasts')]
    public function testCastPreservesDatabaseValuesAndOutputNames(string $expression): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $sql = 'SELECT ' . $expression;
        $operation = $semantics->analyze($sql);
        self::assertInstanceOf(Select::class, $operation);
        self::assertInstanceOf(SqliteCast::class, $operation->fields()->items[0]->expression);
        $pdo = new PDO('sqlite::memory:');
        $original = $pdo->query($sql);
        $rebuilt = $pdo->query($operation->toString());
        self::assertInstanceOf(PDOStatement::class, $original);
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        self::assertSame($original->fetchAll(PDO::FETCH_ASSOC), $rebuilt->fetchAll(PDO::FETCH_ASSOC));
        $graph = new SemanticGraph();
        self::assertTrue($graph->isSemanticOperation($operation));
        self::assertSame($graph->fingerprint($operation), $graph->fingerprint($semantics->analyze($operation->toString())));
    }

    #[TestWith(['"TEXT" "INT"', 'TEXT', Affinity::Text])]
    #[TestWith(['[BLOB] A', 'BLOB', Affinity::Blob])]
    #[TestWith(['A /*INT*/ B', 'A /*INT*/ B', Affinity::Integer])]
    #[TestWith(['', '', Affinity::Numeric])]
    public function testTargetUsesCastRulesRatherThanColumnDeclarationRules(string $source, string $name, Affinity $affinity): void
    {
        $tree = (new SqliteParser())->parse('SELECT CAST(1 AS ' . $source . ')');
        $target = (new ConversionReader())->target(Tree::outer($tree, ['typetoken'])[0]);
        self::assertSame($name, $target->name);
        self::assertSame($affinity, $target->affinity);
    }

    #[TestWith(["'a' COLLATE nocase = 'A'", 1])]
    #[TestWith(["('a' COLLATE binary) = ('A' COLLATE nocase)", 0])]
    #[TestWith(["('a' COLLATE nocase) = ('A' COLLATE binary)", 1])]
    #[TestWith(["('x ' COLLATE rtrim) = 'x'", 1])]
    #[TestWith(["('a' || '') COLLATE nocase IN ('A')", 1])]
    #[TestWith(["(('a' COLLATE binary) COLLATE nocase) = 'A'", 1])]
    #[TestWith(["CAST('12.5' AS INTEGER) + 1", 13])]
    public function testCollatedPreservesComparisonPrecedenceAndNestedConversions(string $expression, int $expected): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $sql = 'SELECT ' . $expression;
        $operation = $semantics->analyze($sql);
        $pdo = new PDO('sqlite::memory:');
        $original = $pdo->query($sql);
        $rebuilt = $pdo->query($operation->toString());
        self::assertInstanceOf(PDOStatement::class, $original);
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        self::assertSame($expected, $original->fetchColumn());
        self::assertSame($expected, $rebuilt->fetchColumn());
        self::assertSame((new SemanticGraph())->fingerprint($operation), (new SemanticGraph())->fingerprint($semantics->analyze($operation->toString())));
    }
}
