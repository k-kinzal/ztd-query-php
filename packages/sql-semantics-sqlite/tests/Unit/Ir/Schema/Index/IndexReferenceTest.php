<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\IndexReference;

#[CoversClass(IndexReference::class)]
#[Small]
final class IndexReferenceTest extends TestCase
{
    public function testQuotedKeepsTheEmptyAttachedSchemaName(): void
    {
        $index = new IndexReference(new Identifier(''), new Identifier('idx'), new Identifier('owner'));

        self::assertSame('""."idx"', $index->quoted());
        self::assertSame('owner', $index->table->value);
    }
}
