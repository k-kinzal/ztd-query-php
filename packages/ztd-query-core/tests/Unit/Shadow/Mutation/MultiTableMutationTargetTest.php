<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\MultiTableMutationTarget;

#[CoversClass(MultiTableMutationTarget::class)]
final class MultiTableMutationTargetTest extends TestCase
{
    public function testTableNameColumnsAndPrimaryKeysAreWhatTheTargetWasWrittenAs(): void
    {
        $target = new MultiTableMutationTarget('users', ['id', 'name'], [3 => 'id']);

        self::assertSame('users', $target->tableName());
        self::assertSame(['id', 'name'], $target->columns());
        self::assertSame(['id'], $target->primaryKeys());
        self::assertSame(['id'], $target->matchColumns());
    }

    public function testUsesAllColumnsForMatchingWithoutPrimaryKey(): void
    {
        $target = new MultiTableMutationTarget('logs', [2 => 'message', 4 => 'created_at'], []);

        self::assertSame(['message', 'created_at'], $target->columns());
        self::assertSame(['message', 'created_at'], $target->matchColumns());
    }

    public function testMatchColumnsAreTheKeyColumnsWhereTheTableDeclaresOne(): void
    {
        $target = new MultiTableMutationTarget('users', ['id', 'name'], ['id']);

        self::assertSame(['id'], $target->matchColumns());
    }

    public function testMatchColumnsFallBackToEveryColumnWhereTheTableDeclaresNoKey(): void
    {
        $target = new MultiTableMutationTarget('users', ['id', 'name'], []);

        self::assertSame(['id', 'name'], $target->matchColumns());
    }

    public function testColumnsAreTheColumnsTheStatementWrites(): void
    {
        $target = new MultiTableMutationTarget('users', ['id', 'name'], ['id']);

        self::assertSame(['id', 'name'], $target->columns());
    }
}
