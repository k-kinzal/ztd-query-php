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
 * Independent semantic fixtures for control, memory, and calls.
 */
#[CoversNothing]
#[Small]
final class ConcreteSemanticsTest extends TestCase
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
            'overwrite' => ['<?php function target() { $x="before"; $x="after"; return $x . ":done"; }', Term::fromNative('after:done')],
            'callContexts' => ['<?php function tagged(string $tag,int $id) { return $tag . ":" . $id; } function target() { return [tagged("user",1),tagged("item",2)]; }', Term::fromNative(['user:1', 'item:2'])],
            'clone' => ['<?php final class Box {public string $value="initial";} function target() {$a=new Box;$b=$a;$b->value="shared";$c=clone $a;$c->value="cloned";return [$a->value,$b->value,$c->value];}', Term::fromNative(['shared', 'shared', 'cloned'])],
            'capture' => ['<?php function target() {$x="first";$a=fn()=>$x;$b=function()use(&$x){return $x;};$x="second";return [$a(),$b()];}', Term::fromNative(['first', 'second'])],
            'reference' => ['<?php function replace(string &$x) {$x="changed";} function target() {$x="original";replace($x);return $x;}', Term::fromNative('changed')],
            'finally' => ['<?php function target() {$x="before";try{return $x;}finally{$x="after";}}', Term::fromNative('before')],
            'finallyOverride' => ['<?php function target(){try{return "before";}finally{return "after";}}', Term::fromNative('after')],
            'foreach' => ['<?php function target() {$parts=[];foreach(["a","b","c"] as $x){$parts[]="[".$x."]";}return $parts;}', Term::fromNative(['[a]', '[b]', '[c]'])],
            'recursion' => ['<?php function rep(int $n){if($n<=0)return "";return "x".rep($n-1);} function target(){return rep(2);}', Term::fromNative('xx')],
            'named' => ['<?php function f($a,$b="default",...$rest){return [$a,$b,$rest];} function target(){return f(b:"B",a:"A",extra:3);}', Term::fromNative(['A', 'B', ['extra' => 3]])],
            'byrefForeach' => ['<?php function target(){ $a=[1,2,3];foreach($a as &$x){}$x=4;unset($x);$x=5;return $a;}', Term::fromNative([1, 2, 4])],
            'arrayReferenceCopy' => ['<?php function target(){$x=1;$a=[&$x];$b=$a;$b[0]=2;return [$x,$a,$b];}', Term::fromNative([2, [2], [2]])],
            'arrayValueCopy' => ['<?php function target(){$a=[1];$b=$a;$b[0]=2;return [$a,$b];}', Term::fromNative([[1], [2]])],
            'shortCircuit' => ['<?php function target(){$x=0;false && ++$x;true || ++$x;return $x;}', Term::fromNative(0)],
            'catch' => ['<?php function target(){$x="before";try{$x="after";throw new RuntimeException();$x="wrong";}catch(RuntimeException $e){return $x;}}', Term::fromNative('after')],
            'map' => ['<?php function target(){return array_map(fn($x)=>$x*2,[1,2,3]);}', Term::fromNative([2, 4, 6])],
            'filter' => ['<?php function target(){return array_filter([1,2,3],fn($x)=>$x>1);}', Term::fromNative([1 => 2, 2 => 3])],
            'reduce' => ['<?php function target(){return array_reduce([1,2,3],fn($a,$b)=>$a+$b,0);}', Term::fromNative(6)],
            'constructor' => ['<?php class Box{function __construct(public string $value){}}function target(){return (new Box("yes"))->value;}', Term::fromNative('yes')],
            'loop' => ['<?php function target(){$s="";for($i=0;$i<3;$i++){$s.="x";}return $s;}', Term::fromNative('xxx')],
            'switch' => ['<?php function target(){$x="";switch(2){case 1:$x.="a";case 2:$x.="b";case 3:$x.="c";break;default:$x="d";}return $x;}', Term::fromNative('bc')],
            'match' => ['<?php function target(){return match(2){1=>"a",2=>"b",default=>"c"};}', Term::fromNative('b')],
            'static' => ['<?php function f(){static $x=0;return ++$x;}function target(){return [f(),f()];}', Term::fromNative([1, 2])],
            'nullsafe' => ['<?php function target(){$x=0;$a=null;$a?->missing(++$x);return $x;}', Term::fromNative(0)],
            'coalesce' => ['<?php function target(){$x=0;$a="known";$b=$a??++$x;return [$b,$x];}', Term::fromNative(['known', 0])],
            'unpack' => ['<?php function f($a,$b){return [$a,$b];}function target(){return f(...["b"=>2,"a"=>1]);}', Term::fromNative([1, 2])],
            'arrayUnion' => ['<?php function target(){return [2=>"left","x"=>1]+[2=>"right",3=>"new"];}', Term::fromNative([2 => 'left', 'x' => 1, 3 => 'new'])],
            'arrayMerge' => ['<?php function target(){return array_merge([2=>"left","x"=>1],[2=>"right","x"=>2]);}', Term::fromNative([0 => 'left', 'x' => 2, 1 => 'right'])],
            'negativeAppend' => ['<?php function target(){$a=[-5=>"first"];$a[]="next";return $a;}', Term::fromNative([-5 => 'first', -4 => 'next'])],
            'breakFinally' => ['<?php function target(){$x=0;while(true){try{break;}finally{$x=2;}}return $x;}', Term::fromNative(2)],
            'doWhile' => ['<?php function target(){$x=0;do{$x++;}while($x<2);return $x;}', Term::fromNative(2)],
            'propertyAllocations' => ['<?php class Box{public $value=0;}function target(){$a=new Box;$b=new Box;$a->value=1;$b->value=2;return [$a->value,$b->value];}', Term::fromNative([1, 2])],
            'deferredClosure' => ['<?php function target(){$x=0;$unused=function()use(&$x){$x=9;};return $x;}', Term::fromNative(0)],
            'shallowClone' => ['<?php class Box{public $child; public $value=0;}function target(){$a=new Box;$a->child=new Box;$b=clone $a;$b->child->value=3;return $a->child->value;}', Term::fromNative(3)],
            'cloneHook' => ['<?php class Box{public $value="first";function __clone(){$this->value="clone";}}function target(){$a=new Box;$b=clone $a;return [$a->value,$b->value];}', Term::fromNative(['first', 'clone'])],
            'weakBinding' => ['<?php function f(int $x){return $x;}function target(){return f("12");}', Term::fromNative(12)],
        ];
    }
}
