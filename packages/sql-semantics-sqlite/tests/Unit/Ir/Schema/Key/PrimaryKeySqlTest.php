<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyTerm;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKeySql;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;

#[CoversClass(PrimaryKeySql::class)]
#[Small]
final class PrimaryKeySqlTest extends TestCase
{
    public function testTableUsesTheOwningLayoutAndTermOrder(): void
    {
        $columns = [new TableColumn(new Identifier('code')), new TableColumn(new Identifier('id'))];
        $key = new PrimaryKey([new KeyTerm(1, true), new KeyTerm(0, false, Collation::RightTrim)], conflict: KeyConflict::Fail);

        self::assertSame('PRIMARY KEY ("id" DESC, "code" COLLATE RTRIM) ON CONFLICT FAIL', (new PrimaryKeySql())->table($key, $columns));
    }

    public function testInlineKeepsDescendingAndOmitsTheDefaultAlgorithm(): void
    {
        $key = new PrimaryKey([new KeyTerm(0, true)], inline: true);

        self::assertSame(' PRIMARY KEY DESC', (new PrimaryKeySql())->inline($key));
    }

    public function testConflictAddsTheExplicitPolicyClause(): void
    {
        $renderer = new PrimaryKeySql();

        self::assertSame('', $renderer->conflict(KeyConflict::Abort));
        self::assertSame(' ON CONFLICT ROLLBACK', $renderer->conflict(KeyConflict::Rollback));
    }
}
