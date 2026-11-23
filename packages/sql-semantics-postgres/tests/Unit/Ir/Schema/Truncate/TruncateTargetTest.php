<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Truncate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate\TruncateTarget;

#[CoversClass(TruncateTarget::class)]
#[Small]
final class TruncateTargetTest extends TestCase
{
    public function testToStringWritesOnlyBeforeTheExactName(): void
    {
        $name = new TableName(new Identifier('day'), new Identifier('items'));
        $target = new TruncateTarget($name, true);

        self::assertSame('ONLY "day"."items"', $target->toString());
    }
}
