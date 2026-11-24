<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\EmptyIndexMetadata;

#[CoversClass(EmptyIndexMetadata::class)]
#[Small]
final class EmptyIndexMetadataTest extends TestCase
{
    public function testKeepsARealEmptyNameDistinctFromNoName(): void
    {
        $selection = new EmptyIndexMetadata(new Identifier('temp'), new Identifier(''));

        self::assertSame('temp', $selection->schema?->value);
        self::assertSame('', $selection->name?->value);
        self::assertNull((new EmptyIndexMetadata())->name);
    }
}
