<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFixture\Plan\Exception\CyclicDependencyException::class)]
#[UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\DuplicateColumnBindingException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnboundedSelfReferenceException::class)]
final class CyclicDependencyExceptionTest extends TestCase
{
    public function testDescribesCyclicDependency(): void
    {
        $exception = new \SqlFixture\Plan\Exception\CyclicDependencyException(['a', 'b', 'c']);
        $message = $exception->getMessage();

        self::assertSame(
            'The plan contains cyclic dependencies among: a, b, c. No generation order satisfies them.',
            $message
        );
        self::assertSame(['a', 'b', 'c'], $exception->blockedTables);
    }
}
