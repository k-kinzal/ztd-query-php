<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\DefinitionSource;

#[CoversClass(DefinitionSource::class)]
#[Small]
final class DefinitionSourceTest extends TestCase
{
    public function testOwnsTheDeclaredNamesWithoutRetainingArrayReferences(): void
    {
        $name = new Identifier('original');
        $source = new DefinitionSource([&$name]);
        $name = new Identifier('different');

        self::assertSame('original', $source->columns[0]->value);
    }
}
