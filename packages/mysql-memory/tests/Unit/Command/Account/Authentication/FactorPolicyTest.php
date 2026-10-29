<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account\Authentication;

use MySqlMemory\Command\Account\Authentication\FactorPolicy;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(FactorPolicy::class)]
#[Small]
final class FactorPolicyTest extends TestCase
{
    #[DataProvider('providerReleases')]
    public function testSqlExecutionRefusesOmittedFactorsAfterRevokingAllPrivileges(string $version): void
    {
        $session = (new Instance($version))->connect();
        $session->query('REVOKE ALL PRIVILEGES, GRANT OPTION FROM CURRENT_USER');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4058);
        $session->query('ALTER USER CURRENT_USER ADD 2 FACTOR IDENTIFIED BY RANDOM PASSWORD ADD 3 FACTOR IDENTIFIED BY RANDOM PASSWORD');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            yield $version => [$version];
        }
    }

    public function testMismatchRefusesAnExemptionRevokedInTheExistingConnection(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->query('REVOKE AUTHENTICATION_POLICY_ADMIN ON *.* FROM CURRENT_USER');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4058);
        (new FactorPolicy())->mismatch('2', $session);
    }

    public function testMismatchAcceptsAnExemptionThroughAnActiveRole(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->query('CREATE ROLE policy_admin, inherited; GRANT AUTHENTICATION_POLICY_ADMIN ON *.* TO policy_admin; GRANT policy_admin TO inherited; GRANT inherited TO CURRENT_USER; SET ROLE inherited; REVOKE AUTHENTICATION_POLICY_ADMIN ON *.* FROM CURRENT_USER');
        (new FactorPolicy())->mismatch('3', $session);
        self::assertSame([4058], array_column($session->diagnostics->conditions, 1));
        $session->query('SET ROLE NONE');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4058);
        (new FactorPolicy())->mismatch('3', $session);
    }
}
