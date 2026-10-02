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
 * Checks writes through by-reference iteration and unknown reference effects against PHP 8.3 runs of symbolic fixtures.
 */
#[CoversNothing]
#[Medium]
final class ReferenceIterationSemanticsTest extends TestCase
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
    }

    /**
     * @return list<array{string, string}> Trusted fixtures executed only by the test oracle
     */
    public static function programs(): array
    {
        $strip = 'static function(array $a){unset($a[0]["a"]);}';
        $keep = 'static function(array $a){}';
        return [
            ['<?php function build(){$o=new stdClass;$o->a=1;foreach($o as &$v){$v=2;}return $o->a;}', ''],
            ['<?php function build(){$o=new stdClass;$o->a=1;$p=$o;foreach($o as &$v){$v=2;}return $p->a;}', ''],
            ['<?php function build($o){$o->a=1;$p=$o;foreach($o as &$v){$v=2;}return $p->a;}', 'new stdClass'],
            ['<?php function build(object $o){$o->a=1;foreach($o as &$v){}$o->a=5;$v=7;return $o->a;}', 'new stdClass'],
            ['<?php function build(array $p){$p["k"]=1;foreach($p as &$v){$v=2;}return $p["k"];}', '[]'],
            ['<?php function build(array $p){$p["k"]=1;foreach($p as &$v){$v=2;}return $p["k"];}', '["a"=>0]'],
            ['<?php function build(){$_GET["k"]=1;foreach($_GET as &$v){$v=2;}return $_GET["k"];}', ''],
            ['<?php function build(array $xs){$arr=[1,...$xs];foreach($arr as &$v){$v=9;}return $arr[0];}', '[]'],
            ['<?php function build(array $xs){$arr=[1,...$xs];foreach($arr as &$v){$v=9;}return $arr[0];}', '[5,6]'],
            ['<?php function build(array $xs){$arr=[1,...$xs];foreach($arr as &$v){}$arr[0]=5;$v=7;return $arr[0];}', '[]'],
            ['<?php function build($k){$a=[1,2];$v=&$a[$k];$v=9;return $a;}', '0'],
            ['<?php function build(array $xs){$i=0;$arr=[1,2];foreach($arr as $k=>&$v){if($i++===1)return $k;$arr=$xs;}return null;}', '["a"=>1,"b"=>2]'],
            ['<?php function build(array $xs){$i=0;$arr=[1,2];foreach($arr as $k=>&$v){if($i++===1)return $k;$arr=$xs;}return null;}', '[5,6]'],
            ['<?php function build(array $xs){$i=0;$arr=[1,2];foreach($arr as $k=>&$v){if($i++===2)return $k;$arr[]=$xs;}return null;}', '[]'],
            ['<?php function build(callable $f){$x=["a"=>1];$arr=[&$x];$f($arr);return array_key_exists("a",$x);}', $strip],
            ['<?php function build(callable $f){$x=["a"=>1];$arr=[&$x];$f($arr);return array_key_exists("a",$x);}', $keep],
            ['<?php function build(callable $f){$x=["a"=>1];$arr=[&$x];$f($arr);return [isset($x["a"]),count($x),in_array(1,$x,true)];}', $strip],
            ['<?php function build(callable $f){$x=["a"=>1];$arr=[&$x];$f($arr);foreach($x as $k=>$v){return $k;}return null;}', $strip],
        ];
    }
}
