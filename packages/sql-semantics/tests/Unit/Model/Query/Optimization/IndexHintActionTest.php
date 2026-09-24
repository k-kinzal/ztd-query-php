<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Optimization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Optimization\IndexHintAction;

#[CoversClass(IndexHintAction::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class IndexHintActionTest extends TestCase
{
    public function testCasesAreBackedByTheirKeywords(): void
    {
        self::assertSame(['USE', 'IGNORE', 'FORCE'], array_map(static fn (IndexHintAction $action): string => $action->value, IndexHintAction::cases()));
    }
}
