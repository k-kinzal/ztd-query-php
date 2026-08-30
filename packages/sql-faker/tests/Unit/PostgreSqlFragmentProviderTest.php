<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlFaker\PostgreSql\Lexical\LexicalGrammar;
use SqlFaker\PostgreSqlFragmentProvider;

#[Medium]
#[CoversClass(PostgreSqlFragmentProvider::class)]
final class PostgreSqlFragmentProviderTest extends TestCase
{
    public function testExpr(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->expr(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testSimpleExpr(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->simpleExpr(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testLiteral(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->literal(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testWhereClause(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlFragmentProvider($faker);
        $faker->seed(0);

        $result = $provider->whereClause(maxDepth: 1);

        self::assertSame('', $result);
    }

    public function testSortClause(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->sortClause(maxDepth: 3);

        self::assertSame(['ORDER', 'BY'], array_slice((new LexicalGrammar($faker, 'pg-17.2'))->tokenize($result), 0, 2));
    }

    public function testSelectLimit(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->selectLimit(maxDepth: 3);

        self::assertMatchesRegularExpression('/LIMIT|OFFSET|FETCH/', $result);
    }

    public function testTableRef(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->tableRef(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testJoinedTable(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->joinedTable(maxDepth: 3);

        self::assertStringContainsString('JOIN', $result);
    }

    public function testQualifiedName(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->qualifiedName(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testSubquery(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->subquery(maxDepth: 3);

        self::assertStringContainsString('(', $result);
        self::assertStringContainsString(')', $result);
    }

    public function testWithClause(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->withClause(maxDepth: 3);

        self::assertStringContainsString('WITH', $result);
    }

}
