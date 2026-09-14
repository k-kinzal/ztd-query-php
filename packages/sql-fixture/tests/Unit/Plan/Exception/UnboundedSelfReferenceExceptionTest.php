<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFixture\Plan\Exception\UnboundedSelfReferenceException::class)]
#[UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\DuplicateColumnBindingException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CyclicDependencyException::class)]
final class UnboundedSelfReferenceExceptionTest extends TestCase
{
    public function testDescribesUnboundedSelfReference(): void
    {
        $exception = new \SqlFixture\Plan\Exception\UnboundedSelfReferenceException(
            'category',
            'category.id < category.parent_id'
        );
        $message = $exception->getMessage();

        self::assertSame(
            'The relation category.id < category.parent_id makes every category row need '
            . 'another one, without end. Mark the child optional with ? so the chain can stop.',
            $message
        );
        self::assertSame('category', $exception->table);
        self::assertSame('category.id < category.parent_id', $exception->written);
    }
}
