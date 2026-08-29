<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\LexicalGrammar;
use SqlFaker\MySqlFragmentProvider;

#[CoversClass(MySqlFragmentProvider::class)]
final class MySqlFragmentProviderTest extends TestCase
{
    public function testExpr(): void
    {
        $faker = Factory::create();
        $provider = new MySqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->expr(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testSimpleExpr(): void
    {
        $faker = Factory::create();
        $provider = new MySqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->simpleExpr(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testLiteral(): void
    {
        $faker = Factory::create();
        $provider = new MySqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->literal(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testPredicate(): void
    {
        $faker = Factory::create();
        $provider = new MySqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->predicate(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testWhereClause(): void
    {
        $faker = Factory::create();
        $provider = new MySqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->whereClause(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bWHERE\b/i', $result);
    }

    public function testOrderClause(): void
    {
        $faker = Factory::create();
        $provider = new MySqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->orderClause(maxDepth: 3);

        self::assertSame(
            ['ORDER_SYM', 'BY'],
            array_slice((new LexicalGrammar($faker, 'mysql-8.4.7'))->tokenize($result), 0, 2),
        );
    }

    public function testLimitClause(): void
    {
        $faker = Factory::create();
        $provider = new MySqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->limitClause(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bLIMIT\b/i', $result);
    }

    public function testTableReference(): void
    {
        $faker = Factory::create();
        $provider = new MySqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->tableReference(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testJoinedTable(): void
    {
        $faker = Factory::create();
        $provider = new MySqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->joinedTable(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bJOIN\b/i', $result);
    }

    public function testTableIdent(): void
    {
        $faker = Factory::create();
        $provider = new MySqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->tableIdent(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testSubquery(): void
    {
        $faker = Factory::create();
        $provider = new MySqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->subquery(maxDepth: 3);

        self::assertStringContainsString('(', $result);
        self::assertStringContainsString(')', $result);
    }

    public function testWithClause(): void
    {
        $faker = Factory::create();
        $provider = new MySqlFragmentProvider($faker);
        $faker->seed(12345);

        $result = $provider->withClause(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bWITH\b/i', $result);
    }
}
