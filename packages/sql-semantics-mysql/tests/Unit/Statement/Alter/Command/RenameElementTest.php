<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(RenameElement::class)]
#[Medium]
final class RenameElementTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->inspect('ALTER TABLE t RENAME INDEX i TO j', [$table]);

        self::assertSame([], $alter->facts->diagnostics);
    }

    public function testRenderWritesTheKind(): void
    {
        self::assertSame('ALTER TABLE t RENAME INDEX i TO j', (new Inspection())->inspect('ALTER TABLE t RENAME KEY i TO j')->toString());
    }
}
