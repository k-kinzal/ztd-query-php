<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexTarget;

#[CoversClass(IndexTarget::class)]
#[Small]
final class IndexTargetTest extends TestCase
{
    public function testTargetKeepsTheSessionNamespaceAndInheritanceRole(): void
    {
        $target = new IndexTarget(new Identifier('pg_temp'), new Identifier('indexed'), true);

        self::assertSame('pg_temp', $target->schema->value);
        self::assertTrue($target->only);
    }
}
