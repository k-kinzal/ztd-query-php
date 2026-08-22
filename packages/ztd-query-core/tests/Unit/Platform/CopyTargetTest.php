<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\CopyTarget;

#[CoversClass(CopyTarget::class)]
final class CopyTargetTest extends TestCase
{
    public function testCarriesSemanticRelationAndColumnNames(): void
    {
        $target = new CopyTarget(['public', 'users'], ['id', 'name']);

        self::assertSame(['public', 'users'], $target->relation);
        self::assertSame(['id', 'name'], $target->columns);
        self::assertSame('users', $target->tableName());
    }
}
