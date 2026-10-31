<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Account\Privilege\LevelRule;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege;

#[CoversClass(LevelRule::class)]
#[Medium]
final class LevelRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerMisplacedRequests(): iterable
    {
        yield 'column privilege at the global level' => ['mysql-8.4.7', 'GRANT SELECT(a) ON *.* TO u', 'ER_ILLEGAL_GRANT_FOR_TABLE'];
        yield 'routine privilege on a table' => ['mysql-8.4.7', 'GRANT EXECUTE ON TABLE t TO u', 'ER_ILLEGAL_GRANT_FOR_TABLE'];
        yield 'AS outside the global level' => ['mysql-8.4.7', 'GRANT SELECT ON d.* TO u AS u', 'ER_UNSUPPORTED_USE_OF_GRANT_AS'];
        yield 'dynamic privilege at the database level' => ['mysql-8.4.7', 'GRANT BACKUP_ADMIN ON d.* TO u', 'ER_ILLEGAL_PRIVILEGE_LEVEL'];
        yield 'legacy global privilege at the database level' => ['mysql-5.7.44', 'GRANT SUPER ON d.t TO u', 'ER_ILLEGAL_GRANT_FOR_TABLE'];
        yield 'revoked routine privilege on a table' => ['mysql-8.4.7', 'REVOKE EXECUTE ON TABLE t FROM u', 'ER_ILLEGAL_GRANT_FOR_TABLE'];
    }

    #[DataProvider('providerMisplacedRequests')]
    public function testCheckedRefusesAStandaloneRequestOutsideItsLevel(string $release, string $sql, string $error): void
    {
        try {
            (new Semantics(Dialect::MySql, $release))->analyze($sql);
            self::fail('The privileges do not fit the level.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(MisplacedPrivilege::class, $failure->problem);
            self::assertSame($error, $failure->problem->error);
            self::assertFalse($failure->aborts);
        }
    }

    public function testCheckedKeepsAStoredRequestAndAFittingOne(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $stored = $semantics->analyze('CREATE PROCEDURE p() GRANT CREATE TABLESPACE ON d.* TO u');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $stored);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges::class, $stored->body);
        self::assertSame('GRANT SELECT, INSERT ON d.t TO u', $semantics->analyze('GRANT SELECT, INSERT ON d.t TO u')->toString());
        self::assertSame('GRANT RELOAD ON d.* TO u', $semantics->analyze('GRANT RELOAD ON d.* TO u')->toString());
        self::assertSame('REVOKE IF EXISTS BACKUP_ADMIN ON d.* FROM u', $semantics->analyze('REVOKE IF EXISTS BACKUP_ADMIN ON d.* FROM u')->toString());
    }
}
