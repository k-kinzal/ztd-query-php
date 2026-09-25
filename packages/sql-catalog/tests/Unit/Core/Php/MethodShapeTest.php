<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Php;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Php\MethodShape;
use SqlCatalog\Core\Type\TypeShape;

#[CoversClass(MethodShape::class)]
#[UsesClass(TypeShape::class)]
final class MethodShapeTest extends TestCase
{
    public function testQualifiedNameJoinsTheClassAndTheMethod(): void
    {
        $shape = new MethodShape('App\\Repository', 'find', [], TypeShape::of(['array']));
        self::assertSame('App\\Repository::find', $shape->qualifiedName());
    }

    public function testKeepsWhatWasDeclared(): void
    {
        $shape = new MethodShape('App\\Repository', 'find', [], TypeShape::of(['array']), true, null, 'src/a.php');
        self::assertTrue($shape->static);
        self::assertSame('src/a.php', $shape->file);
        self::assertNull($shape->node);
    }
}
