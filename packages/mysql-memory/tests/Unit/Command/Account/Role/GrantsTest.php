<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account\Role;

use MySqlMemory\Command\Account\Role\Grants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(Grants::class)]
#[Medium]
final class GrantsTest extends TestCase
{
    public function testApplyRollsBackEarlierRoleEdgesWhenALaterRoleIsAnonymous(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $session->query('CREATE USER u; CREATE ROLE r');
        $error = $session->run("GRANT r, '' TO u")[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $error);
        self::assertSame(3524, $error->getCode());
        $result = $session->query('SHOW GRANTS FOR u')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([['GRANT USAGE ON *.* TO `u`@`%`']], $result->rows);
    }
}
