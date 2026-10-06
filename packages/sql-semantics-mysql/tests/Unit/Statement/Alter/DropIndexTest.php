<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\DropIndex;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(DropIndex::class)]
#[Medium]
final class DropIndexTest extends TestCase
{
    public function testDeriveStatementResolvesTheTableAndChecksTheOptions(): void
    {
        $drop = (new Semantics(Dialect::MySql))->analyze('DROP INDEX i ON t ALGORITHM = FAST', []);

        self::assertSame(['Relation t does not exist.', 'FAST is not an ALGORITHM the server knows.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $drop->facts->diagnostics));
    }

    public function testRenderWritesTheOptionsInOrder(): void
    {
        self::assertSame('DROP INDEX `PRIMARY` ON db.t LOCK = shared ALGORITHM = DEFAULT', (new Semantics(Dialect::MySql))->analyze('drop index `PRIMARY` on db.t lock shared algorithm default')->toString());
    }
}
