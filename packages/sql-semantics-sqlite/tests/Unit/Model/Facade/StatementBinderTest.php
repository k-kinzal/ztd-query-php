<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Facade\StatementBinder;
use SqlSemantics\Platform\Sqlite\Platform;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(StatementBinder::class)]
#[Small]
final class StatementBinderTest extends TestCase
{
    public function testResolveBindsASingleStatement(): void
    {
        $platform = Platform::shared();
        $profile = (new Semantics())->profile();
        $context = $platform->context($profile, null, [], true);
        $binding = (new StatementBinder($platform->parser($profile)))->resolve('SELECT 1 AS answer', $context);

        self::assertSame($context, $binding->context);
        self::assertSame('answer', $binding->facts->output?->fields()?->at(0)->name?->value);
    }
}
