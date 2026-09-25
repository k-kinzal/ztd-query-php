<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlObject\LoadableFunctions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Server\LoadableResult;
use SqlSemantics\Model\Statement\Definition\MySql\Server\CreateLoadableFunctionStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LoadableFunctions::class)]
#[Medium]
final class LoadableFunctionsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'STRING', LoadableResult::String])]
    #[TestWith(['mysql-5.7.44', 'REAL', LoadableResult::Real])]
    #[TestWith(['mysql-8.0.44', 'DECIMAL', LoadableResult::Decimal])]
    #[TestWith(['mysql-8.4.7', 'INT', LoadableResult::Integer])]
    #[TestWith(['mysql-9.1.0', 'INTEGER', LoadableResult::Integer])]
    public function testBindReadsTheResultKindInEveryRelease(string $version, string $type, LoadableResult $returns): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("CREATE AGGREGATE FUNCTION f RETURNS {$type} SONAME 'lib''s.so'");
        self::assertInstanceOf(CreateLoadableFunctionStatement::class, $statement);
        self::assertSame(['f', $returns, "lib's.so", true, false], [$statement->name, $statement->returns, $statement->library, $statement->aggregate, $statement->ifNotExists]);
        $expected = "CREATE AGGREGATE FUNCTION `f` RETURNS {$returns->value} SONAME 'lib''s.so'";
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindReadsIfNotExists(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind("CREATE FUNCTION IF NOT EXISTS f RETURNS STRING SONAME 'u.so'");
        self::assertInstanceOf(CreateLoadableFunctionStatement::class, $statement);
        self::assertSame([false, true], [$statement->aggregate, $statement->ifNotExists]);
    }

    public function testBindReturnsNullForAnotherStatement(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $statement = (new Binder($schema))->bind('CREATE TABLESPACE ts');
        self::assertNull(LoadableFunctions::bind($statement->origin, $statement->source, new Identifiers(Dialect::MySql)));
    }

    public function testBindRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ServerDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE FUNCTION `` RETURNS REAL SONAME 'u.so'");
    }

    #[TestWith(['mysql-5.6.51', 'CREATE FUNCTION f RETURNS REAL SONAME \'x.so\'', CreateLoadableFunctionStatement::class, 'CREATE FUNCTION `f` RETURNS REAL SONAME \'x.so\''])]
    #[TestWith(['mysql-5.7.44', 'CREATE FUNCTION f RETURNS REAL SONAME \'x.so\'', CreateLoadableFunctionStatement::class, 'CREATE FUNCTION `f` RETURNS REAL SONAME \'x.so\''])]
    #[TestWith(['mysql-8.0.44', 'CREATE FUNCTION f RETURNS REAL SONAME \'x.so\'', CreateLoadableFunctionStatement::class, 'CREATE FUNCTION `f` RETURNS REAL SONAME \'x.so\''])]
    #[TestWith(['mysql-8.4.7', 'CREATE FUNCTION f RETURNS REAL SONAME \'x.so\'', CreateLoadableFunctionStatement::class, 'CREATE FUNCTION `f` RETURNS REAL SONAME \'x.so\''])]
    #[TestWith(['mysql-9.1.0', 'CREATE FUNCTION f RETURNS REAL SONAME \'x.so\'', CreateLoadableFunctionStatement::class, 'CREATE FUNCTION `f` RETURNS REAL SONAME \'x.so\''])]
    public function testBindReadsTheRealResultUnderEveryGrammar(string $version, string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
