<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Session\Parse\Definition\RenameInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(RenameInput::class)]
#[Medium]
final class RenameInputTest extends TestCase
{
    public function testRefusedPreservesAccountFailureAcrossCreation(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $missing = $session->run('RENAME USER u TO u')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing);
        self::assertSame(1396, $missing->getCode());
        $session->query('CREATE USER u');
        $known = $session->run('RENAME USER u TO u')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $known);
        self::assertSame(1396, $known->getCode());
    }

    public function testRefusedRetainsPreparedInvocationBeforeTheAccountsExist(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $session->query("PREPARE s FROM 'RENAME USER r TO r'");
        $missing = $session->run('EXECUTE s')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing);
        self::assertSame(1396, $missing->getCode());
        $session->query('CREATE USER r');
        $known = $session->run('EXECUTE s')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $known);
        self::assertSame(1396, $known->getCode());
    }
}
