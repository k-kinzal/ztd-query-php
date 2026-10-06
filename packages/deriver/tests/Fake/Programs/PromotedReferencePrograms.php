<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Trusted PHP 8.3 constructor promotion, alias, and typed-property fixtures.
 * @visibility root
 */
final class PromotedReferencePrograms
{
    /**
     * @return array<string,array{string,string}> Source and independently expected JSON
     */
    public static function cases(): array
    {
        return [
            'caller write reaches property' => ['<?php class Box{function __construct(public int &$value){}}function target(){$x=1;$b=new Box($x);$x=2;return $b->value;}','2'],
            'property write reaches caller' => ['<?php class Box{function __construct(public int &$value){}}function target(){$x=1;$b=new Box($x);$b->value=2;return $x;}','2'],
            'constructor write reaches property' => ['<?php class Box{function __construct(public int &$value){$value=3;}}function target(){$x=1;$b=new Box($x);return [$x,$b->value];}','[3,3]'],
            'private promotion keeps alias' => ['<?php class Box{function __construct(private int &$value){}function read(){return $this->value;}}function target(){$x=1;$b=new Box($x);$x=4;return $b->read();}','4'],
            'protected promotion keeps alias' => ['<?php class Box{function __construct(protected int &$value){}function read(){return $this->value;}}function target(){$x=1;$b=new Box($x);$x=4;return $b->read();}','4'],
            'clone keeps shared reference' => ['<?php class Box{function __construct(public int &$value){}}function target(){$x=1;$a=new Box($x);$b=clone $a;$x=5;return [$a->value,$b->value];}','[5,5]'],
            'reference enforces live property type' => ['<?php class Box{function __construct(public int &$value){}}function target(){$x=1;$b=new Box($x);try{$x=[];}catch(TypeError $e){return [$x,$b->value];}return 99;}','[1,1]'],
            'unset removes property constraint' => ['<?php class Box{function __construct(public int &$value){}}function target(){$x=1;$b=new Box($x);unset($b->value);$x=[];return $x;}','[]'],
            'by value promotion stays independent' => ['<?php class Box{function __construct(public int $value){}}function target(){$x=1;$b=new Box($x);$x=2;return $b->value;}','1'],
            'reference readonly promotion rejected' => ['<?php class Box{function __construct(public readonly int &$value){}}function target(){$x=1;try{$b=new Box($x);return 99;}catch(Error $e){return $x;}}','1'],
            'reference readonly class rejected' => ['<?php readonly class Box{function __construct(public int &$value){}}function target(){$x=1;try{$b=new Box($x);return 99;}catch(Error $e){return $x;}}','1'],
            'array promotion keeps alias' => ['<?php class Box{function __construct(public array &$items){}}function target(){$x=[1];$b=new Box($x);$b->items[]=2;return $x;}','[1,2]'],
            'reference default aliases parameter and property' => ['<?php class Box{function __construct(public int &$value=1){$value=2;}}function target(){$b=new Box;return $b->value;}','2'],
            'reference named argument' => ['<?php class Box{function __construct(public int &$value){}}function target(){$x=1;$b=new Box(value:$x);$x=6;return $b->value;}','6'],
            'weak coercion precedes readonly failure' => ['<?php class Box{function __construct(public readonly int &$value){}}function target(){$x="1";try{$b=new Box($x);return 99;}catch(Error $e){return $x;}}','1'],
            'readonly promotion cannot reinitialize' => ['<?php class Box{function __construct(public readonly int $x){}}function target(){$b=new Box(1);try{$b->__construct(2);}catch(Error $e){return $b->x;}return 99;}','1'],
            'all parameter checks precede promotion' => ['<?php class Box{function __construct(public int $x,string $y){}}function target(){$b=new Box(1,"a");try{$b->__construct(2,[]);}catch(TypeError $e){return $b->x;}return 99;}','1'],
            'all coercions precede readonly failure' => ['<?php class Box{function __construct(public readonly int &$x,int &$y){}}function target(){$x=1;$y="2";try{new Box($x,$y);}catch(Error $e){return $y;}return 99;}','2'],
            'repeated promotion rebinds reference' => ['<?php class Box{function __construct(public int &$x){}}function target(){$x=1;$y=2;$b=new Box($x);$b->__construct($y);$x=3;$y=4;return [$x,$b->x];}','[3,4]'],
            'promotion reads latest coerced shared argument' => ['<?php class Box{function __construct(public &$x,float &$y){}}function target(){$v=1;$b=new Box($v,$v);return [$b->x,$v];}','[1.0,1.0]'],
            'parent constructor private promotion' => ['<?php class A{function __construct(private int &$x){}function read(){return $this->x;}}class B extends A{}function target(){$x=1;$b=new B($x);$x=9;return $b->read();}','9'],
            'trait constructor reference promotion' => ['<?php trait T{function __construct(private int &$x){}function read(){return $this->x;}}class B{use T;}function target(){$x=1;$b=new B($x);$x=9;return $b->read();}','9'],
        ];
    }
}
