<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Account\PrivilegeLevels;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Privilege\DatabaseScope;
use SqlSemantics\Model\Definition\Privilege\PrivilegeScope;
use SqlSemantics\Model\Definition\Privilege\RoutineTarget;
use SqlSemantics\Model\Query\Inspection\Routine\RoutineKind;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PrivilegeLevels::class)]
#[Medium]
final class PrivilegeLevelsTest extends TestCase
{
    public function testReadClassifiesWildcardLevels(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $global = $binder->bind('GRANT SELECT ON *.* TO u');
        $current = $binder->bind('GRANT SELECT ON * TO u');
        $database = $binder->bind('GRANT SELECT ON `app`.* TO u');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $global);
        self::assertInstanceOf(GrantPrivilegesStatement::class, $current);
        self::assertInstanceOf(GrantPrivilegesStatement::class, $database);
        self::assertSame(PrivilegeScope::Global, $global->target);
        self::assertSame(PrivilegeScope::CurrentDatabase, $current->target);
        self::assertEquals(new DatabaseScope('app'), $database->target);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testReadResolvesATableAgainstTheSchema(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT)')))->bind('GRANT SELECT ON TABLE t TO u');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertInstanceOf(TableReference::class, $statement->target);
        self::assertSame('t', $statement->target->declaration->name);
    }

    public function testReadNamesARoutine(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT EXECUTE ON FUNCTION app.f TO u');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertInstanceOf(RoutineTarget::class, $statement->target);
        self::assertSame(RoutineKind::Function, $statement->target->kind);
        self::assertSame(['app', 'f'], $statement->target->name->parts);
    }

    public function testReadRejectsARoutineClassOnAWildcard(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PrivilegeLevel->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT EXECUTE ON PROCEDURE app.* TO u');
    }

    #[TestWith(['GRANT SELECT ON TABLE t TO u', null])]
    #[TestWith(['GRANT SELECT ON t TO u', null])]
    #[TestWith(['GRANT EXECUTE ON PROCEDURE p TO u', RoutineKind::Procedure])]
    public function testKindReadsTheObjectClassKeyword(string $sql, ?RoutineKind $kind): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse($sql);
        self::assertSame($kind, PrivilegeLevels::kind($tree->find('grant')[0]));
    }

    public function testDatabaseKeepsTheDecodedName(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('GRANT SELECT ON `a``b`.* TO u');
        self::assertSame('a`b', PrivilegeLevels::database($tree->find('grant_ident')[0], 'a`b')->database);
    }

    public function testDatabaseRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DatabaseName->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT SELECT ON ``.* TO u');
    }
}
