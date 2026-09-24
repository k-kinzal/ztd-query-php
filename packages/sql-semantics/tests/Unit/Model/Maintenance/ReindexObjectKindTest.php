<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Maintenance\ReindexObjectKind;

#[CoversClass(ReindexObjectKind::class)]
final class ReindexObjectKindTest extends TestCase
{
    public function testRepresentsEveryRebuildableObject(): void
    {
        self::assertSame(['INDEX', 'TABLE', 'SCHEMA'], array_column(ReindexObjectKind::cases(), 'value'));
        self::assertSame(ReindexObjectKind::Schema, ReindexObjectKind::from('SCHEMA'));
    }
}
