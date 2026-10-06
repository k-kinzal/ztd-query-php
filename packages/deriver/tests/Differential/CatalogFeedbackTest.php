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
 * Checks ordinary PHP construction and mutation against an independent PHP 8.3 process.
 */
#[CoversNothing]
#[Medium]
final class CatalogFeedbackTest extends TestCase
{
    /**
     * @param string $source Independent PHP fixture
     * @throws JsonException If fixture observations cannot be encoded
     * @throws RuntimeException If the PHP 8.3 oracle is unavailable
     */
    #[DataProvider('programs')]
    public function testRuntimeOutcomeIsAmongDerivedCandidates(string $source): void
    {
        $runtime = RuntimeOracle::observe($source);
        $result = Analysis::returns($source);
        if ($runtime['exception'] !== '') {
            self::assertContains($runtime['exception'], array_map(static fn (\Deriver\Result\Exceptional $outcome) => $outcome->exception->attributes['class'] ?? $outcome->exception->literal, $result->exceptionalOutcomes));
        } else {
            self::assertContains($runtime['value']->native(), array_map(static fn (Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes));
        }
        if ($runtime['diagnostics'] !== []) {
            self::assertContains('PHP_WARNING', array_column($result->frontiers, 'code'));
        }
    }

    /**
     * @return list<array{string}> Trusted fixtures executed only by the test oracle
     */
    public static function programs(): array
    {
        return [
            ['<?php function target(){return [-5=>-1, +2, -3, -1=>4, 5];}'],
            ['<?php function target(){return [-1=>0,1,2,3];}'],
            ['<?php function target(){return ["-1"=>0,1,2,3];}'],
            ['<?php function target(){return [-1=>1,"-1"=>2,+1=>3,"+1"=>4,5];}'],
            ['<?php function target(){return [-0.0,+0.0,-1.5,+1.5,-9223372036854775808,-9223372036854775809];}'],
            ['<?php function target(){return [-1.5=>1,2];}'],
            ['<?php function target(){return [9223372036854775807=>1,2];}'],
            ['<?php function target(){return [-(-2),+(+3),-true,+"4"];}'],
            ['<?php function target(){$x=1;return $x+++$x;}'],
            ['<?php class Text{function __toString(){throw new Exception;} } function target(){try{exit(new Text);}catch(Exception $e){return "caught";}}'],
            ['<?php function nextValue(){static $n=0;return ++$n;} function target(){return [nextValue(),nextValue()];}'],
            ['<?php function pick($x){return $x;} function target(){return "SELECT ".pick("id")." FROM ".pick("users");}'],
            ['<?php function target(){$sql="old";$n="sql";$$n="new";return $sql;}'],
            ['<?php function target(){return array_fill(-2,3,"?");}'],
            ['<?php function target(){return array_fill(0,-1,"?");}'],
            ['<?php function target(){return array_fill(0,2147483648,"?");}'],
            ['<?php function target(){return str_repeat("?,",3);}'],
            ['<?php function target(){return str_repeat("x",-1);}'],
            ['<?php function target(){return vsprintf("%s %d",["id",7]);}'],
            ['<?php function target(){return intval("0xff",0);}'],
            ['<?php function target(){return [ucfirst("users"),lcfirst("Users"),ltrim(" x "),rtrim(" x ")];}'],
            ['<?php class Text{function __toString(){return "users";}} function target(){return strval(new Text);}'],
            ['<?php class Text{function __toString(){return "users";}} function target(){return implode(",",[new Text,"posts"]);}'],
            ['<?php function target(){define("TABLE","users");return TABLE;}'],
            ['<?php function target(){return define("PHP_INT_SIZE",123);}'],
            ['<?php function target(){define("*","old");return define("TABLE","users");}'],
            ['<?php function target(){$x=1;define("DATA",[&$x]);$x=2;return DATA;}'],
            ['<?php function target(){define("TABLE","users");$again=define("TABLE","posts");return [TABLE,$again];}'],
            ['<?php function target(){define("TABLE","users",true);return TABLE;}'],
            ['<?php function target(){return define("User::TABLE","users");}'],
            ['<?php namespace {const TABLE="global";function target(){return App\\read();}} namespace App {const TABLE="local";function read(){return TABLE;}}'],
            ['<?php final class DB{function __call($name,$args){return $args[0];}} function target(){return (new DB)->query("SELECT 1");}'],
        ];
    }
}
