<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\MissingPrimaryKeyException;

#[CoversClass(MissingPrimaryKeyException::class)]
final class MissingPrimaryKeyExceptionTest extends TestCase
{
    public function testGetTableNameCarriesTableContext(): void
    {
        $exception = new MissingPrimaryKeyException('users');

        self::assertSame('users', $exception->getTableName());
        self::assertSame("UPDATE simulation requires primary keys for 'users'.", $exception->getMessage());
    }
}
