<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Php;

use PhpParser\Node\Scalar\String_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Php\ClassShape;

#[CoversClass(ClassShape::class)]
final class ClassShapeTest extends TestCase
{
    public function testAncestorsListTheParentTheTraitsAndTheInterfaces(): void
    {
        $shape = new ClassShape('App\\User', 'App\\Base', ['App\\Jsonable'], ['App\\Timestamps'], false, [], [], [], []);
        self::assertSame(['App\\Base', 'App\\Timestamps', 'App\\Jsonable'], $shape->ancestors());
    }

    public function testAncestorsOfAClassWithoutAParent(): void
    {
        $shape = new ClassShape('App\\User', null, [], [], false, [], [], [], []);
        self::assertSame([], $shape->ancestors());
    }

    public function testSettledDefaultAnswersForAPropertyNothingOverwrites(): void
    {
        $default = new String_('users');
        $shape = new ClassShape('App\\User', null, [], [], false, [], [], [], [], ['table' => $default], []);
        self::assertSame($default, $shape->settledDefault('table'));
    }

    public function testSettledDefaultIsNullForAPropertyTheClassAssignsTo(): void
    {
        $shape = new ClassShape(
            'App\\User',
            null,
            [],
            [],
            false,
            [],
            [],
            [],
            [],
            ['table' => new String_('users')],
            ['table' => true],
        );
        self::assertNull($shape->settledDefault('table'));
    }

    public function testSettledDefaultIsNullForAPropertyWithoutOne(): void
    {
        $shape = new ClassShape('App\\User', null, [], [], false, [], [], [], []);
        self::assertNull($shape->settledDefault('table'));
    }
}
