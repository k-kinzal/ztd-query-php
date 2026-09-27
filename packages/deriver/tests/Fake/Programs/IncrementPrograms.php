<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Captures independently observed PHP 8.3 increment results and diagnostics.
 * @visibility root
 */
final class IncrementPrograms
{
    /**
     * @return array<string, array{string, string, bool}> Source, recorded JSON return, diagnostic presence
     */
    public static function cases(): array
    {
        return [
            'inc:Z:False' => ['<?php function target(){$x="Z";$result=++$x;return [$result,$x];}', '["AA","AA"]', false],
            'inc:Z:True' => ['<?php function target(){$x="Z";$result=$x++;return [$result,$x];}', '["Z","AA"]', false],
            'inc:a9:False' => ['<?php function target(){$x="a9";$result=++$x;return [$result,$x];}', '["b0","b0"]', false],
            'inc:a9:True' => ['<?php function target(){$x="a9";$result=$x++;return [$result,$x];}', '["a9","b0"]', false],
            'inc:9z:False' => ['<?php function target(){$x="9z";$result=++$x;return [$result,$x];}', '["10a","10a"]', false],
            'inc:9z:True' => ['<?php function target(){$x="9z";$result=$x++;return [$result,$x];}', '["9z","10a"]', false],
            'inc:z9:False' => ['<?php function target(){$x="z9";$result=++$x;return [$result,$x];}', '["aa0","aa0"]', false],
            'inc:z9:True' => ['<?php function target(){$x="z9";$result=$x++;return [$result,$x];}', '["z9","aa0"]', false],
            'inc:A09:False' => ['<?php function target(){$x="A09";$result=++$x;return [$result,$x];}', '["A10","A10"]', false],
            'inc:A09:True' => ['<?php function target(){$x="A09";$result=$x++;return [$result,$x];}', '["A09","A10"]', false],
            'inc:5d9:False' => ['<?php function target(){$x="5d9";$result=++$x;return [$result,$x];}', '["5e0","5e0"]', false],
            'inc:5d9:True' => ['<?php function target(){$x="5d9";$result=$x++;return [$result,$x];}', '["5d9","5e0"]', false],
            'inc:0zz:False' => ['<?php function target(){$x="0zz";$result=++$x;return [$result,$x];}', '["1aa","1aa"]', false],
            'inc:0zz:True' => ['<?php function target(){$x="0zz";$result=$x++;return [$result,$x];}', '["0zz","1aa"]', false],
            'inc:zzz:False' => ['<?php function target(){$x="zzz";$result=++$x;return [$result,$x];}', '["aaaa","aaaa"]', false],
            'inc:zzz:True' => ['<?php function target(){$x="zzz";$result=$x++;return [$result,$x];}', '["zzz","aaaa"]', false],
            'inc:A99:False' => ['<?php function target(){$x="A99";$result=++$x;return [$result,$x];}', '["B00","B00"]', false],
            'inc:A99:True' => ['<?php function target(){$x="A99";$result=$x++;return [$result,$x];}', '["A99","B00"]', false],
            'inc:foo!:False' => ['<?php function target(){$x="foo!";$result=++$x;return [$result,$x];}', '["foo!","foo!"]', true],
            'inc:foo!:True' => ['<?php function target(){$x="foo!";$result=$x++;return [$result,$x];}', '["foo!","foo!"]', true],
            'inc:a-9:False' => ['<?php function target(){$x="a-9";$result=++$x;return [$result,$x];}', '["a-0","a-0"]', true],
            'inc:a-9:True' => ['<?php function target(){$x="a-9";$result=$x++;return [$result,$x];}', '["a-9","a-0"]', true],
            'inc:!z:False' => ['<?php function target(){$x="!z";$result=++$x;return [$result,$x];}', '["!a","!a"]', true],
            'inc:!z:True' => ['<?php function target(){$x="!z";$result=$x++;return [$result,$x];}', '["!z","!a"]', true],
            'inc:0009:False' => ['<?php function target(){$x="0009";$result=++$x;return [$result,$x];}', '[10,10]', false],
            'inc:0009:True' => ['<?php function target(){$x="0009";$result=$x++;return [$result,$x];}', '["0009",10]', false],
            'inc:1e2:False' => ['<?php function target(){$x="1e2";$result=++$x;return [$result,$x];}', '[101.0,101.0]', false],
            'inc:1e2:True' => ['<?php function target(){$x="1e2";$result=$x++;return [$result,$x];}', '["1e2",101.0]', false],
            'inc:9223372036854775807:False' => ['<?php function target(){$x="9223372036854775807";$result=++$x;return [$result,$x];}', '[9.223372036854776e+18,9.223372036854776e+18]', false],
            'inc:9223372036854775807:True' => ['<?php function target(){$x="9223372036854775807";$result=$x++;return [$result,$x];}', '["9223372036854775807",9.223372036854776e+18]', false],
            'inc::False' => ['<?php function target(){$x="";$result=++$x;return [$result,$x];}', '["1","1"]', true],
            'inc::True' => ['<?php function target(){$x="";$result=$x++;return [$result,$x];}', '["","1"]', true],
            'inc:0:False' => ['<?php function target(){$x="0";$result=++$x;return [$result,$x];}', '[1,1]', false],
            'inc:0:True' => ['<?php function target(){$x="0";$result=$x++;return [$result,$x];}', '["0",1]', false],
            'inc:-1:False' => ['<?php function target(){$x="-1";$result=++$x;return [$result,$x];}', '[0,0]', false],
            'inc:-1:True' => ['<?php function target(){$x="-1";$result=$x++;return [$result,$x];}', '["-1",0]', false],
            'dec:1e2' => ['<?php function target(){$x="1e2";$result=$x--;return [$result,$x];}', '["1e2",99.0]', false],
            'dec:42' => ['<?php function target(){$x="42";$result=$x--;return [$result,$x];}', '["42",41]', false],
            'dec:-1' => ['<?php function target(){$x="-1";$result=$x--;return [$result,$x];}', '["-1",-2]', false],
            'dec:9223372036854775808' => ['<?php function target(){$x="9223372036854775808";$result=$x--;return [$result,$x];}', '["9223372036854775808",9.223372036854776e+18]', false],
            'arrayError' => ['<?php function target(){$x=[1];try{$x++;}catch(TypeError $e){return $x;}return 999;}', '[1]', false],
            'objectError' => ['<?php function target(){$x=new stdClass;try{$x++;}catch(TypeError $e){return is_object($x);}return 999;}', 'true', false],
            'propertyString' => ['<?php class B{public string $x="a9";}function target(){$b=new B;$result=$b->x++;return [$result,$b->x];}', '["a9","b0"]', false],
            'referenceString' => ['<?php class B{public string $x="a9";}function target(){$b=new B;$x=&$b->x;$result=$x++;return [$result,$b->x];}', '["a9","b0"]', false],
            'nullIncrement' => ['<?php function target(){$x=null;$result=++$x;return [$result,$x];}', '[1,1]', false],
        ];
    }
}
