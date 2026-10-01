<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\ExpressionReader;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\SqliteBetween;
use SqlSemantics\Statement\Expression\SqliteBinary;
use SqlSemantics\Statement\Expression\SqliteBinaryOperator;
use SqlSemantics\Statement\Expression\SqliteInList;
use SqlSemantics\Statement\Expression\SqliteUnary;
use SqlSemantics\Statement\Expression\SqliteUnaryOperator;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(ExpressionReader::class)]
#[Medium]
final class ExpressionReaderTest extends TestCase
{
    #[TestWith(['1+2*3'])]
    #[TestWith(['(1+2)*3'])]
    #[TestWith(['NOT 1 AND 0'])]
    #[TestWith(['NULL OR 1'])]
    #[TestWith(['2 IS TRUE'])]
    #[TestWith(['2 IS (+TRUE)'])]
    #[TestWith(['1 IS DISTINCT FROM NULL'])]
    #[TestWith(['1 IS NOT DISTINCT FROM 1'])]
    #[TestWith(['1 NOTNULL'])]
    #[TestWith(['1 NOT NULL'])]
    #[TestWith(['NULL ISNULL'])]
    #[TestWith(['1 BETWEEN 0 AND 2'])]
    #[TestWith(['1 NOT BETWEEN 2 AND NULL'])]
    #[TestWith(['NULL IN ()'])]
    #[TestWith(['absent NOT IN ()'])]
    #[TestWith(['1 IN (NULL, 1, 2)'])]
    #[TestWith(['2 NOT IN (1, NULL)'])]
    #[TestWith(['-1'])]
    #[TestWith(['+2'])]
    #[TestWith(['-(+3)'])]
    #[TestWith(['-9223372036854775808'])]
    #[TestWith(['-(-9223372036854775808)'])]
    #[TestWith(['NOT 42'])]
    #[TestWith(['~ 1'])]
    #[TestWith(['+NULL'])]
    #[TestWith(['(1.0)'])]
    public function testReadReconstructsTheActualComputation(string $sql): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $expression = (new ExpressionReader())->read((new SqliteParser())->parse('SELECT ' . $sql)->find('expr')[0], $scope);
        self::assertTrue((new SemanticGraph())->containsOnlyValues($expression));
        $db = new PDO('sqlite::memory:');
        $original = $db->query('SELECT ' . $sql);
        $rebuilt = $db->query('SELECT ' . $expression->toString());
        self::assertInstanceOf(PDOStatement::class, $original);
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        self::assertSame($original->fetch(PDO::FETCH_NUM), $rebuilt->fetch(PDO::FETCH_NUM));
    }

    public function testReadKeepsThePlusOperationAndItsOriginalLookup(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main')), complete: false));
        $expression = (new ExpressionReader())->read((new SqliteParser())->parse('SELECT +foo')->find('expr')[0], $scope);
        self::assertInstanceOf(SqliteUnary::class, $expression);
        self::assertSame(SqliteUnaryOperator::Plus, $expression->operator);
        self::assertInstanceOf(ColumnReference::class, $expression->operand);
        self::assertSame($scope, $expression->operand->scope);
    }

    public function testColumnResolvesParenthesizedLookupsWithoutKeepingSyntaxNodes(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main')), complete: false));
        $reader = new ExpressionReader();
        $parser = new SqliteParser();
        $direct = $reader->column($parser->parse('SELECT bar.foo')->find('expr')[0], $scope);
        $grouped = $reader->read($parser->parse('SELECT ((bar.foo))')->find('expr')[0], $scope);
        self::assertInstanceOf(ColumnReference::class, $grouped);
        self::assertEquals($direct, $grouped);
    }

    public function testInfixKeepsTheOperandsAndNullSafeOperatorDistinct(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $source = (new SqliteParser())->parse('SELECT NULL IS NOT DISTINCT FROM NULL')->find('expr')[0];
        $expression = (new ExpressionReader())->infix($source, $scope);
        self::assertInstanceOf(SqliteBinary::class, $expression);
        self::assertSame(SqliteBinaryOperator::NotDistinctFrom, $expression->operator);
    }

    public function testPredicateDoesNotExpandBetweenIntoTwoSubjectEvaluations(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main')), complete: false));
        $source = (new SqliteParser())->parse('SELECT foo BETWEEN 1 AND 2')->find('expr')[0];
        $expression = (new ExpressionReader())->predicate($source, $scope);
        self::assertInstanceOf(SqliteBetween::class, $expression);
        self::assertCount(1, $expression->references());
        self::assertInstanceOf(ColumnReference::class, $expression->subject);
        self::assertSame($expression->subject, $expression->references()[0]);
    }

    public function testPredicateRetainsAnEmptyMembershipAsItsOwnSemanticCase(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $source = (new SqliteParser())->parse('SELECT absent IN ()')->find('expr')[0];
        $expression = (new ExpressionReader())->predicate($source, $scope);
        self::assertInstanceOf(SqliteInList::class, $expression);
        self::assertSame([], $expression->choices);
        self::assertSame([], $expression->references());
    }
}
