<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Index;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;

#[CoversClass(Index::class)]
#[Small]
final class IndexTest extends TestCase
{
    public function testIdentityRecordsSchemaNameAndOwner(): void
    {
        $index = new Index(new Name('temp'), new Name('tag'), new Name('items'));

        self::assertSame(['temp', 'tag', 'items'], [$index->schema->value, $index->name->value, $index->table->value]);
    }
}
