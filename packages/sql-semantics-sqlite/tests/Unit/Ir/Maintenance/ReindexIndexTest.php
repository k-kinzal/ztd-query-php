<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\ReindexIndex;

#[CoversClass(ReindexIndex::class)]
#[Small]
final class ReindexIndexTest extends TestCase
{
    public function testQuotedUsesTheDecodedIndexInsteadOfItsOwner(): void
    {
        $index = new ReindexIndex(new Identifier('main'), new Identifier('stored "index"'), new Identifier('entries'));

        self::assertSame('"main"."stored ""index"""', $index->quoted());
        self::assertSame('entries', $index->table->value);
    }
}
