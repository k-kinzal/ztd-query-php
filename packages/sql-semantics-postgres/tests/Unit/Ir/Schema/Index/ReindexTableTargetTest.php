<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTableKind;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTableTarget;

#[CoversClass(ReindexTableTarget::class)]
#[Small]
final class ReindexTableTargetTest extends TestCase
{
    public function testQuotedPreservesTheExactOwnerName(): void
    {
        $target = new ReindexTableTarget(new Identifier('Reporting'), new Identifier('orders "current"'), ReindexTableKind::MaterializedView);

        self::assertSame('"Reporting"."orders ""current"""', $target->quoted());
        self::assertSame(ReindexTableKind::MaterializedView, $target->kind);
    }
}
