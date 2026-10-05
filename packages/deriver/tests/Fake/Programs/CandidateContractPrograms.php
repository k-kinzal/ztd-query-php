<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Trusted closed fixtures for independent runtime comparison of candidate expansions.
 */
final class CandidateContractPrograms
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function cases(): iterable
    {
        yield 'conditional-finally' => ['<?php function h($x){try{return 1;}finally{if($x){return 2;}}}function target(){return [h(true),h(false)];}'];
        yield 'nested-finally' => ['<?php function target(){try{try{return 1;}finally{return 2;}}finally{return 3;}}'];
        yield 'clone-after-write' => ['<?php class A{public $x=1;}function target(){$a=new A;$a->x=7;$b=clone $a;$a->x=9;return [$a->x,$b->x];}'];
        yield 'nested-mutation' => ['<?php function target(){$a=["x"=>["y"=>1,"z"=>9]];$a["x"]["y"]+=2;unset($a["x"]["z"]);return $a;}'];
        yield 'interface-constant' => ['<?php interface I{const X=7;}class A implements I{}function target(){return A::X;}'];
        yield 'named-callback' => ['<?php function h($a,$b){return $a-$b;}function target(){return call_user_func_array("h",["b"=>3,"a"=>8]);}'];
        yield 'known-array-unpack' => ['<?php function h($a,$b){return $a-$b;}function target(){return h(...["b"=>3,"a"=>8]);}'];
        yield 'invokable-object' => ['<?php class A{function __invoke($x){return $x+1;}}function target(){return call_user_func(new A,4);}'];
        yield 'enum-properties' => ['<?php enum A:string{case One="one";}function target(){return [A::One->name,A::One->value];}'];
        yield 'address' => ['<?php class A{public $x=1;}function target(){$a=new A;$b=$a;$b->x=9;return $a->x;}'];
        yield 'incoming' => ['<?php class A{public $x=1;function set($x){$this->x=$x;}function get(){return $this->x;}}function target(){$a=new A;$a->set(7);return $a->get();}'];
        yield 'effect' => ['<?php class A{public $x=1;function set($x){$this->x=$x;}}function target(){$a=new A;$a->set(7);return $a->x;}'];
        yield 'selected' => ['<?php class A{public $x=1;function __construct($x){$this->x=$x;}}class B extends A{function __construct(){parent::__construct(7);}}function target(){return (new B)->x;}'];
    }
}
