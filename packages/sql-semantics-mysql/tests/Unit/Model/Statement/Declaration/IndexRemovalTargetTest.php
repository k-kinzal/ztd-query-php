<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexAutoIncrement;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(IndexRemovalTarget::class)]
#[Small]
final class IndexRemovalTargetTest extends TestCase
{
    public function testPreservesTheIndexAndItsOriginalGeneratingSupport(): void
    {
        $key = NativeAdditionFixture::key(NativeAdditionFixture::owner('child'), 'Existing', IndexRole::Ordinary, 'id');
        $support = new IndexAutoIncrement(new Identifier('id'), [new Identifier('other')]);
        $binding = new IndexRemovalTarget($key->identity, $support);

        self::assertSame($key->identity, $binding->index);
        self::assertSame($support, $binding->autoIncrement);
    }
}
