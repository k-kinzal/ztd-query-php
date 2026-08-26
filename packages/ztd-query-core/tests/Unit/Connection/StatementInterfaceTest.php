<?php

declare(strict_types=1);

namespace Tests\Unit\Connection;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeStatement;
use ZtdQuery\Connection\StatementInterface;

#[CoversNothing]
final class StatementInterfaceTest extends TestCase
{
    public function testFakeStatementImplementsInterface(): void
    {
        $statement = new FakeStatement([]);

        self::assertInstanceOf(StatementInterface::class, $statement);
    }
}
