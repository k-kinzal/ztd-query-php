<?php

declare(strict_types=1);

namespace Tests\Unit\Result;

use Deriver\Result\ExecutionResultSet as Subject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ExecutionResultSetTest extends TestCase
{
    public function testKeepsExecutionResultsExplicitlySeparate(): void
    {
        self::assertSame([], (new Subject([]))->results);
    }

}
