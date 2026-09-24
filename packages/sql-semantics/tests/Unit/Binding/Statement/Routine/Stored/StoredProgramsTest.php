<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Routine\Stored\StoredPrograms;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Statement\Definition\MySql\Program;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StoredPrograms::class)]
#[Medium]
final class StoredProgramsTest extends TestCase
{
    /**
     * @param class-string $class
     */
    #[TestWith(['mysql-5.6.51', 'CREATE PROCEDURE p() BEGIN END', Program\CreateProcedureStatement::class])]
    #[TestWith(['mysql-5.7.44', 'CREATE FUNCTION f() RETURNS INT RETURN 1', Program\CreateFunctionStatement::class])]
    #[TestWith(['mysql-8.0.44', 'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW DO 1', Program\CreateTriggerStatement::class])]
    #[TestWith(['mysql-8.4.7', 'CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO DO 1', Program\CreateEventStatement::class])]
    #[TestWith(['mysql-5.6.51', 'ALTER EVENT e ENABLE', Program\AlterEventStatement::class])]
    #[TestWith(['mysql-9.1.0', 'ALTER EVENT e ENABLE', Program\AlterEventStatement::class])]
    public function testBindRoutesEveryStoredProgramForm(string $version, string $sql, string $class): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (n INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf($class, $statement);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testBindLeavesLoadableFunctionsToTheirFamily(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE FUNCTION f RETURNS STRING SONAME 'f.so'");
        self::assertNotInstanceOf(Program\CreateFunctionStatement::class, $statement);
    }

    /**
     * @param class-string $class
     */
    #[TestWith(["CREATE DEFINER = 'a'@'h' PROCEDURE p() BEGIN END", AccountName::class])]
    #[TestWith(['CREATE DEFINER = CURRENT_USER() PROCEDURE p() BEGIN END', CurrentAccount::class])]
    public function testDefinerReadsTheAccount(string $sql, string $class): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse($sql);
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::MySql))->build(), new Identifiers(Dialect::MySql), ''));
        self::assertInstanceOf($class, StoredPrograms::definer(Tree::outer($tree, ['definer'])[0], $context));
        self::assertNull(StoredPrograms::definer(null, $context));
    }

    public function testNameReadsQualifiedNames(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('CREATE PROCEDURE `app`.`p q`() BEGIN END');
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::MySql))->build(), new Identifiers(Dialect::MySql), ''));
        self::assertSame(['app', 'p q'], StoredPrograms::name(Tree::outer($tree, ['sp_name'])[0], $context)->parts);
    }

    #[TestWith(['mysql-5.7.44', 'alter definer = u event e enable', "ALTER DEFINER = 'u' EVENT `e` ENABLE"])]
    #[TestWith(['mysql-8.4.7', 'alter definer = u event e enable', "ALTER DEFINER = 'u' EVENT `e` ENABLE"])]
    #[TestWith(['mysql-5.7.44', 'create definer = u event e on schedule every 1 day do select 1', "CREATE DEFINER = 'u' EVENT `e` ON SCHEDULE EVERY 1 DAY DO SELECT 1"])]
    #[TestWith(['mysql-8.4.7', 'create definer = u trigger tr before insert on t for each row set @a = 1', "CREATE DEFINER = 'u' TRIGGER `tr` BEFORE INSERT ON `t` FOR EACH ROW SET @`a` = 1"])]
    #[TestWith(['mysql-8.4.7', 'create definer = u procedure p() begin end', "CREATE DEFINER = 'u' PROCEDURE `p`() BEGIN END"])]
    public function testBindKeepsTheDefinerOfEachProgram(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT)'));
        self::assertSame($expected, $binder->bind($sql)->toString());
    }

    public function testNameRejectsAnEmptyComponent(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::RoutineName->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE ``.p() BEGIN END');
    }

    public function testBindLeavesOtherDialectsAlone(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $origin = (new Binder($schema))->bind('SELECT 1')->origin;
        $statement = Tree::outer((new DialectParser(Dialect::MySql))->parse('CREATE PROCEDURE p() BEGIN END'), ['create'])[0];
        self::assertNull(StoredPrograms::bind($origin, $statement, new QueryContext(new TableResolver($schema, new Identifiers(Dialect::PostgreSql), 'public'))));
    }
}
