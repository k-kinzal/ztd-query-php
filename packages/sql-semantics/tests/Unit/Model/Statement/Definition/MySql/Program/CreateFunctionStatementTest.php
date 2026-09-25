<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain;
use SqlSemantics\Model\Definition\Routine\Stored\ExternalRoutineCode;
use SqlSemantics\Model\Definition\Routine\Stored\RoutineCharacteristics;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CreateFunctionStatement::class)]
#[Medium]
final class CreateFunctionStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindsTheSameFunctionOnEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('CREATE FUNCTION area(w INT, h INT) RETURNS BIGINT DETERMINISTIC NO SQL BEGIN DECLARE r BIGINT DEFAULT w * h; RETURN r; END');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertSame(['w', 'h'], array_column($statement->parameters, 'name'));
        self::assertSame('bigint', $statement->returns->type->name);
        self::assertSame('CREATE FUNCTION `area`(`w` integer, `h` integer) RETURNS bigint DETERMINISTIC NO SQL BEGIN DECLARE `r` bigint DEFAULT (`w` * `h`); RETURN `r`; END', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindsAJavaScriptBody(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('CREATE FUNCTION IF NOT EXISTS f(a INT) RETURNS INT LANGUAGE JAVASCRIPT AS $js$ return a * 2 $js$');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertInstanceOf(ExternalRoutineCode::class, $statement->body);
        self::assertSame(' return a * 2 ', $statement->body->code);
        self::assertSame("CREATE FUNCTION IF NOT EXISTS `f`(`a` integer) RETURNS integer LANGUAGE `JAVASCRIPT` AS ' return a * 2 '", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithOriginPreservesTheDefinition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f() RETURNS INT RETURN 1');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithNameReplacesOnlyTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f() RETURNS INT RETURN 1');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertSame('CREATE FUNCTION `g`() RETURNS integer RETURN 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withName(new QualifiedName(['g']))));
    }

    public function testWithParametersReordersParameters(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f(a INT, b INT) RETURNS INT RETURN a - b');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertSame('CREATE FUNCTION `f`(`b` integer, `a` integer) RETURNS integer RETURN(`a` - `b`)', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withParameters(array_reverse($statement->parameters))));
    }

    public function testWithReturnsReplacesTheReturnDomain(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f() RETURNS INT RETURN 1');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertSame('CREATE FUNCTION `f`() RETURNS text RETURN 1', $statement->withReturns(new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'text')))->toString());
    }

    public function testWithCharacteristicsReplacesAllCharacteristics(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f() RETURNS INT NO SQL RETURN 1');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertSame('CREATE FUNCTION `f`() RETURNS integer DETERMINISTIC RETURN 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withCharacteristics(new RoutineCharacteristics(true))));
    }

    public function testWithBodyRequiresReturn(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f() RETURNS INT RETURN 1');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withBody(new BlockStatement(null));
    }

    public function testWithDefinerSetsTheDefiner(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f() RETURNS INT RETURN 1');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertSame('CREATE DEFINER = CURRENT_USER FUNCTION `f`() RETURNS integer RETURN 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withDefiner(CurrentAccount::Authenticated)));
    }

    public function testRejectsAnExternalBodyBeforeMySql81(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('CREATE FUNCTION f() RETURNS INT RETURN 1');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateFunctionStatement($statement->origin, $statement->name, [], $statement->returns, $statement->characteristics, new ExternalRoutineCode('JAVASCRIPT', 'return 1'));
    }

    public function testDiagnosesAFunctionWithoutReturn(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramStatement->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f() RETURNS INT DO 1', strict: false);
    }
}
