<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Account\Catalog;
use MySqlMemory\Account\Grants;
use MySqlMemory\Command\Account\Levels;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Levels::class)]
#[Small]
final class LevelsTest extends TestCase
{
    public function testTargetAnswersTheGlobalLevel(): void
    {
        self::assertSame(['GLOBAL', '', ''], (new Levels())->target(ObjectKind::Table, new GlobalLevel(), (new Instance())->connect()));
    }

    public function testTargetNeedsTheCurrentDatabase(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);

        (new Levels())->target(ObjectKind::Table, new CurrentDatabaseLevel(), (new Instance())->connect());
    }

    public function testParsedRefusesARoleInAPrivilegeList(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("Illegal privilege identifier near 'r@'%' ON *.* TO u' at line 1");

        $session->query("GRANT SELECT, r@'%' ON *.* TO u");
    }

    public function testParsedRefusesAPrivilegeTheLevelDoesNotTake(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1144);

        $session->query('GRANT RELOAD ON d.t TO nobody');
    }

    public function testNearAnswersTheTextFromTheItem(): void
    {
        self::assertSame(['SELECT, r TO u', "`r`@'%' ON *.* TO u", ''], [(new Levels())->near('GRANT SELECT, r TO u', 'SELECT'), (new Levels())->near("GRANT RELOAD, `r`@'%' ON *.* TO u", 'r@%'), (new Levels())->near('GRANT x', '')]);
    }

    public function testObjectsRefusesAMissingRoutine(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1305);
        $this->expectExceptionMessage('PROCEDURE p does not exist');

        $session->query('GRANT EXECUTE ON PROCEDURE d.p TO nobody');
    }

    public function testObjectsRefusesAColumnTheTableDoesNotHave(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $this->expectExceptionMessage("Unknown column 'zz' in 't'");

        $session->query('GRANT SELECT (zz) ON d.t TO nobody');
    }

    public function testDynamicNamesTheTableBelowTheDatabaseLevel(): void
    {
        $session = (new Instance())->connect();

        (new Levels())->dynamic([new DynamicPrivilege(new Name('backup_admin'))], ['TABLE', 'd', 't'], $session, true);
        (new Levels())->dynamic([new DynamicPrivilege(new Name('backup_admin'))], ['DATABASE', 'd', ''], $session, true);

        self::assertSame([['Warning', 3619, 'Illegal privilege level specified for t'], ['Warning', 3619, 'Illegal privilege level specified for BACKUP_ADMIN']], $session->diagnostics->conditions);
    }

    public function testUsageRefusesAPrivilegeADatabaseDoesNotTake(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1221);

        $session->query('REVOKE RELOAD ON d.* FROM root');
    }

    public function testReadAnswersWhatAListNames(): void
    {
        self::assertSame(
            [['INSERT'], ['SELECT' => ['a']], ['ROLE_ADMIN'], true, false],
            (new Levels())->read([new StaticPrivilege(PrivilegeKind::Insert), new StaticPrivilege(PrivilegeKind::Select, [new Name('a')]), new DynamicPrivilege(new Name('role_admin')), new StaticPrivilege(PrivilegeKind::GrantOption), new StaticPrivilege(PrivilegeKind::Usage)], 'TABLE'),
        );
        self::assertSame(Catalog::DYNAMIC, (new Levels())->read([new AllPrivileges()], 'GLOBAL')[2]);
    }

    public function testAllAnswersThePrivilegesOfEachLevel(): void
    {
        self::assertSame([Catalog::DATABASE, ['EXECUTE', 'ALTER ROUTINE']], [(new Levels())->all('DATABASE'), (new Levels())->all('PROCEDURE')]);
    }

    public function testAtKeepsALevelOnlyWhenAsked(): void
    {
        $grants = new Grants();

        self::assertSame([null, true], [(new Levels())->at($grants, ['TABLE', 'd', 't'], false), (new Levels())->at($grants, ['TABLE', 'd', 't'], true)?->empty()]);
    }

    public function testAllNamesTheStaticPrivilegesOfTheRelease(): void
    {
        self::assertSame((new Catalog(\SqlSemantics\Contract\GrammarRelease::MySql5744))->statics(), (new Levels(\SqlSemantics\Contract\GrammarRelease::MySql5744))->all('GLOBAL'));
    }
}
