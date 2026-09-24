<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\TextSearch\MappingChange;

#[CoversClass(MappingChange::class)]
final class MappingChangeTest extends TestCase
{
    public function testSpellsTheSqlKeywords(): void
    {
        self::assertSame(['ADD', 'ALTER'], array_map(static fn (MappingChange $change): string => $change->value, MappingChange::cases()));
    }
}
