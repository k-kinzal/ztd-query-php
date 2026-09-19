<?php

declare(strict_types=1);

namespace Tests\Unit\Php;

use PhpParser\Node\Scalar\String_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Php\ParameterShape;
use SqlCatalog\Type\TypeShape;

#[CoversClass(ParameterShape::class)]
#[UsesClass(TypeShape::class)]
final class ParameterShapeTest extends TestCase
{
    public function testKeepsWhatWasDeclared(): void
    {
        $default = new String_('users');
        $shape = new ParameterShape('table', TypeShape::of(['string']), $default, true);
        self::assertSame('table', $shape->name);
        self::assertSame('string', $shape->type->display());
        self::assertSame($default, $shape->default);
        self::assertTrue($shape->variadic);
    }

    public function testDefaultsToNoDefaultAndNotVariadic(): void
    {
        $shape = new ParameterShape('id', TypeShape::of(['int']));
        self::assertNull($shape->default);
        self::assertFalse($shape->variadic);
    }
}
