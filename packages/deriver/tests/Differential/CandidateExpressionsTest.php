<?php

declare(strict_types=1);

namespace Tests\Differential;

use Deriver\Query\ReturnQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\RuntimeOracle;
use Tests\Semantic\CandidateContractTest;

/**
 * Compares closed source candidates with independent PHP 8.3 observations.
 */
#[CoversNothing]
#[Medium]
final class CandidateExpressionsTest extends TestCase
{
    /**
     * @throws JsonException If the independent runtime envelope is invalid
     */
    #[DataProvider('programs')]
    public function testClosedDependenciesAgreeWithPhp(string $source): void
    {
        $expected = RuntimeOracle::evaluate('<?php ' . $source)->native();
        $session = CandidateContractTest::session($source);
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame([], CandidateContractTest::frontiers($result));
        self::assertSame([$expected], CandidateContractTest::native($result, 'return'));
    }

    /**
     * @return iterable<string, array{string}> Generated closed finite fixtures
     */
    public static function programs(): iterable
    {
        for ($seed = 0; $seed < 40; $seed++) {
            $left = $seed % 9 - 4;
            $right = $seed % 7 + 1;
            yield 'arithmetic-' . $seed => ['function target(){return -(' . $left . '+' . $right . ')*3;}'];
            yield 'call-' . $seed => ['function helper(int $x,int $y){return $x*2+$y;}function target(){return helper(y:' . $right . ',x:' . $left . ');}'];
            yield 'reference-' . $seed => ['function target(){$x=' . $left . ';$y=&$x;$y+=' . $right . ';return $x;}'];
            yield 'array-' . $seed => ['function target(){$a=[-(1+0)=>' . $left . ',' . $right . '];$a[0]+=3;return $a;}'];
        }
        yield 'recursion' => ['function sum($n){if($n===0){return 0;}return $n+sum($n-1);}function target(){return sum(5);}'];
        yield 'loop' => ['function target(){$s=0;for($i=0;$i<5;$i++){$s+=$i;}return $s;}'];
        yield 'do-once' => ['function target(){$s=1;do{$s+=2;}while(false);return $s;}'];
        yield 'do-loop' => ['function target(){$s=0;do{$s++;}while($s<3);return $s;}'];
        yield 'constructor' => ['class Box{function __construct(public int $n){}}function target(){$b=new Box("7");return $b->n;}'];
        yield 'catch' => ['function helper(int $n){return $n;}function target(){try{return helper([]);}catch(TypeError $e){return "typed";}}'];
    }
}
