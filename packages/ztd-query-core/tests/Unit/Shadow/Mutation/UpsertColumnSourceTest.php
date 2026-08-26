<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\UpsertColumnSource;

#[CoversClass(UpsertColumnSource::class)]
final class UpsertColumnSourceTest extends TestCase
{
    public function testListsSemanticColumnSources(): void
    {
        self::assertSame(
            [UpsertColumnSource::Existing, UpsertColumnSource::Incoming],
            UpsertColumnSource::cases(),
        );
    }
}
