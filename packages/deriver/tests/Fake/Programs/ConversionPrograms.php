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
        ];
    }
}
