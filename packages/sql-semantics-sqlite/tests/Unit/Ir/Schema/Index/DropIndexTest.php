<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\IndexReference;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\MissingIndex;

#[CoversClass(DropIndex::class)]
#[Small]
final class DropIndexTest extends TestCase
{
    public function testToStringUsesAnAbsoluteInstalledIndex(): void
    {
        $drop = new DropIndex(new IndexReference(new Identifier('temp'), new Identifier('name "quoted"'), new Identifier('owner')));

        self::assertSame('DROP INDEX "temp"."name ""quoted"""', $drop->toString());
    }

    public function testProfileIdIdentifiesNativeIndexRemoval(): void
    {
        $drop = new DropIndex(new MissingIndex(new Identifier('x')), true);

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', $drop->profileId());
    }
}
