<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Binder;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Role\SessionRolePolicy;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Account\Identification\HashIdentification;
use SqlSemantics\Model\Definition\Privilege\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\DatabaseScope;
use SqlSemantics\Model\Definition\Privilege\DynamicPrivilege;
use SqlSemantics\Model\Definition\Privilege\Grantor;
use SqlSemantics\Model\Definition\Privilege\PrivilegeScope;
use SqlSemantics\Model\Definition\Privilege\RoleExclusion;
use SqlSemantics\Model\Definition\Privilege\RoutineTarget;
use SqlSemantics\Model\Definition\Privilege\StaticPrivilege;
use SqlSemantics\Model\Query\Inspection\Routine\RoutineKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Account\PrivilegeLevels;

#[CoversClass(PrivilegeLevels::class)]
#[Medium]
final class PrivilegeLevelsTest extends TestCase
{
    public function testLevelWritesEachTargetClass(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('GRANT SELECT ON t TO u');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertSame('TABLE `t`', PrivilegeLevels::level($statement->target)->toString());
        self::assertSame('*', PrivilegeLevels::level(PrivilegeScope::CurrentDatabase)->toString());
        self::assertSame('`a``b`.*', PrivilegeLevels::level(new DatabaseScope('a`b'))->toString());
        self::assertSame('FUNCTION `app`.`f`', PrivilegeLevels::level(new RoutineTarget(new QualifiedName(['app', 'f']), RoutineKind::Function))->toString());
    }

    public function testPrivilegesWriteKeywordsColumnsAndDynamicNames(): void
    {
        self::assertSame('LOCK TABLES, SELECT (`a`, `b`), `BACKUP_ADMIN`', PrivilegeLevels::privileges([StaticPrivilege::LockTables, new ColumnPrivilege(StaticPrivilege::Select, ['a', 'b']), new DynamicPrivilege('BACKUP_ADMIN')])->toString());
    }

    public function testGranteesWriteLegacyCredentials(): void
    {
        $hash = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'*h'", 0));
        self::assertInstanceOf(Literal::class, $hash);
        self::assertSame("'a' IDENTIFIED BY PASSWORD '*h', CURRENT_USER", PrivilegeLevels::grantees([new AccountDefinition(new AccountName('a'), new HashIdentification($hash)), CurrentAccount::Authenticated])->toString());
    }

    public function testGrantorWritesTheRoleSelection(): void
    {
        self::assertSame([], PrivilegeLevels::grantor(null));
        self::assertSame("AS 'g'", (new Tree('grantor', PrivilegeLevels::grantor(new Grantor(new AccountName('g')))))->toString());
        self::assertSame('AS CURRENT_USER WITH ROLE NONE', (new Tree('grantor', PrivilegeLevels::grantor(new Grantor(CurrentAccount::Authenticated, SessionRolePolicy::None))))->toString());
        self::assertSame("AS 'g' WITH ROLE ALL EXCEPT 'r'", (new Tree('grantor', PrivilegeLevels::grantor(new Grantor(new AccountName('g'), new RoleExclusion([new AccountName('r')])))))->toString());
    }

    #[TestWith(['GRANT SELECT ON *.* TO u AS r WITH ROLE a, b', GrantPrivilegesStatement::class, 'GRANT SELECT ON *.* TO \'u\' AS \'r\' WITH ROLE \'a\', \'b\''])]
    #[TestWith(['GRANT SELECT ON *.* TO u AS r WITH ROLE NONE', GrantPrivilegesStatement::class, 'GRANT SELECT ON *.* TO \'u\' AS \'r\' WITH ROLE NONE'])]
    public function testGrantorSpellsTheRoleSelection(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
