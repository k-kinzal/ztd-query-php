<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Account\LegacyGrantRule;

#[CoversClass(LegacyGrantRule::class)]
#[Medium]
final class LegacyGrantRuleTest extends TestCase
{
    public function testRequestKeepsInvalidRoutineLevelsInOrdinaryProgramStatements(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('CREATE PROCEDURE p() GRANT ALL ON PROCEDURE *.* TO u');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges::class, $operation->body);
    }

    public function testGrantLowersEveryCommand(): void
    {
        self::assertSame('GRANT EXECUTE ON FUNCTION f TO u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('grant execute on function f to u')->toString());
        self::assertSame('GRANT PROXY ON a TO b', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('grant proxy on a to b')->toString());
    }

    public function testRevokeLowersEveryCommand(): void
    {
        self::assertSame('REVOKE PROXY ON a FROM b', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('revoke proxy on a from b')->toString());
        self::assertSame('REVOKE ALL, GRANT OPTION FROM u', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('revoke all privileges, grant option from u')->toString());
    }

    public function testTableConfirmsTheOptionalWord(): void
    {
        self::assertSame('GRANT SELECT ON t TO u', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('grant select on table t to u')->toString());
    }

    public function testUsersLowersBothListKinds(): void
    {
        self::assertSame('REVOKE SELECT ON t FROM u', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('revoke select on t from u')->toString());
    }
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerAuthentication(): iterable
    {
        foreach (["BY 'pw'", "BY PASSWORD 'hash'", 'WITH plugin', "WITH plugin AS 'hash'"] as $clause) {
            foreach (['ALL, GRANT OPTION', 'SELECT ON *.*', 'PROXY ON a'] as $target) {
                yield $target . ' ' . $clause => ['REVOKE ' . $target . ' FROM b IDENTIFIED ' . $clause, str_contains($clause, "'") ? substr($clause, (int) strpos($clause, "'")) : ''];
            }
        }
    }

    #[DataProvider('providerAuthentication')]
    public function testUsersRejectsAuthenticationAtTheNativePosition(string $sql, string $suffix): void
    {
        try {
            (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze($sql);
            self::fail('REVOKE cannot change authentication.');
        } catch (\SqlSemantics\Diagnostic\AnalysisException $error) {
            $cause = $error->getPrevious();
            self::assertInstanceOf(\SqlParser\Parser\SyntaxException::class, $cause);
            self::assertSame($suffix, substr($sql, $cause->token->offset));
        }
    }
}
