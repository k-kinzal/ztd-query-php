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
final class BoundarySemanticsTest extends TestCase
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
            'arrayRebind' => ['<?php function target(){$x=1;$y=2;$a=[&$x];$a[0]=&$y;$a[0]=3;return [$x,$y,$a];}', Term::fromNative([1, 3, [3]])],
            'unsetAppend' => ['<?php function target(){$a=[5=>1];unset($a[5]);$a[]=2;return $a;}', Term::fromNative([6 => 2])],
            'unsetNegative' => ['<?php function target(){$a=[-5=>1];unset($a[-5]);$a[]=2;return $a;}', Term::fromNative([-4 => 2])],
            'returnReference' => ['<?php function &get(&$x){return $x;}function target(){$x=1;$y=&get($x);$y=2;return $x;}', Term::fromNative(2)],
            'floatPromotion' => ['<?php function f(float $x):float{return $x;}function target(){return f(2);}', Term::fromNative(2.0)],
            'returnCoercion' => ['<?php function f():int{return "42";}function target(){return f();}', Term::fromNative(42)],
            'returnFailure' => ['<?php declare(strict_types=1);function f():int{return "42";}function target(){try{return f();}catch(TypeError $e){return "caught";}}', Term::fromNative('caught')],
            'referenceCoercion' => ['<?php function f(int &$x){}function target(){$x="42";f($x);return $x;}', Term::fromNative(42)],
            'arrayCallable' => ['<?php class C{function f($x){return $x+1;}}function target(){$c=new C;$f=[$c,"f"];return $f(2);}', Term::fromNative(3)],
            'invoke' => ['<?php class C{function __invoke($x){return $x+1;}}function target(){$c=new C;return $c(2);}', Term::fromNative(3)],
            'sortNumeric' => ['<?php function target(){$a=["b"=>3,"a"=>1,2];$r=sort($a);return [$r,$a];}', Term::fromNative([true, [1, 2, 3]])],
            'replaceCount' => ['<?php function target(){$count=99;$r=str_replace("a","b","aaba",$count);return [$r,$count];}', Term::fromNative(['bbbb', 3])],
            'format' => ['<?php function target(){return sprintf("user:%s:%d:%%","a",7);}', Term::fromNative('user:a:7:%')],
            'formatPositions' => ['<?php function target(){return sprintf(\'%2$s:%1$d\',7,\'a\');}', Term::fromNative('a:7')],
            'implodeOverload' => ['<?php function target(){return implode(["a","b"]);}', Term::fromNative('ab')],
            'keysFilter' => ['<?php function target(){return array_keys(["a"=>1,"b"=>"1","c"=>2],1,true);}', Term::fromNative(['a'])],
            'recursiveCount' => ['<?php function target(){return count([1,[2,3]],COUNT_RECURSIVE);}', Term::fromNative(4)],
            'filterKey' => ['<?php function target(){return array_filter(["a"=>1,"b"=>2],fn($k)=>$k==="b",ARRAY_FILTER_USE_KEY);}', Term::fromNative(['b' => 2])],
        ];
    }
}
