<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\RenameTablespace;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(RenameTablespace::class)]
#[Medium]
final class RenameTablespaceTest extends TestCase
{
    public function testRenderWritesBothNames(): void
    {
        self::assertSame('ALTER TABLESPACE a RENAME TO `b c`', (new Inspection())->inspect('alter tablespace a rename to `b c`')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect('ALTER TABLESPACE a RENAME TO b');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
