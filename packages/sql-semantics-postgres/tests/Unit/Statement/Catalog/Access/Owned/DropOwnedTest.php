<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Owned;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Owned\DropOwned::class)]
#[Medium]
final class DropOwnedTest extends TestCase
{
    public function testRenderWritesTheRolesAndTheBehavior(): void
    {
        self::assertSame('DROP OWNED BY joe, CURRENT_USER CASCADE', (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('DROP OWNED BY joe, CURRENT_USER CASCADE')->toString());
    }

    public function testDeriveStatementReportsPublic(): void
    {
        self::assertSame(['role "public" does not exist'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('DROP OWNED BY joe, PUBLIC')->facts->diagnostics));
    }

    public function testRejectsNoRole(): void
    {
        $this->expectExceptionMessage('A role list names at least one role.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Owned\DropOwned([]);
    }
}
