<?php

declare(strict_types=1);

namespace Tests\Unit\Php;

use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Php\TypeReader;
use SqlCatalog\Type\TypeShape;

#[CoversClass(TypeReader::class)]
#[UsesClass(TypeShape::class)]
final class TypeReaderTest extends TestCase
{
    public function testReadOfNothingIsUnknown(): void
    {
        self::assertSame('mixed', (new TypeReader())->read(null)->display());
    }

    public function testReadOfABuiltinName(): void
    {
        self::assertSame('int', (new TypeReader())->read(new Identifier('int'))->display());
    }

    public function testReadOfAClassName(): void
    {
        self::assertSame('App\\Status', (new TypeReader())->read(new Name('App\\Status'))->display());
    }

    public function testReadOfANullableTypeAddsNull(): void
    {
        self::assertSame('null|string', (new TypeReader())->read(new NullableType(new Identifier('string')))->display());
    }

    public function testReadOfAUnionCoversEveryAlternative(): void
    {
        $type = new UnionType([new Identifier('int'), new Identifier('string')]);
        self::assertSame('int|string', (new TypeReader())->read($type)->display());
    }

    public function testReadOfAnIntersectionCoversEveryPart(): void
    {
        $type = new IntersectionType([new Name('A'), new Name('B')]);
        self::assertSame('A|B', (new TypeReader())->read($type)->display());
    }

    public function testReadPartsOfNothingIsUnknown(): void
    {
        self::assertSame('mixed', (new TypeReader())->readParts([])->display());
    }
}
