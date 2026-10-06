<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Constructor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\Composite;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Composite::class)]
#[Small]
final class CompositeTest extends TestCase
{
    public function testNameIsTheRelationOrRecord(): void
    {
        self::assertSame(['t', 'record'], [(new Composite([], new Name('t')))->name(), (new Composite([]))->name()]);
    }
}
