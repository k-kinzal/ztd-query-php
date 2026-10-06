<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnostic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Diagnostic\ResourceLimitExceeded;

#[CoversClass(ResourceLimitExceeded::class)]
#[Small]
final class ResourceLimitExceededTest extends TestCase
{
    public function testTheMessageIsKept(): void
    {
        $limit = new ResourceLimitExceeded('The statement nests deeper than the configured limit.');

        self::assertSame('The statement nests deeper than the configured limit.', $limit->getMessage());
    }

    public function testTheCauseIsChained(): void
    {
        $cause = new RuntimeException('The nesting counter overflowed.');

        $limit = new ResourceLimitExceeded('The work limit was reached.', 0, $cause);

        self::assertSame($cause, $limit->getPrevious());
    }
}
