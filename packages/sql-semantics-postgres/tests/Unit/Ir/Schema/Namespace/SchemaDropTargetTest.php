<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaDropTarget;

#[CoversClass(SchemaDropTarget::class)]
final class SchemaDropTargetTest extends TestCase
{
    public function testPreservesTheDecodedNameAndPresence(): void
    {
        $target = new SchemaDropTarget(new Identifier('Regional."棚"'), false);

        self::assertSame('Regional."棚"', $target->name->value);
        self::assertFalse($target->present);
    }

    public function testRejectsAnUnboundTemporaryNamespace(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('needs a persistent namespace coordinate');

        new SchemaDropTarget(new Identifier('pg_temp_3'), true);
    }
}
