<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\MissingPrimaryKeyException;
use ZtdQuery\Exception\SimulationException;

#[CoversClass(MissingPrimaryKeyException::class)]
final class MissingPrimaryKeyExceptionTest extends TestCase
{
    public function testCarriesTableContext(): void
    {
        $exception = new MissingPrimaryKeyException('users');

        self::assertInstanceOf(SimulationException::class, $exception);
        self::assertSame('users', $exception->getTableName());
        self::assertSame("UPDATE simulation requires primary keys for 'users'.", $exception->getMessage());
    }
}
