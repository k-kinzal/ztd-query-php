<?php

declare(strict_types=1);

namespace Tests\Unit\Php;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Php\FunctionShape;
use SqlCatalog\Type\TypeShape;

#[CoversClass(FunctionShape::class)]
#[UsesClass(TypeShape::class)]
final class FunctionShapeTest extends TestCase
{
    public function testKeepsWhatWasDeclared(): void
    {
        $shape = new FunctionShape('App\\find', [], TypeShape::of(['string']), null, 'src/a.php');
        self::assertSame('App\\find', $shape->name);
        self::assertSame('string', $shape->returnType->display());
        self::assertSame('src/a.php', $shape->file);
    }
}
