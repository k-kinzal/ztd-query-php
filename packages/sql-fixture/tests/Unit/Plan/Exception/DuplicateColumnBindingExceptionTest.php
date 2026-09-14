<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;

#[CoversClass(\SqlFixture\Plan\Exception\DuplicateColumnBindingException::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CyclicDependencyException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnboundedSelfReferenceException::class)]
final class DuplicateColumnBindingExceptionTest extends TestCase
{
    public function testDescribesDuplicateColumnBinding(): void
    {
        $exception = new \SqlFixture\Plan\Exception\DuplicateColumnBindingException(
            ColumnRef::of('b', 'x'),
            ColumnRef::of('a', 'id'),
            ColumnRef::of('c', 'id')
        );
        $message = $exception->getMessage();

        self::assertSame(
            'b.x is bound to a.id and to c.id. A column can reference one parent, so one of '
            . 'the two relations has to go.',
            $message
        );
        self::assertEquals(ColumnRef::of('b', 'x'), $exception->child);
        self::assertEquals(ColumnRef::of('a', 'id'), $exception->first);
        self::assertEquals(ColumnRef::of('c', 'id'), $exception->second);
    }
}
