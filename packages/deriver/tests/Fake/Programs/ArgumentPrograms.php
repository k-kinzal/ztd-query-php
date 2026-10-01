<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Independent PHP 8.3 observations for argument evaluation.
 * @visibility root
 */
final class ArgumentPrograms
{
    /**
     * Supplies trusted programs and observations obtained from PHP 8.3.
     * @return array<string, array{string, string, string, bool}> Source, normal returns, exception class, diagnostic presence
     */
    public static function cases(): array
    {
        return [
            'nullable-property' => ['<?php class Box{public ?int $x;}function change(&$x){$x=2;}function target(){$b=new Box;change($b->x);return $b->x;}', '[2]', '', false],
            'absent-array' => ['<?php function change(&$x){$x=2;}function target(){$a=[];change($a[0]);return $a;}', '[[2]]', '', false],
            'absent-variable' => ['<?php function change(&$x){$x=2;}function target(){change($a);return $a;}', '[2]', '', false],
            'string-reference' => ['<?php function change(&$x){$x="X";}function target(){$a="abc";try{change($a[0]);}catch(Error $e){return $a;}return 999;}', '["abc"]', '', false],
            'named-nullable' => ['<?php class Box{public ?int $x;}function change($ignored,&$x){$x=2;}function target(){$b=new Box;change(x:$b->x,ignored:1);return $b->x;}', '[2]', '', false],
            'byref-later-write' => ['<?php function consume(int &$x,$other){return [$x,$other];}function target(){$a=1;try{return consume($a,($a="bad"));}catch(TypeError $e){return $a;}}', '["bad"]', '', false],
            'byref-later-coercion' => ['<?php function consume(int &$x,$other){return [$x,$other];}function target(){$a="1";return [consume($a,($a="2")),$a];}', '[[[2,"2"],2]]', '', false],
            'byval-later-write' => ['<?php function consume($x,$other){return [$x,$other];}function target(){$a=1;return consume($a,($a=2));}', '[[1,2]]', '', false],
            'reference-rebind-later' => ['<?php function consume(&$x,$other){$x=3;return $other;}function target(){$a=1;$b=2;consume($a,($a=&$b));return [$a,$b];}', '[[2,2]]', '', false],
            'inaccessible-method-order' => ['<?php class Box{private function hidden($x){}}function target(){$a=1;$b=new Box;try{$b->hidden($a=2);}catch(Error $e){return $a;}return 999;}', '[1]', '', false],
            'unpack:local' => ['<?php function f(&$x){$x=2;}function target(){$a=[1];f(...$a);return $a;}', '[[2]]', '', false],
            'unpack:property' => ['<?php class B{public array $x=[1];}function f(&$x){$x=2;}function target(){$b=new B;f(...$b->x);return $b->x;}', '[[1]]', '', false],
            'unpack:readonly' => ['<?php class B{public readonly array $x;function __construct(){$this->x=[1];}}function f(&$x){$x=2;}function target(){$b=new B;f(...$b->x);return $b->x;}', '[[1]]', '', false],
            'unpack:literal' => ['<?php function f(&$x){$x=2;return $x;}function target(){return f(...[1]);}', '[2]', '', false],
            'unpack:call-temp' => ['<?php function g(){return 1;}function f(&$x){$x=2;return $x;}function target(){return f(g());}', '[2]', '', true],
            'unpack:new-temp' => ['<?php function f(&$x){$x=2;return $x;}function target(){return f(new stdClass);}', '[2]', '', true],
            'unpack:array-element' => ['<?php function f(&$x){$x=2;}function target(){$a=[[1]];f(...$a[0]);return $a;}', '[[[1]]]', '', false],
            'unpack:call-array' => ['<?php function f(&$x){$x=2;return $x;}function g(){return [1];}function target(){return f(...g());}', '[2]', '', false],
            'unpack:cell-snapshot' => ['<?php function f($x,$later){return $x;}function target(){$x=1;$a=[&$x];return [f(...$a,later:($x=2)),$x];}', '[[1,2]]', '', false],
            'unpack:byref-unpack-later' => ['<?php function f(&$x,$later){return $x;}function target(){$x=1;$a=[&$x];return [f(...$a,later:($x=2)),$x];}', '[[2,2]]', '', false],
            'extra:method-reference' => ['<?php class Box{function set(&$x){$x=2;}}function target(){$b=new Box;$a=[];$b->set($a[0]);return $a;}', '[[2]]', '', false],
            'extra:static-reference' => ['<?php class Box{static function set(&$x){$x=2;}}function target(){$a=[];Box::set($a[0]);return $a;}', '[[2]]', '', false],
            'extra:constructor-reference' => ['<?php class Box{function __construct(&$x){$x=2;}}function target(){$a=[];$b=new Box($a[0]);return $a;}', '[[2]]', '', false],
            'extra:closure-reference' => ['<?php function target(){$f=function(&$x){$x=2;};$a=[];$f($a[0]);return $a;}', '[[2]]', '', false],
            'extra:array-callable-reference' => ['<?php class Box{function set(&$x){$x=2;}}function target(){$f=[new Box,"set"];$a=[];$f($a[0]);return $a;}', '[[2]]', '', false],
            'extra:invokable-reference' => ['<?php class Box{function __invoke(&$x){$x=2;}}function target(){$f=new Box;$a=[];$f($a[0]);return $a;}', '[[2]]', '', false],
            'extra:variadic-reference' => ['<?php function set(&...$xs){$xs[0]=2;$xs["named"]=3;}function target(){$a=[];set($a[0],named:$a[1]);return $a;}', '[[2,3]]', '', false],
            'extra:unpack-named-reference' => ['<?php function set(&$x,&$y){$x=2;$y=3;}function target(){$a=["y"=>1,"x"=>0];set(...$a);return $a;}', '[{"y":3,"x":2}]', '', false],
            'extra:unpack-variadic-reference' => ['<?php function set(&...$xs){$xs[0]=2;$xs["named"]=3;}function target(){$a=[0,"named"=>1];set(...$a);return $a;}', '[{"0":2,"named":3}]', '', false],
            'extra:nullable-unpack-read' => ['<?php class Box{public ?array $a;}function set(&$x){}function target(){$b=new Box;try{set(...$b->a);}catch(Error $e){return 2;}return 3;}', '[2]', '', false],
            'extra:readonly-direct' => ['<?php class Box{public readonly int $x;function __construct(){$this->x=1;}}function set(&$x){$x=2;}function target(){$b=new Box;try{set($b->x);}catch(Error $e){return $b->x;}return 999;}', '[1]', '', false],
            'extra:nonnullable-uninitialized' => ['<?php class Box{public int $x;}function set(&$x){$x=2;}function target(){$b=new Box;try{set($b->x);}catch(Error $e){return isset($b->x);}return 999;}', '[false]', '', false],
            'extra:literal-reference-before-later-effects' => ['<?php function set(&$x,$other){}function target(){$a=1;try{set(2,$a=3);}catch(Error $e){return $a;}return 999;}', '[1]', '', false],
            'extra:returned-reference-freeze' => ['<?php function &ref(&$x){return $x;}function pair($x,$y){return [$x,$y];}function target(){$a=1;return pair(ref($a),$a=2);}', '[[1,2]]', '', false],
            'extra:returned-reference-current' => ['<?php function &ref(&$x){return $x;}function pair(&$x,$y){return [$x,$y];}function target(){$a=1;return pair(ref($a),$a=2);}', '[[2,2]]', '', false],
            'extra:returned-reference-array-unpack' => ['<?php function &ref(&$x){return $x;}function set(&$x){$x=2;}function target(){$a=[1];set(...ref($a));return $a;}', '[[2]]', '', false],
            'extra:missing-reference-null' => ['<?php function untouched(&$x){}function target(){untouched($a);return $a;}', '[null]', '', false],
            'extra:missing-alias-null' => ['<?php function target(){$b=&$a;return [$a,$b];}', '[[null,null]]', '', false],
            'extra:missing-capture-null' => ['<?php function target(){$f=function()use(&$a){return $a;};return [$a,$f()];}', '[[null,null]]', '', false],
            'extra:private-constructor-early' => ['<?php class Box{private function __construct($x){}}function target(){$a=1;try{new Box($a=2);}catch(Error $e){return $a;}return 999;}', '[1]', '', false],
            'extra:abstract-constructor-early' => ['<?php abstract class Box{}function target(){$a=1;try{new Box($a=2);}catch(Error $e){return $a;}return 999;}', '[1]', '', false],
            'extra:static-instance-early' => ['<?php class Box{function foo($x){}}function target(){$a=1;try{Box::foo($a=2);}catch(Error $e){return $a;}return 999;}', '[1]', '', false],
            'extra:magic-value-arguments' => ['<?php class Box{function __call($name,$args){return $args;}}function target(){$a=1;return (new Box)->missing($a,$a=2);}', '[[1,2]]', '', false],
            'extra:arrayaccess:False:False:False' => ['<?php class Box implements ArrayAccess{public $x=1;public $log=[];public function offsetExists(mixed $key):bool{return true;}public function offsetGet(mixed $key):mixed{$this->log[]="get";return $this->x;}public function offsetSet(mixed $key,mixed $v):void{$this->log[]="set";$this->x=$v;}public function offsetUnset(mixed $key):void{}}function consume($x,$other){$x=3;return $other;}function target(){$b=new Box;consume($b[0],0);return [$b->x,$b->log];}', '[[1,["get"]]]', '', false],
            'extra:arrayaccess:False:False:True' => ['<?php class Box implements ArrayAccess{public $x=1;public $log=[];public function offsetExists(mixed $key):bool{return true;}public function offsetGet(mixed $key):mixed{$this->log[]="get";return $this->x;}public function offsetSet(mixed $key,mixed $v):void{$this->log[]="set";$this->x=$v;}public function offsetUnset(mixed $key):void{}}function consume($x,$other){$x=3;return $other;}function target(){$b=new Box;consume($b[0],$b->x=2);return [$b->x,$b->log];}', '[[2,["get"]]]', '', false],
            'extra:arrayaccess:False:True:False' => ['<?php class Box implements ArrayAccess{public $x=1;public $log=[];public function offsetExists(mixed $key):bool{return true;}public function offsetGet(mixed $key):mixed{$this->log[]="get";return $this->x;}public function offsetSet(mixed $key,mixed $v):void{$this->log[]="set";$this->x=$v;}public function offsetUnset(mixed $key):void{}}function consume(&$x,$other){$x=3;return $other;}function target(){$b=new Box;consume($b[0],0);return [$b->x,$b->log];}', '[[1,["get"]]]', '', true],
            'extra:arrayaccess:False:True:True' => ['<?php class Box implements ArrayAccess{public $x=1;public $log=[];public function offsetExists(mixed $key):bool{return true;}public function offsetGet(mixed $key):mixed{$this->log[]="get";return $this->x;}public function offsetSet(mixed $key,mixed $v):void{$this->log[]="set";$this->x=$v;}public function offsetUnset(mixed $key):void{}}function consume(&$x,$other){$x=3;return $other;}function target(){$b=new Box;consume($b[0],$b->x=2);return [$b->x,$b->log];}', '[[2,["get"]]]', '', true],
            'extra:arrayaccess:True:False:False' => ['<?php class Box implements ArrayAccess{public $x=1;public $log=[];public function offsetExists(mixed $key):bool{return true;}public function &offsetGet(mixed $key):mixed{$this->log[]="get";return $this->x;}public function offsetSet(mixed $key,mixed $v):void{$this->log[]="set";$this->x=$v;}public function offsetUnset(mixed $key):void{}}function consume($x,$other){$x=3;return $other;}function target(){$b=new Box;consume($b[0],0);return [$b->x,$b->log];}', '[[1,["get"]]]', '', false],
            'extra:arrayaccess:True:False:True' => ['<?php class Box implements ArrayAccess{public $x=1;public $log=[];public function offsetExists(mixed $key):bool{return true;}public function &offsetGet(mixed $key):mixed{$this->log[]="get";return $this->x;}public function offsetSet(mixed $key,mixed $v):void{$this->log[]="set";$this->x=$v;}public function offsetUnset(mixed $key):void{}}function consume($x,$other){$x=3;return $other;}function target(){$b=new Box;consume($b[0],$b->x=2);return [$b->x,$b->log];}', '[[2,["get"]]]', '', false],
            'extra:arrayaccess:True:True:False' => ['<?php class Box implements ArrayAccess{public $x=1;public $log=[];public function offsetExists(mixed $key):bool{return true;}public function &offsetGet(mixed $key):mixed{$this->log[]="get";return $this->x;}public function offsetSet(mixed $key,mixed $v):void{$this->log[]="set";$this->x=$v;}public function offsetUnset(mixed $key):void{}}function consume(&$x,$other){$x=3;return $other;}function target(){$b=new Box;consume($b[0],0);return [$b->x,$b->log];}', '[[3,["get"]]]', '', false],
            'extra:arrayaccess:True:True:True' => ['<?php class Box implements ArrayAccess{public $x=1;public $log=[];public function offsetExists(mixed $key):bool{return true;}public function &offsetGet(mixed $key):mixed{$this->log[]="get";return $this->x;}public function offsetSet(mixed $key,mixed $v):void{$this->log[]="set";$this->x=$v;}public function offsetUnset(mixed $key):void{}}function consume(&$x,$other){$x=3;return $other;}function target(){$b=new Box;consume($b[0],$b->x=2);return [$b->x,$b->log];}', '[[3,["get"]]]', '', false],
            'shared-parameter-coercion' => ['<?php function consume(int &$a,float &$b){return [$a,$b];}function target(){$a="1.5";return [consume($a,$a),$a];}', '[[[1.0,1.0],1.0]]', '', true],
            'shared-variadic-coercion' => ['<?php function consume(int &$a,float &...$b){return [$a,$b];}function target(){$a="1.5";return [consume($a,$a),$a];}', '[[[1.0,[1.0]],1.0]]', '', true],
            'coercion:union-preserve-int' => ['<?php function consume(float|int $x){return $x;}function target(){return consume(1);}', '[1]', '', false],
            'coercion:union-decimal-string' => ['<?php function consume(int|float $x){return $x;}function target(){return consume("1.0");}', '[1.0]', '', false],
            'coercion:union-exponent-string' => ['<?php function consume(int|float $x){return $x;}function target(){return consume("1e3");}', '[1000.0]', '', false],
            'coercion:union-integer-string' => ['<?php function consume(float|int $x){return $x;}function target(){return consume("001");}', '[1]', '', false],
            'coercion:union-integer-max' => ['<?php function consume(float|int $x){return $x;}function target(){return consume("9223372036854775807");}', '[9223372036854775807]', '', false],
            'coercion:integer-max' => ['<?php function consume(int $x){return $x;}function target(){return consume("9223372036854775807");}', '[9223372036854775807]', '', false],
            'coercion:integer-min' => ['<?php function consume(int $x){return $x;}function target(){return consume("-9223372036854775808");}', '[-9223372036854775808]', '', false],
            'coercion:integer-overflow' => ['<?php function consume(int $x){return $x;}function target(){return consume("9223372036854775808");}', '[]', 'TypeError', false],
            'coercion:float-parameter-warning' => ['<?php function consume(int $x){return $x;}function target(){return consume(1.5);}', '[1]', '', true],
            'coercion:float-return-warning' => ['<?php function consume():int{return 1.5;}function target(){return consume();}', '[1]', '', true],
            'coercion:float-property-warning' => ['<?php class Box{public int $x;}function target(){$b=new Box;$b->x=1.5;return $b->x;}', '[1]', '', true],
            'coercion:float-reference-property-warning' => ['<?php class Box{public int $x=1;}function target(){$b=new Box;$a=&$b->x;$a=1.5;return $b->x;}', '[1]', '', true],
            'coercion:float-reference-property-bind-warning' => ['<?php class Box{public int $x;}function target(){$b=new Box;$a=1.5;$b->x=&$a;return [$a,$b->x];}', '[[1,1]]', '', true],
            'coercion:float-explicit-cast' => ['<?php function target(){return (int)1.5;}', '[1]', '', false],
            'coercion:arithmetic-integer-max' => ['<?php function target(){return "9223372036854775807"+0;}', '[9223372036854775807]', '', false],
            'coercion:reference-default' => ['<?php function consume(int &$x=2){$x++;return $x;}function target(){return [consume(),consume()];}', '[[3,3]]', '', false],
        ];
    }
}
