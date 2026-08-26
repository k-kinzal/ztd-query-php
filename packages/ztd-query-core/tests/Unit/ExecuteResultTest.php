<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ZtdQuery\ExecuteResult;
use ZtdQuery\GenericExecuteResult;

#[CoversNothing]
final class ExecuteResultTest extends TestCase
{
    public function testGenericExecuteResultImplementsInterface(): void
    {
        $result = GenericExecuteResult::passthrough();

        self::assertInstanceOf(ExecuteResult::class, $result);
    }
}
