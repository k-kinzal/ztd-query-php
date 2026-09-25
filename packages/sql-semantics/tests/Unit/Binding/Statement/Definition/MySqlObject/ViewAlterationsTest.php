<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\MySqlObject\ViewAlterations;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\View\ViewAlgorithm;
use SqlSemantics\Model\Definition\View\ViewSecurity;
use SqlSemantics\Model\Definition\ViewCheck;
use SqlSemantics\Model\Statement\Definition\MySql\View\AlterViewStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ViewAlterations::class)]
#[Medium]
final class ViewAlterationsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindReadsThePropertiesAndQueryInEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('ALTER ALGORITHM = MERGE DEFINER = CURRENT_USER SQL SECURITY INVOKER VIEW d.v (a, b) AS SELECT 1, 2 WITH LOCAL CHECK OPTION');
        self::assertInstanceOf(AlterViewStatement::class, $statement);
        self::assertSame([['d', 'v'], ['a', 'b'], ViewCheck::Local, ViewAlgorithm::Merge, ViewSecurity::Invoker, 2], [$statement->name->parts, $statement->columns, $statement->check, $statement->properties->algorithm, $statement->properties->security, count($statement->query->resultColumns())]);
        self::assertInstanceOf(CurrentAccount::class, $statement->properties->definer);
        $expected = 'ALTER ALGORITHM = MERGE DEFINER = CURRENT_USER SQL SECURITY INVOKER VIEW `d`.`v`(`a`, `b`) AS SELECT 1, 2 WITH LOCAL CHECK OPTION';
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testBindReturnsNullForAnotherAlteration(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $statement = (new Binder($schema))->bind('ALTER TABLESPACE ts RENAME TO t2');
        self::assertNull(ViewAlterations::bind($statement->origin, $statement->source, new QueryContext(new TableResolver($schema, new Identifiers(Dialect::MySql), ''))));
    }

    #[TestWith(['ALTER VIEW v AS SELECT 1', ViewCheck::None])]
    #[TestWith(['ALTER VIEW v AS SELECT 1 WITH CHECK OPTION', ViewCheck::Cascaded])]
    #[TestWith(['ALTER VIEW v AS SELECT 1 WITH CASCADED CHECK OPTION', ViewCheck::Cascaded])]
    #[TestWith(['ALTER VIEW v AS SELECT 1 WITH LOCAL CHECK OPTION', ViewCheck::Local])]
    public function testCheckReadsTheCheckOption(string $sql, ViewCheck $check): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
        self::assertSame($check, ViewAlterations::check($statement->source));
    }

    #[TestWith(['mysql-5.6.51', 'ALTER VIEW v AS SELECT a FROM t FOR UPDATE', 'ALTER VIEW `v` AS SELECT `a` AS `a` FROM `t` FOR UPDATE'])]
    #[TestWith(['mysql-5.7.44', 'ALTER VIEW v AS SELECT a FROM t LOCK IN SHARE MODE', 'ALTER VIEW `v` AS SELECT `a` AS `a` FROM `t` LOCK IN SHARE MODE'])]
    #[TestWith(['mysql-8.4.7', 'ALTER VIEW v AS SELECT a FROM t FOR UPDATE OF t SKIP LOCKED', 'ALTER VIEW `v` AS SELECT `a` AS `a` FROM `t` FOR UPDATE OF `t` SKIP LOCKED'])]
    #[TestWith(['mysql-9.1.0', 'ALTER VIEW v AS SELECT a FROM t FOR SHARE NOWAIT WITH CHECK OPTION', 'ALTER VIEW `v` AS SELECT `a` AS `a` FROM `t` FOR SHARE NOWAIT WITH CASCADED CHECK OPTION'])]
    public function testBindKeepsTheLockingClauseOfTheViewQuery(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(AlterViewStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    #[TestWith(['mysql-5.6.51', 'ALTER SQL SECURITY DEFINER VIEW v AS SELECT 1', ViewSecurity::Definer, 'ALTER SQL SECURITY DEFINER VIEW `v` AS SELECT 1'])]
    #[TestWith(['mysql-8.4.7', 'ALTER ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW v AS SELECT 1', ViewSecurity::Definer, 'ALTER SQL SECURITY DEFINER VIEW `v` AS SELECT 1'])]
    #[TestWith(['mysql-9.1.0', 'ALTER VIEW v AS SELECT 1', null, 'ALTER VIEW `v` AS SELECT 1'])]
    public function testBindKeepsAnExplicitDefinerSecurityApartFromAnOmittedOne(string $version, string $sql, ?ViewSecurity $security, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(AlterViewStatement::class, $statement);
        self::assertSame($security, $statement->properties->security);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }
}
