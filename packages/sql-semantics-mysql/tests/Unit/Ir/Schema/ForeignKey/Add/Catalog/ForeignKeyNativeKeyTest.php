<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyNativeKey::class)]
#[Small]
final class ForeignKeyNativeKeyTest extends TestCase
{
    public function testKeyRetainsAnOrderedLeadingPrefix(): void
    {
        $key = NativeAdditionFixture::key(NativeAdditionFixture::owner('child'), 'known', IndexRole::Ordinary, 'line_id');

        self::assertSame('known', $key->identity->name->value);
        self::assertSame('line_id', $key->fields[0]->field->value);
    }
}
