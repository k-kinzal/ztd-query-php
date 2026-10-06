<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

use Deriver\Value\Term;

/**
 * Trusted scalar conversion fixtures with explicit runtime observations.
 * @visibility root
 */
final class ConversionPrograms
{
    /**
     * @return array<string, array{string, Term}> Source programs and expected concrete observations
     */
    public static function cases(): array
    {
        return [
            'unpack stops on append overflow' => ['<?php function target(){try{return [PHP_INT_MAX=>1,...[2,3]];}catch(Error $e){return "error";}}', Term::constant('error')],
            'unpack scalar rejects input' => ['<?php function target(){$value=7;try{return [...$value];}catch(Error $e){return get_class($e);}}', Term::constant('Error')],
            'unpack closure rejects input' => ['<?php function target(){try{return [...(fn()=>1)];}catch(TypeError $e){return "error";}}', Term::constant('error')],
            'count array rejects invalid mode' => ['<?php function target(){try{return count([],2);}catch(ValueError $e){return "error";}}', Term::constant('error')],
            'implode rejects two arrays' => ['<?php function target(){try{return implode(["a"],["b"]);}catch(TypeError $e){return "error";}}', Term::constant('error')],
            'implode rejects single string' => ['<?php function target(){try{return implode(",");}catch(TypeError $e){return "error";}}', Term::constant('error')],
            'implode explicit null overload' => ['<?php function target(){return implode(["a",2],null);}', Term::constant('a2')],
            'explode empty separator' => ['<?php function target(){try{return explode("","abc");}catch(ValueError $e){return "error";}}', Term::constant('error')],
            'array callback method' => ['<?php class B{public int $n=0;function twice(int $v):int{$this->n++;return 2*$v;}}function target(){$b=new B;return [array_map([$b,"twice"],[2,3]),$b->n];}', Term::fromNative([[4, 6], 2])],
            'public method callable predicate' => ['<?php class B{function run(){}}function target(){$b=new B;return [is_callable([$b,"run"]),is_callable("B::run")];}', Term::fromNative([true, false])],
            'countable method effects' => ['<?php class B implements Countable{public int $n=0;function count():int{$this->n++;return 4;}}function target(){$b=new B;return [count($b),$b->n];}', Term::fromNative([4, 1])],
            'countable method throws' => ['<?php class B implements Countable{public int $n=0;function count():int{$this->n=8;throw new Error;}}function target(){$b=new B;try{count($b);}catch(Error $e){return $b->n;}}', Term::constant(8)],
            'countable rejects mode before effects' => ['<?php class B implements Countable{public int $n=0;function count():int{$this->n++;return 4;}}function target(){$b=new B;try{count($b,99);}catch(ValueError $e){return $b->n;}}', Term::constant(0)],
            'strict internal null' => ['<?php declare(strict_types=1);function target(){try{return strlen(null);}catch(TypeError $e){return "error";}}', Term::constant('error')],
            'invalid array key exists key' => ['<?php function target(){try{return array_key_exists([],[]);}catch(TypeError $e){return "error";}}', Term::constant('error')],
            'invalid array callback' => ['<?php function target(){try{return array_map(7,[1]);}catch(TypeError $e){return "error";}}', Term::constant('error')],
            'automatic Stringable interface' => ['<?php class B{function __toString():string{return "ok";}}function pass(Stringable $b){return (string)$b;}function target(){return pass(new B);}', Term::constant('ok')],
            'source string method effects' => ['<?php class B{public int $n=0; function __toString():string{$this->n++;return "ok";}} function target(){$b=new B; $s=(string)$b; return [$s,$b->n];}', Term::fromNative(['ok', 1])],
            'concatenation invokes both operands' => ['<?php class B{public int $n=0; function __toString():string{$this->n++;return (string)$this->n;}} function target(){$b=new B; $s=$b.$b; return [$s,$b->n];}', Term::fromNative(['12', 2])],
            'source string method throws after writing' => ['<?php class B{public int $n=0; function __toString():string{$this->n=3;throw new RuntimeException;} } function target(){$b=new B; try{$s=(string)$b;}catch(RuntimeException $e){return $b->n;}}', Term::constant(3)],
            'implicit string return declaration' => ['<?php class B{function __toString(){return 42;}} function target(){return (string)new B;}', Term::constant('42')],
            'strict implicit string return' => ['<?php declare(strict_types=1); class B{function __toString(){return 42;}} function target(){try{return (string)new B;}catch(TypeError $e){return "error";}}', Term::constant('error')],
            'invalid implicit string return' => ['<?php class B{function __toString(){return [];}} function target(){try{return (string)new B;}catch(TypeError $e){return "error";}}', Term::constant('error')],
            'object without string method' => ['<?php class B{} function target(){try{return (string)new B;}catch(Error $e){return "error";}}', Term::constant('error')],
            'closure string cast' => ['<?php function target(){try{return (string)(fn()=>1);}catch(Error $e){return "error";}}', Term::constant('error')],
            'enum string cast' => ['<?php enum B:string{case A="a";} function target(){try{return (string)B::A;}catch(Error $e){return "error";}}', Term::constant('error')],
            'zero base power keeps the infinity sign' => ['<?php function target(){$z=0.0;return [0 ** -1 > PHP_INT_MAX, (-$z) ** -1 < 0, (-$z) ** -2 > 0, (-0.0) ** -2.5 > 0, $z ** -0.5 > 0];}', Term::fromNative([true, true, true, true, true])],
            'not a number is true' => ['<?php function target(){$n=1e308 * 10 - 1e308 * 10;return [$n ? 1 : 2, $n xor false, !$n];}', Term::fromNative([1, true, false])],
            'unary signs keep negative zero' => ['<?php function target(){$z=0.0;return [(-$z) ** -1 < 0, (+"-0.0") ** -1 < 0, (-(-$z)) ** -1 > 0];}', Term::fromNative([true, true, true])],
        ];
    }
}
