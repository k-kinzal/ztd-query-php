<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Adapter\Pdo\Session\SessionFactoryResolver;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

#[CoversClass(SessionFactoryResolver::class)]
#[UsesClass(ZtdPdo::class)]
final class SessionFactoryResolverTest extends TestCase
{
    public function testMissingFactoryRequiresAnExplicitPlatformChoice(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provide a SessionFactory or use a database-specific PDO adapter.');
        ZtdPdo::fromPdo(new PDO('sqlite::memory:'));
    }
}
