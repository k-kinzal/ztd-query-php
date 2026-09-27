<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

/**
 * Property declarations, implicit calls, and PHP 8.3 object boundaries.
 */
#[CoversNothing]
#[Small]
final class PropertySemanticsTest extends TestCase
{
    /**
     * @param string $source
     * @param Term $expected
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[DataProvider('providerTargetSemantics')]
    public function testTargetSemantics(string $source, Term $expected): void
    {
        $result = Analysis::returns($source);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertSame([], $result->frontiers);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected->native(), $result->normalOutcomes[0]->values['return']->native());
        self::assertSame('closed', $result->assessment->closure);
    }

    /**
     * @return array<string, array{string, Term}>
     */
    public static function providerTargetSemantics(): array
    {
        return [
            'typedWeak' => ['<?php class B{public int $x=1;}function target(){$b=new B;$r=($b->x="42");return [$r,$b->x];}', Term::fromNative([42, 42])],
            'typedStrict' => ['<?php declare(strict_types=1);class B{public int $x=1;}function target(){$b=new B;try{$b->x="42";}catch(TypeError $e){return $b->x;}return 999;}', Term::fromNative(1)],
            'privateOutside' => ['<?php class B{private $x=1;}function target(){try{return (new B)->x;}catch(Error $e){return "denied";}}', Term::fromNative('denied')],
            'privateSlots' => ['<?php class A{private $x=1;function a(){return $this->x;}}class B extends A{private $x=2;function b(){return $this->x;}}function target(){$b=new B;return [$b->a(),$b->b()];}', Term::fromNative([1, 2])],
            'protectedParent' => ['<?php class A{protected $x=1;}class B extends A{function get(){return $this->x;}}function target(){return (new B)->get();}', Term::fromNative(1)],
            'staticDefault' => ['<?php class A{public static $x=3;static function f(){return self::$x;}}function target(){return A::f();}', Term::fromNative(3)],
            'staticInherited' => ['<?php class A{public static $x=3;}class B extends A{}function target(){B::$x=4;return [A::$x,B::$x];}', Term::fromNative([4, 4])],
            'staticLate' => ['<?php class A{public static $x=3;static function f(){return static::$x;}}class B extends A{public static $x=4;}function target(){return [A::f(),B::f()];}', Term::fromNative([3, 4])],
            'magicGetSet' => ['<?php class B{private $v=0;function __get($n){return $this->v;}function __set($n,$v){$this->v=$v*2;}}function target(){$b=new B;$r=($b->x=3);return [$r,$b->x];}', Term::fromNative([3, 6])],
            'magicIsset' => ['<?php class B{function __isset($n){return $n==="x";}function __get($n){return 3;}}function target(){$b=new B;return [isset($b->x),isset($b->y),$b->x??9,$b->y??9];}', Term::fromNative([true, false, 3, 9])],
            'magicCoalesceWithoutIsset' => ['<?php class B{function __get($n){return 3;}}function target(){$b=new B;return [isset($b->x),$b->x??9];}', Term::fromNative([false, 3])],
            'readonlyInitialize' => ['<?php class B{public readonly int $x;function set(){ $this->x=1;}}function target(){$b=new B;$b->set();try{$b->set();}catch(Error $e){return $b->x;}return 999;}', Term::fromNative(1)],
            'readonlyClone' => ['<?php class B{function __construct(public readonly int $x){}function __clone(){$this->x=2;}}function target(){$a=new B(1);$b=clone $a;return [$a->x,$b->x];}', Term::fromNative([1, 2])],
            'readonlyCloneTwice' => ['<?php class B{function __construct(public readonly int $x){}function __clone(){$this->x=2;$this->x=3;}}function target(){$a=new B(1);try{$b=clone $a;}catch(Error $e){return $a->x;}return 999;}', Term::fromNative(1)],
            'typedUnset' => ['<?php class B{public int $x=1;}function target(){$b=new B;unset($b->x);try{return $b->x;}catch(Error $e){return "uninitialized";}}', Term::fromNative('uninitialized')],
            'strictClosure' => ['<?php declare(strict_types=1);function f(int $x){return $x;}function target(){$g=fn()=>f("1");try{return $g();}catch(TypeError $e){return "strict";}}', Term::fromNative('strict')],
            'callableString' => ['<?php function f(){}function target(){return is_callable("f");}', Term::fromNative(true)],
            'enumObject' => ['<?php enum E{case A;}function target(){return is_object(E::A);}', Term::fromNative(true)],
            'arrayIdentityMetadata' => ['<?php function target(){$a=[1];$a[]=2;return $a===[1,2];}', Term::fromNative(true)],
            'arrayXor' => ['<?php function target(){return [1] xor [];}', Term::fromNative(true)],
            'traitProperty' => ['<?php trait T{public $x=4;}class B{use T;}function target(){return (new B)->x;}', Term::fromNative(4)],
        ];
    }
}
