<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Analyzer;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\Budget;
use Deriver\Query\ReturnQuery;
use Deriver\Query\ValueQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * Regression matrix for candidate expansion and structural performance.
 */
#[CoversNothing]
#[Large]
final class CandidateExpansionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<mixed>}> Source and expected candidate values
     */
    public static function providerCases(): iterable
    {
        yield 'T01 literal' => ['function f(){return 42;}', [42]];
        yield 'T03 aliases' => ['function f(){$x="foo";$y=$x;return $y;}', ['foo']];
        yield 'T04 branches' => ['function f($b){if($b){$x=30;}else{$x=60;}return $x;}', [30, 60]];
        yield 'T05 equal branches' => ['function f($b){if($b){$x=30;}else{$x=30;}return $x;}', [30]];
        yield 'F29 callers' => ['function f(int $n){return $n+5;} function recipe(int $n){return f($n);} function a(){return recipe(30);} function b(){return recipe(60);}', [35, 65]];
        yield 'F12 finally local' => ['function f(){try{$ttl=30;}finally{} return $ttl;}', [30]];
        yield 'F12 finally override' => ['function f(){try{return 30;}finally{return 60;}}', [60]];
        yield 'F12 return position' => ['function f(){$x=30;try{return $x;}finally{$x=60;}}', [30]];
        yield 'F27 throw catch' => ['function f(){try{throw new RuntimeException();}catch(RuntimeException $e){return 60;}}', [60]];
        yield 'F27 throw expression catch' => ['function f(){try{return throw new RuntimeException();}catch(RuntimeException $e){return 60;}}', [60]];
        yield 'F01 late static' => ['class A{static function value(){return "A";}static function sql(){return static::value();}} class U extends A{static function value(){return "U";}}function f(){return U::sql();}', ['U']];
        yield 'F02 parent receiver' => ['class A{function value(){return $this->name();}}class U extends A{function name(){return "U";}function sql(){return parent::value();}}function f(){return (new U)->sql();}', ['U']];
        yield 'F03 reference capture' => ['function f(){$t="a";$c=function()use(&$t){return $t;};$t="b";return $c();}', ['b']];
        yield 'F03 value capture' => ['function f(){$t="a";$c=function()use($t){return $t;};$t="b";return $c();}', ['a']];
        yield 'F05 unpack' => ['function add($x,$y){return $x+$y;}function f(){return add(...[2,3]);}', [5]];
        yield 'F06 nested write' => ['function f(){$a=["x"=>["y"=>"a"]];$a["x"]["y"]="v";return $a["x"]["y"];}', ['v']];
        yield 'F04 dynamic write' => ['function f(){$x="a";$name="x";$$name="b";return $x;}', ['b']];
        yield 'F06 element alias' => ['function f(){$a=["t"=>"a"];$r=&$a["t"];$r="b";return $a["t"];}', ['b']];
        yield 'F07 global write' => ['function setValue(){global $g;$g="b";}function f(){global $g;$g="a";setValue();return $g;}', ['b']];
        yield 'F08 static sequence' => ['function nextValue(){static $n=0;return ++$n;}function f(){nextValue();return nextValue();}', [2]];
        yield 'F11 conditional declaration' => ['if(!class_exists("A")){class A{static function value(){return 42;}}}function f(){return A::value();}', [42]];
        yield 'F13 invariant' => ['function f($n){$x="fixed";while($n){$n--;}return $x;}', ['fixed']];
        yield 'F14 foreach' => ['function f(){$s="";foreach(["a","b"] as $v){$s.=$v;}return $s;}', ['ab']];
        yield 'F15 array callable' => ['class A{function m($v){return $v+1;}}function f(){$cb=[new A,"m"];return $cb(2);}', [3]];
        yield 'F15 named callback' => ['function inc($v){return $v+1;}function f(){return call_user_func("inc",2);}', [3]];
        yield 'F15 array map' => ['function f(){return array_map(fn($v)=>$v+1,[1,2]);}', [[2,3]]];
        yield 'F15 array filter' => ['function f(){return array_filter([1,2,3],fn($v)=>$v>1);}', [[1 => 2,2 => 3]]];
        yield 'F15 callback sort' => ['function f(){$v=[3,1,2];usort($v,fn($a,$b)=>$a<=>$b);return $v;}', [[1,2,3]]];
        yield 'F19 string conversion' => ['class A{function __toString(){return "a";}}function f(){return "prefix:".new A;}', ['prefix:a']];
        yield 'F20 script global' => ['$cfg=["db"=>["table"=>"users"]];function f(){global $cfg;return $cfg["db"]["table"];}', ['users']];
        yield 'F17 class constant' => ['class A{const X=2+3;}function f(){return A::X;}', [5]];
        yield 'F17 class name' => ['class A{}function f(){return A::class;}', ['A']];
        yield 'F19 string offset' => ['function f(){return "abc"[1];}', ['b']];
        yield 'F19 clone property' => ['class A{public $x="a";}function f(){$a=new A;$b=clone $a;return $b->x;}', ['a']];
    }

    /**
     * @throws JsonException If fixture or captured metadata cannot be encoded
     * @param list<mixed> $expected Exact concrete candidate values
     */
    #[DataProvider('providerCases')]
    public function testExpansion(string $source, array $expected): void
    {
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('fixture.php', '<?php ' . $source)]));
        $result = $session->derive(new ReturnQuery('f'));
        self::assertSame($expected, array_map(static fn ($candidate) => $candidate->result, $result->candidates));
        foreach ($result as $candidate) {
            self::assertSame('analyzed', $candidate->type);
            self::assertSame('observation', $candidate->evidence[0]->root->kind);
            foreach ($candidate->evidence as $proof) {
                $nodes = $proof->nodes();
                self::assertArrayHasKey($proof->root->id, $nodes);
                foreach ($nodes as $node) {
                    foreach ($node->inputs as $child) {
                        self::assertArrayHasKey($child->id, $nodes);
                    }
                }
            }
        }
    }

    /**
     * @throws JsonException If fixture or captured metadata cannot be encoded
     */
    public function testP01ThroughP03IgnoreUnrelatedBranches(): void
    {
        foreach ([0, 8, 16, 20, 100] as $count) {
            $branches = str_repeat('if($unrelated){$a=1;}else{$a=2;}', $count);
            foreach (['observe("SELECT 1");', '$sql="SELECT 1";' . $branches . 'observe($sql);', 'observe(helper());'] as $body) {
                $source = '<?php function helper(){' . $branches . 'return "SELECT 1";} function f($unrelated){' . $branches . $body . '}';
                $session = (new Analyzer())->open(new ProjectInput([new SourceFile('fixture.php', $source)]));
                $result = $session->derive(new ValueQuery($session->callsTo('observe')[0]->argument(0)));
                self::assertCount(1, $result);
                self::assertSame('SELECT 1', $result->candidates[0]->result);
                self::assertLessThan(20, $result->statistics->referenceExpansions);
            }
        }
    }

    /**
     * @throws JsonException If fixture or captured metadata cannot be encoded
     */
    public function testL02RetainsUnenumeratedCandidates(): void
    {
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('fixture.php', '<?php function f($a,$b){return $a?10:($b?20:30);}') ]));
        $result = $session->derive(new ReturnQuery('f', budget: new Budget(maxCandidates: 2)));
        self::assertCount(2, $result);
        self::assertSame('partials', $result->candidates[1]->type);
        self::assertSame('unexpanded-choice', $result->candidates[1]->term->kind);
        $values = iterator_to_array((new \Deriver\Evaluation\Candidate\Choices())->alternatives($result->candidates[1]->term->operands[0]), false);
        self::assertSame([20, 30], array_map(static fn ($row) => array_values($row[0]->operands)[0]->native(), $values));
    }
}
