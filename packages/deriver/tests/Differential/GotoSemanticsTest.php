<?php

declare(strict_types=1);

namespace Tests\Differential;

use Deriver\Result\Alternative;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fake\Analysis;
use Tests\Fake\RuntimeOracle;

/**
 * Checks goto control transfer against an independent PHP 8.3 process.
 */
#[CoversNothing]
#[Medium]
final class GotoSemanticsTest extends TestCase
{
    /**
     * @param string $source Independent PHP fixture
     * @throws JsonException If fixture observations cannot be encoded
     * @throws RuntimeException If the PHP 8.3 oracle is unavailable
     */
    #[DataProvider('programs')]
    public function testDerivedReturnIsExactlyTheRuntimeReturn(string $source): void
    {
        $runtime = RuntimeOracle::observe($source);
        $result = Analysis::returns($source);
        self::assertSame('', $runtime['exception']);
        $actual = array_values(array_unique(array_map(static fn (Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes), SORT_REGULAR));
        self::assertSame([$runtime['value']->native()], $actual);
        self::assertNotContains('UNSUPPORTED_LANGUAGE_FEATURE', array_column($result->frontiers, 'code'));
    }

    /**
     * @return array<string, array{string}> Trusted fixtures executed only by the test oracle
     */
    public static function programs(): array
    {
        return [
            'backward loop' => ['<?php function target(){$i=0;$s="";start:$i++;$s.=$i;if($i<3)goto start;return $s;}'],
            'forward skip' => ['<?php function target(){$x="a";goto skip;$x="b";skip:return $x;}'],
            'label inside if' => ['<?php function target(){$x="a";goto inner;if(false){$x="b";inner:$x.="c";}return $x;}'],
            'out of foreach' => ['<?php function target(){$r=[];foreach([1,2,3] as $v){if($v===2)goto done;$r[]=$v;}done:return $r;}'],
            'out of nested foreach by reference' => ['<?php function target(){$a=[1,2];foreach($a as &$v){foreach([3,4] as $w){$v=$w;goto done;}}done:$v=9;return $a;}'],
            'out of try with finally' => ['<?php function target(){$log="";try{goto a;}finally{$log.="F";}$log.="skipped";a:$log.="A";return $log;}'],
            'out of nested finally blocks' => ['<?php function target(){$log="";try{try{goto a;}finally{$log.="1";}}finally{$log.="2";}a:$log.="3";return $log;}'],
            'out of foreach inside try' => ['<?php function target(){$log="";foreach([1,2] as $v){try{goto out;}finally{$log.="F$v";}}out:return $log;}'],
            'within finally' => ['<?php function target(){$log="";try{$log.="T";}finally{$i=0;again:$i++;$log.=$i;if($i<2)goto again;}return $log;}'],
            'out of switch' => ['<?php function target(){$x=2;switch($x){case 2:goto out;case 3:return "three";}return "fallthrough";out:return "out";}'],
            'from catch' => ['<?php function target(){$n=0;retry:try{$n++;if($n<3)throw new Exception("x");return $n;}catch(Exception $e){goto retry;}}'],
            'from catch through finally' => ['<?php function target(){$log="";try{throw new Exception;}catch(Exception $e){goto out;}finally{$log.="F";}$log.="skipped";out:return $log."O";}'],
            'closure' => ['<?php function target(){$f=function($n){$i=0;l:$i++;if($i<$n)goto l;return $i;};return $f(4);}'],
            'method' => ['<?php class Counter{public function run(){$i=0;l:if(++$i<5)goto l;return $i;}} function target(){return (new Counter)->run();}'],
        ];
    }
}
