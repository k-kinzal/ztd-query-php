<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;

#[CoversClass(ZtdPdoException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdo::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Driver\PdoConnection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Driver\PdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\StatementExecution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\Bindings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\BufferedRow::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\DriverSessionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\PreparedQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ConnectionExecution::class)]
final class ZtdPdoExceptionTest extends TestCase
{
    public function testItIsCaughtByCodeThatCatchesPdosOwnFailures(): void
    {
        $exception = new ZtdPdoException('test');

        self::assertContains(PDOException::class, class_parents($exception));
    }

    public function testMessageIsSet(): void
    {
        $exception = new ZtdPdoException('something went wrong');

        self::assertSame('something went wrong', $exception->getMessage());
    }

    public function testPreviousExceptionIsPreserved(): void
    {
        $previous = new RuntimeException('cause');
        $exception = new ZtdPdoException('wrapped', 0, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    public function testDefaultCodeIsZero(): void
    {
        $exception = new ZtdPdoException('test');

        self::assertSame(0, $exception->getCode());
    }

    public function testExplicitCodeIsPreserved(): void
    {
        $exception = new ZtdPdoException('test', 42);

        self::assertSame(42, $exception->getCode());
    }
}
