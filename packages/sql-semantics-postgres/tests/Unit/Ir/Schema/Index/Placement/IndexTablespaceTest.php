<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Placement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Placement\IndexTablespace;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;

#[CoversClass(IndexTablespace::class)]
final class IndexTablespaceTest extends TestCase
{
    public function testPrefixPreservesQuotedNameAndReporting(): void
    {
        $placement = new IndexTablespace(new Identifier('cold "archive"'));

        self::assertSame('REINDEX (TABLESPACE "cold ""archive""") ', $placement->prefix(false));
        self::assertSame('REINDEX (VERBOSE, TABLESPACE "cold ""archive""") ', $placement->prefix(true));
    }

    public function testRejectsTheReservedSharedDestination(): void
    {
        try {
            new IndexTablespace(new Identifier('pg_global'));
        } catch (InvalidConstruction $failure) {
            self::assertStringContainsString('requires an application tablespace or pg_default', $failure->getMessage());

            return;
        }

        self::fail('A shared destination was constructed for an application index.');
    }
}
