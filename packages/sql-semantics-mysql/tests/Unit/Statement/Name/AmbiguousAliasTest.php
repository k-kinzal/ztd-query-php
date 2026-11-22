<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AmbiguousAlias::class)]
#[Medium]
final class AmbiguousAliasTest extends TestCase
{
    public function testMessageNamesTheAlias(): void
    {
        $operation = (new Inspection())->diagnose('SELECT 1 AS x, 2 AS x ORDER BY x + 1', []);
        $fields = $operation->fields()->items ?? [];

        self::assertSame('Column x is ambiguous.', (new AmbiguousAlias(new Name('x'), $fields))->message());
        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(AmbiguousAlias::class, $operation->facts->diagnostics[0]);
        self::assertSame($fields, $operation->facts->diagnostics[0]->candidates);
    }

    public function testRejectsASingleCandidate(): void
    {
        $operation = (new Inspection())->diagnose('SELECT 1 AS x', []);

        $this->expectExceptionMessage('An ambiguous alias names at least two select list items.');

        new AmbiguousAlias(new Name('x'), $operation->fields()->items ?? []);
    }
}
