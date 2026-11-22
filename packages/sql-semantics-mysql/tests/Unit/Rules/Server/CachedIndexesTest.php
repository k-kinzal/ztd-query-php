<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Rules\Server\CachedIndexes;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\PrimaryIndex;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CachedIndexes::class)]
#[Medium]
final class CachedIndexesTest extends TestCase
{
    public function testCheckedKeepsNamesAndPrimary(): void
    {
        $indexes = [new Name('i'), new PrimaryIndex()];

        self::assertSame($indexes, (new CachedIndexes())->checked(new QualifiedName(new Name('t')), $indexes));
        self::assertNull((new CachedIndexes())->checked(new QualifiedName(new Name('t')), null));
    }

    public function testRenderWritesTablePartitionsAndIndexes(): void
    {
        self::assertSame('CACHE INDEX db.t PARTITION (ALL) INDEX (PRIMARY, i) IN c', (new Inspection())->inspect('cache index db.t partition (all) index (primary, i) in c')->toString());
    }
}
