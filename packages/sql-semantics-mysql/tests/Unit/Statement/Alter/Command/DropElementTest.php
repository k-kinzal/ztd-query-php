<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DropElement::class)]
#[Medium]
final class DropElementTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->inspect('ALTER TABLE t DROP INDEX i, DROP FOREIGN KEY f', [$table]);

        self::assertSame([], $alter->facts->diagnostics);
    }

    public function testRenderWritesEveryKind(): void
    {
        self::assertSame('ALTER TABLE t DROP COLUMN a CASCADE, DROP PRIMARY KEY, DROP INDEX i, DROP CHECK c, DROP CONSTRAINT d', (new Inspection())->inspect('ALTER TABLE t DROP a CASCADE, DROP PRIMARY KEY, DROP KEY i, DROP CHECK c, DROP CONSTRAINT d')->toString());
    }
}
