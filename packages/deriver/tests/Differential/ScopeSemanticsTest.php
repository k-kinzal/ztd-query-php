<?php

declare(strict_types=1);

namespace Tests\Differential;

use Deriver\Query\Budget;
use Deriver\Query\ValueQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fake\Analysis;
use Tests\Fake\RuntimeOracle;

/**
 * Checks script-scope bindings against an independent PHP 8.3 process.
 */
#[CoversNothing]
#[Medium]
final class ScopeSemanticsTest extends TestCase
{
    /**
     * The value PHP passes to the first sink call is a concrete candidate, or a symbolic candidate covers it.
     * @param string $statements Script statements that call sink() once without diagnostics
     * @throws JsonException If fixture observations cannot be encoded
     * @throws RuntimeException If the PHP 8.3 oracle is unavailable
     */
    #[DataProvider('programs')]
    public function testScriptSinkArgumentCoversTheRuntimeValue(string $statements): void
    {
        $source = '<?php function sink($value){$GLOBALS["deriverSeen"][]=$value;} function target(){return $GLOBALS["deriverSeen"][0];} ' . $statements;
        $runtime = RuntimeOracle::observe($source);
        self::assertSame('', $runtime['exception']);
        $session = Analysis::session($source);
        $result = $session->derive(new ValueQuery($session->callsTo('sink')[0]->argument(0), budget: new Budget(symbolicRecursion: 1)));
        $concrete = [];
        $symbolic = false;
        foreach ($result->normalOutcomes as $outcome) {
            $value = $outcome->values['value'];
            if ($value->isConcrete()) {
                $concrete[] = $value->native();
            } else {
                $symbolic = true;
            }
        }
        self::assertTrue($symbolic || in_array($runtime['value']->native(), $concrete, true), 'PHP 8.3 passed ' . json_encode($runtime['value']->native(), JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, array{string}> Trusted script statements executed only by the test oracle
     */
    public static function programs(): array
    {
        return [
            'unset reference source' => ['$x=1;$a=&$x;unset($x);sink($a);'],
            'unset reference source then write' => ['$x=1;$a=&$x;unset($x);$x=2;sink([$a,$x]);'],
            'unset reference target' => ['$x=1;$a=&$x;unset($a);sink($x);'],
            'unset after by-reference argument' => ['function keep(&$p){static $r;$r=&$p;} $x=1;keep($x);unset($x);$x=2;sink($x);'],
            'unset after foreach by reference' => ['$rows=[1,2];foreach($rows as &$row){$row++;}unset($rows);sink($row);'],
            'function unsets its global binding' => ['function drop(){global $g;unset($g);} $g="kept";drop();sink($g);'],
            'unset static reference' => ['function read(){static $s=5;$r=&$s;unset($s);return $r;} sink(read());'],
            'global created by refused recursion' => ['function f(int $n,int $d){if($n>0){f($n-1,1);return;}global $newg;$newg=$d===0?"top":"deep";} $n=random_int(1,3);f($n,0);sink($newg);'],
        ];
    }
}
