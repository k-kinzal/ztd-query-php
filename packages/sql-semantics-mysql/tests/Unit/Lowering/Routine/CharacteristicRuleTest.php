<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\CharacteristicRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\AccessLevel;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\DataAccess;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineComment;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineLanguage;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SecurityContext;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SqlSecurity;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;

#[CoversClass(CharacteristicRule::class)]
#[Medium]
final class CharacteristicRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCharacteristicsKeepTheWrittenOrderOfACreate(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerCharacteristicsKeepTheWrittenOrderOfACreate')]
    public function testCharacteristicsKeepTheWrittenOrderOfACreate(string $release): void
    {
        $create = (new Semantics(Dialect::MySql, $release))->analyze("create procedure p() comment 'x' language sql not deterministic deterministic contains sql no sql reads sql data modifies sql data sql security definer sql security invoker select 1");
        $statement = $create->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertSame(
            [RoutineComment::class, RoutineLanguage::class, Determinism::class, Determinism::class, DataAccess::class, DataAccess::class, DataAccess::class, DataAccess::class, SqlSecurity::class, SqlSecurity::class],
            array_map(static fn (Characteristic $characteristic): string => $characteristic::class, $statement->characteristics),
        );
        self::assertSame(
            "CREATE PROCEDURE p() COMMENT 'x' LANGUAGE SQL NOT DETERMINISTIC DETERMINISTIC CONTAINS SQL NO SQL READS SQL DATA MODIFIES SQL DATA SQL SECURITY DEFINER SQL SECURITY INVOKER SELECT 1",
            $create->toString(),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCharacteristicsKeepTheWrittenOrderOfAnAlter(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerCharacteristicsKeepTheWrittenOrderOfAnAlter')]
    public function testCharacteristicsKeepTheWrittenOrderOfAnAlter(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $alter = $semantics->analyze("alter function f sql security invoker comment 'a' no sql comment 'b'");
        $statement = $alter->statement;
        self::assertInstanceOf(AlterRoutine::class, $statement);
        self::assertSame(
            [SqlSecurity::class, RoutineComment::class, DataAccess::class, RoutineComment::class],
            array_map(static fn (Characteristic $characteristic): string => $characteristic::class, $statement->characteristics),
        );
        self::assertSame("ALTER FUNCTION f SQL SECURITY INVOKER COMMENT 'a' NO SQL COMMENT 'b'", $alter->toString());

        $bare = $semantics->analyze('alter procedure p')->statement;
        self::assertInstanceOf(AlterRoutine::class, $bare);
        self::assertSame([], $bare->characteristics);
    }

    /**
     * @return iterable<string, array{string, string, AccessLevel}>
     */
    public static function providerCharacteristicLowersTheDataAccess(): iterable
    {
        yield 'no sql on 5.6' => ['mysql-5.6.51', 'NO SQL', AccessLevel::NoSql];
        yield 'contains sql on 5.7' => ['mysql-5.7.44', 'CONTAINS SQL', AccessLevel::ContainsSql];
        yield 'reads sql data on 8.0' => ['mysql-8.0.44', 'READS SQL DATA', AccessLevel::ReadsSqlData];
        yield 'modifies sql data on 9.1' => ['mysql-9.1.0', 'MODIFIES SQL DATA', AccessLevel::ModifiesSqlData];
    }

    #[DataProvider('providerCharacteristicLowersTheDataAccess')]
    public function testCharacteristicLowersTheDataAccess(string $release, string $clause, AccessLevel $level): void
    {
        $statement = (new Semantics(Dialect::MySql, $release))->analyze('CREATE PROCEDURE p() ' . $clause . ' SELECT 1')->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertEquals([new DataAccess($level)], $statement->characteristics);
    }

    /**
     * @return iterable<string, array{string, string, SecurityContext}>
     */
    public static function providerCharacteristicLowersTheSecurityContext(): iterable
    {
        yield 'definer on 5.6' => ['mysql-5.6.51', 'SQL SECURITY DEFINER', SecurityContext::Definer];
        yield 'invoker on 5.6' => ['mysql-5.6.51', 'SQL SECURITY INVOKER', SecurityContext::Invoker];
        yield 'definer on 9.1' => ['mysql-9.1.0', 'SQL SECURITY DEFINER', SecurityContext::Definer];
        yield 'invoker on 9.1' => ['mysql-9.1.0', 'SQL SECURITY INVOKER', SecurityContext::Invoker];
    }

    #[DataProvider('providerCharacteristicLowersTheSecurityContext')]
    public function testCharacteristicLowersTheSecurityContext(string $release, string $clause, SecurityContext $context): void
    {
        $alter = (new Semantics(Dialect::MySql, $release))->analyze('ALTER PROCEDURE p ' . $clause);
        $statement = $alter->statement;
        self::assertInstanceOf(AlterRoutine::class, $statement);
        self::assertEquals([new SqlSecurity($context)], $statement->characteristics);
        self::assertSame('ALTER PROCEDURE p ' . $clause, $alter->toString());
    }

    public function testCharacteristicLowersTheDeterminismOfAFunction(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $statement = $semantics->analyze('create function f() returns int not deterministic deterministic return 1')->statement;
        self::assertInstanceOf(CreateFunction::class, $statement);
        self::assertEquals([new Determinism(false), new Determinism(true)], $statement->characteristics);
    }

    public function testCharacteristicLowersTheCommentText(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() COMMENT "it\'s" SELECT 1')->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        $comment = $statement->characteristics[0];
        self::assertInstanceOf(RoutineComment::class, $comment);
        self::assertSame("it's", $comment->text->value);
    }

    public function testCharacteristicLowersTheLanguage(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $create = $semantics->analyze("CREATE FUNCTION f() RETURNS INT LANGUAGE javascript AS 'return 1'");
        $statement = $create->statement;
        self::assertInstanceOf(CreateFunction::class, $statement);
        $language = $statement->characteristics[0];
        self::assertInstanceOf(RoutineLanguage::class, $language);
        self::assertSame('javascript', $language->external?->value);
        self::assertSame("CREATE FUNCTION f() RETURNS INT LANGUAGE javascript AS 'return 1'", $create->toString());

        $sql = $semantics->analyze('CREATE PROCEDURE p() LANGUAGE SQL SELECT 1')->statement;
        self::assertInstanceOf(CreateProcedure::class, $sql);
        self::assertEquals([new RoutineLanguage()], $sql->characteristics);
    }

    public function testCharacteristicRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new CharacteristicRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: sp_name: ident');

        $rule->characteristic(new Node('sp_name', 1, []));
    }
}
