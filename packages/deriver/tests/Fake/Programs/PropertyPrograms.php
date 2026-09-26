<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Records independently observed PHP 8.3 property values and caught errors.
 * @visibility root
 */
final class PropertyPrograms
{
    /**
     * @return array<string,array{string,string}> Trusted source and recorded JSON result
     */
    public static function cases(): array
    {
        return [
            'typed uninitialized read' => ['<?php class B{public int $x;}function target(){$b=new B;try{return $b->x;}catch(Error $e){return "uninitialized";}}', '"uninitialized"'],
            'nullable uninitialized silent' => ['<?php class B{public ?int $x;}function target(){$b=new B;return [isset($b->x),$b->x??8];}', '[false,8]'],
            'typed reference rejects later write' => ['<?php class B{public int $x=1;}function target(){$b=new B;$r=&$b->x;try{$r="word";}catch(TypeError $e){return [$b->x,$r];}return 999;}', '[1,1]'],
            'nullable reference initializes' => ['<?php class B{public ?int $x;}function target(){$b=new B;$r=&$b->x;return [$b->x,$r];}', '[null,null]'],
            'nonnullable reference fails' => ['<?php class B{public int $x;}function target(){$b=new B;try{$r=&$b->x;}catch(Error $e){return isset($b->x);}return 999;}', 'false'],
            'alias coerces source' => ['<?php class B{public int $x=1;}function target(){$b=new B;$r="42";$b->x=&$r;return [$b->x,$r];}', '[42,42]'],
            'increment typed property' => ['<?php class B{public int $x=1;}function target(){$b=new B;return [$b->x++,$b->x,++$b->x];}', '[1,2,3]'],
            'increment typed uninitialized' => ['<?php class B{public int $x;}function target(){$b=new B;try{$b->x++;}catch(Error $e){return isset($b->x);}return 999;}', 'false'],
            'readonly unset inside before initialization' => ['<?php class B{public readonly int $x;function run(){unset($this->x);$this->x=4;return $this->x;}}function target(){return (new B)->run();}', '4'],
            'readonly unset outside before initialization' => ['<?php class B{public readonly int $x;}function target(){$b=new B;try{unset($b->x);}catch(Error $e){return "blocked";}return "allowed";}', '"blocked"'],
            'readonly unset after initialization' => ['<?php class B{public readonly int $x;function __construct(){$this->x=4;}function run(){try{unset($this->x);}catch(Error $e){return $this->x;}return 999;}}function target(){return (new B)->run();}', '4'],
            'readonly initialize outside' => ['<?php class B{public readonly int $x;}function target(){$b=new B;try{$b->x=4;}catch(Error $e){return isset($b->x);}return 999;}', 'false'],
            'readonly clone writes once' => ['<?php class B{function __construct(public readonly int $x){}function __clone(){$this->x=2;try{$this->x=3;}catch(Error $e){}}}function target(){$a=new B(1);$b=clone $a;return [$a->x,$b->x];}', '[1,2]'],
            'readonly failed clone type retains opportunity' => ['<?php class B{function __construct(public readonly int $x){}function __clone(){try{$this->x=[];}catch(TypeError $e){}$this->x=3;}}function target(){$a=new B(1);$b=clone $a;return [$a->x,$b->x];}', '[1,3]'],
            'static array default' => ['<?php class B{public static array $a=[1];}function target(){B::$a[]=2;return B::$a;}', '[1,2]'],
            'static multiple defaults' => ['<?php class B{public static int $x=1;public static int $y=2;}function target(){B::$x=9;return [B::$x,B::$y,B::$x];}', '[9,2,9]'],
            'static null default' => ['<?php class B{public static $x;}function target(){return B::$x;}', 'null'],
            'static typed no default' => ['<?php class B{public static int $x;}function target(){try{return B::$x;}catch(Error $e){return "uninitialized";}}', '"uninitialized"'],
            'static default throws' => ['<?php class B{public static int $x=1/0;}function target(){try{return B::$x;}catch(DivisionByZeroError $e){return "division";}}', '"division"'],
            'static missing property' => ['<?php class B{}function target(){try{return B::$missing;}catch(Error $e){return "missing";}}', '"missing"'],
            'instance property as static' => ['<?php class B{public int $x=1;}function target(){try{return B::$x;}catch(Error $e){return "instance";}}', '"instance"'],
            'inaccessible public read' => ['<?php class B{private int $x=1;}function target(){$b=new B;try{return $b->x;}catch(Error $e){return "private";}}', '"private"'],
            'visible silent read' => ['<?php class B{public int $x=3;}function target(){$b=new B;return [isset($b->x),$b->x??8];}', '[true,3]'],
            'magic private read' => ['<?php class B{private int $x=1;function __get($name){return "magic:".$name;}}function target(){return (new B)->x;}', '"magic:x"'],
            'magic missing read' => ['<?php class B{function __get($name){return "magic:".$name;}}function target(){return (new B)->x;}', '"magic:x"'],
            'magic not used for visible property' => ['<?php class B{public int $x=1;function __get($name){return 99;}}function target(){return (new B)->x;}', '1'],
            'magic inaccessible write' => ['<?php class B{private int $x=1;public int $seen=0;function __set($name,$value){$this->seen=$value;}}function target(){$b=new B;$b->x=7;return $b->seen;}', '7'],
            'readonly indirect array write' => ['<?php class B{function __construct(public readonly array $x){}}function target(){$b=new B([1]);try{$b->x[]=2;}catch(Error $e){return $b->x;}return 999;}', '[1]'],
            'readonly object handle mutation' => ['<?php class C{public int $x=1;}class B{function __construct(public readonly C $value){}}function target(){$b=new B(new C);$b->value->x=2;return $b->value->x;}', '2'],
            'scalar property write fails' => ['<?php function target(){$a=1;try{$a->x=2;}catch(Error $e){return $a;}return 999;}', '1'],
            'typed unset then initialize' => ['<?php class B{public int $x=1;}function target(){$b=new B;unset($b->x);$b->x=2;return $b->x;}', '2'],
        ];
    }
}
