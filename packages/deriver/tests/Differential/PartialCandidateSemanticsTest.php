<?php

declare(strict_types=1);

namespace Tests\Differential;

use Deriver\Query\ReturnQuery;
use Deriver\Result\Alternative;
use Deriver\Result\Exceptional;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fake\Analysis;
use Tests\Fake\Candidates;
use Tests\Fake\RuntimeOracle;

/**
 * Runs concrete inputs under PHP 8.3 and checks that candidates derived for symbolic inputs cover each observation.
 */
#[CoversNothing]
#[Medium]
final class PartialCandidateSemanticsTest extends TestCase
{
    /**
     * @param string $source Declares build() without calling it
     * @param string $arguments Concrete PHP arguments for one runtime call
     * @throws JsonException If fixture observations cannot be encoded
     * @throws RuntimeException If the PHP 8.3 oracle is unavailable
     */
    #[DataProvider('programs')]
    public function testRuntimeOutcomeIsCoveredBySymbolicCandidates(string $source, string $arguments): void
    {
        $runtime = RuntimeOracle::observe($source . ' function target(){return build(' . $arguments . ');}');
        $result = Analysis::session($source)->derive(new ReturnQuery('build'));
        if ($runtime['exception'] !== '') {
            $classes = array_map(static fn (Exceptional $outcome) => $outcome->exception->attributes['class'] ?? $outcome->exception->literal, $result->exceptionalOutcomes);
            self::assertTrue(in_array($runtime['exception'], $classes, true) || in_array('Throwable', $classes, true), $runtime['exception']);
            return;
        }
        $value = $runtime['value'];
        $covering = array_filter($result->normalOutcomes, static fn (Alternative $outcome): bool => Candidates::covers($outcome->values['return'], $value));
        self::assertNotEmpty($covering, json_encode($value->native(), JSON_THROW_ON_ERROR));
        if ($runtime['diagnostics'] !== []) {
            self::assertNotEmpty($result->frontiers);
        }
    }

    /**
     * @return list<array{string, string}> Trusted fixtures executed only by the test oracle
     */
    public static function programs(): array
    {
        $spread = '<?php function build(array $x){$cols=["id","name",...$x];';
        $format = '<?php function build($f){return ';
        $programs = [];
        foreach (['[]', '["a"]', '["k"=>"v",5=>"z"]', '["0"=>"zero","name"]'] as $input) {
            $programs[] = ['<?php function build(array $x){return implode(",",[...$x,"z"]);}', $input];
            $programs[] = ['<?php function build(array $x){foreach([...$x,"z"] as $v){return $v;}}', $input];
            $programs[] = [$spread . 'return implode(",",$cols);}', $input];
            $programs[] = [$spread . 'return [$cols[0],$cols["1"],count($cols)>=2];}', $input];
            $programs[] = [$spread . '$i=0;foreach($cols as $k=>$v){if($i++===1)return "$k=$v";}return null;}', $input];
            $programs[] = [$spread . 'return [in_array("name",$cols,true),array_key_exists(1,$cols)];}', $input];
            $programs[] = ['<?php function build(array $x){return implode("-",array_merge(["id"],$x));}', $input];
        }
        foreach (['[]', '[1]'] as $input) {
            $programs[] = ['<?php function build(array $x){try{$a=[7=>"id",...$x];return "built";}catch(Error $e){return "failed";}}', $input];
            $programs[] = ['<?php function build(array $x){try{$a=[PHP_INT_MAX=>"id",...$x];return "built";}catch(Error $e){return "failed";}}', $input];
        }
        foreach (['"a = 1"', '"a = %s"', '\'%0$s\'', '"%%"', '1', 'null'] as $input) {
            $programs[] = [$format . 'sprintf("SELECT * FROM $f WHERE id = %d",5);}', $input];
            $programs[] = [$format . 'sprintf("SELECT %s FROM t WHERE ".$f,"id");}', $input];
            $programs[] = [$format . 'vsprintf(\'%2$s %1$s \'.$f,["a"=>"x","b"=>"y"]);}', $input];
        }
        foreach (['""', '"%"', '"s"', '"1$s"', '"d"'] as $input) {
            $programs[] = [$format . 'sprintf("100%".$f,"a");}', $input];
        }
        foreach (['"a,b"', '""'] as $input) {
            $programs[] = ['<?php function build(string $s){foreach(explode(",",$s) as $part){return $part;}return null;}', $input];
        }
        foreach (['vsprintf("%s",["a"=>"x"])', 'vsprintf("%s %s",["x"])', 'sprintf(\'%0$s\',"x")', 'sprintf(\'%1$%\')', 'sprintf(\'%1$%\',"x")', 'sprintf(\'%s %0$s\')', 'sprintf(\'%2147483647$s\',"x")'] as $call) {
            $programs[] = ['<?php function build(){return ' . $call . ';}', ''];
        }
        foreach (['sprintf("%s",x:"v")', 'sprintf("%s",...["x"=>"v"])', 'sprintf("%s","a",...["x"=>"v"])', 'sprintf(format:"%s",values:"v")', 'vsprintf("%s",["x"=>"v"])', 'sprintf(\'%2$%\',1)', 'sprintf(\'%2$%\',1,2)', 'sprintf(\'%$s\',1)', 'sprintf(\'%1$\',1)', 'sprintf(\'%1$\')', 'sprintf("a%",1)', 'sprintf("a%")', 'sprintf(\'%s %2$%\',1)', 'sprintf(\'%1$%%s\',1)', 'vsprintf(\'%2$%\',[1])'] as $call) {
            $programs[] = ['<?php function build(){return ' . $call . ';}', ''];
        }
        foreach (['""', '"s"', '"$s"', '"$%"', '"%"', '"0$s"'] as $input) {
            $programs[] = [$format . 'sprintf(\'%1$s %2\'.$f,"a","b");}', $input];
            $programs[] = [$format . 'sprintf("%s %".$f,"a",x:"v");}', $input];
        }
        return $programs;
    }
}
