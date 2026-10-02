<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Records independently observed PHP 8.3 results of by-reference array mutations and key selection.
 * @visibility root
 */
final class ArrayMutationPrograms
{
    /**
     * @return array<string, array{string, string, string}> Source, JSON normal returns, exception class
     */
    public static function cases(): array
    {
        return [
            'shift renumbers integer keys' => ['<?php function target(){$a=[5=>"a","k"=>"b",9=>"c"];$v=array_shift($a);return [$v,$a];}', '[["a",{"k":"b","0":"c"}]]', ''],
            'shift of an empty array' => ['<?php function target(){$a=[];return [array_shift($a),$a];}', '[[null,[]]]', ''],
            'shift resets the append index' => ['<?php function target(){$a=[1,2,3];unset($a[2]);array_shift($a);$a[]=9;return $a;}', '[[2,9]]', ''],
            'shift of an emptied array keeps the append index' => ['<?php function target(){$a=[5=>1];unset($a[5]);$v=array_shift($a);$a[]=2;return [$v,$a];}', '[[null,{"6":2}]]', ''],
            'shift renumbers negative keys' => ['<?php function target(){$a=[-5=>"x","k"=>"y",7=>"z"];$v=array_shift($a);$a[]=1;return [$v,$a];}', '[["x",{"k":"y","0":"z","1":1}]]', ''],
            'shift copies a referenced value' => ['<?php function target(){$x=1;$a=[&$x,2];$v=array_shift($a);$x=5;return [$v,$a];}', '[[1,[2]]]', ''],
            'shift keeps remaining references' => ['<?php function target(){$x=1;$a=[0,&$x];array_shift($a);$x=5;return $a;}', '[[5]]', ''],
            'pop returns the last value' => ['<?php function target(){$a=["k"=>1,3=>2];$v=array_pop($a);return [$v,$a];}', '[[2,{"k":1}]]', ''],
            'pop of an empty array' => ['<?php function target(){$a=[];return [array_pop($a),$a];}', '[[null,[]]]', ''],
            'pop releases the last append index' => ['<?php function target(){$a=[1,2,3];array_pop($a);$a[]=9;return $a;}', '[[1,2,9]]', ''],
            'pop of a lower last key keeps the append index' => ['<?php function target(){$a=[3=>"a",1=>"b"];array_pop($a);$a[]=9;return $a;}', '[{"3":"a","4":9}]', ''],
            'pop of the largest key releases it' => ['<?php function target(){$a=[1=>"b",3=>"a"];array_pop($a);$a[]=9;return $a;}', '[{"1":"b","3":9}]', ''],
            'pop to an empty array keeps the released index' => ['<?php function target(){$a=[5=>1];array_pop($a);$a[]=2;return $a;}', '[{"5":2}]', ''],
            'pop of the maximum key frees it' => ['<?php function target(){$a=[PHP_INT_MAX=>1];array_pop($a);$a[]=5;return array_keys($a);}', '[[9223372036854775807]]', ''],
            'pop copies a referenced value' => ['<?php function target(){$x=1;$a=[1,&$x];$v=array_pop($a);$x=5;return [$v,$a];}', '[[1,[1]]]', ''],
            'push appends and counts' => ['<?php function target(){$a=["k"=>1,4=>2];$n=array_push($a,"x","y");return [$n,$a];}', '[[4,{"k":1,"4":2,"5":"x","6":"y"}]]', ''],
            'push without values' => ['<?php function target(){$a=[1];return [array_push($a),$a];}', '[[1,[1]]]', ''],
            'push uses the append index after unset' => ['<?php function target(){$a=[1,2];unset($a[1]);array_push($a,3);return $a;}', '[{"0":1,"2":3}]', ''],
            'push keeps values inserted before an occupied maximum index' => ['<?php function target(){$a=[PHP_INT_MAX-1=>1];try{array_push($a,2,3);}catch(Error $e){return array_keys($a);}return null;}', '[[9223372036854775806,9223372036854775807]]', ''],
            'push through a reference parameter' => ['<?php function add(array &$p){return array_push($p,"x");} function target(){$a=["y"];$n=add($a);return [$n,$a];}', '[[2,["y","x"]]]', ''],
            'push in a loop' => ['<?php function target(){$a=[];foreach(["x","y"] as $v){array_push($a,$v);}return $a;}', '[["x","y"]]', ''],
            'unshift renumbers and counts' => ['<?php function target(){$a=["k"=>1,5=>2];$n=array_unshift($a,"x","y");return [$n,$a];}', '[[4,{"0":"x","1":"y","k":1,"2":2}]]', ''],
            'unshift without values renumbers' => ['<?php function target(){$a=["a"=>1,5=>2];$n=array_unshift($a);$a[]=3;return [$n,$a];}', '[[2,{"a":1,"0":2,"1":3}]]', ''],
            'unshift keeps references' => ['<?php function target(){$x=1;$a=[&$x,2];array_unshift($a,0);$x=5;return $a;}', '[[0,5,2]]', ''],
            'shift loop builds a condition' => ['<?php function target(){$c=["a"=>1,"b"=>2];$w=[];while($c){$k=array_key_first($c);array_shift($c);$w[]="$k = ?";}return implode(" AND ",$w);}', '["a = ? AND b = ?"]', ''],
            'first and last keys' => ['<?php function target(){return [array_key_first(["a"=>1,"b"=>2]),array_key_last(["a"=>1,"b"=>2]),array_key_first([]),array_key_last([5=>1,2=>2])];}', '[["a","b",null,2]]', ''],
            'slices' => ['<?php function target(){return [array_slice([5=>"a","k"=>"b",9=>"c"],1),array_slice([5=>"a","k"=>"b",9=>"c"],0,2,true),array_slice([1,2,3],-2,-1),array_slice([1,2,3],5)];}', '[[{"k":"b","0":"c"},{"5":"a","k":"b"},[2],[]]]', ''],
            'shift of a string' => ['<?php function target(){$s="x";return array_shift($s);}', '[]', 'TypeError'],
            'shift of an undefined variable' => ['<?php function target(){return array_shift($undefined);}', '[]', 'TypeError'],
            'push onto null' => ['<?php function target(){$n=null;return array_push($n,1);}', '[]', 'TypeError'],
            'push after the maximum index' => ['<?php function target(){$a=[PHP_INT_MAX=>1];return array_push($a,2);}', '[]', 'Error'],
            'push of named values' => ['<?php function target(){$a=[];return array_push($a,...["x"=>1]);}', '[]', 'ArgumentCountError'],
            'unshift of named values' => ['<?php function target(){$a=[];return array_unshift($a,...["x"=>1]);}', '[]', 'ArgumentCountError'],
            'shift of a literal' => ['<?php function target(){return array_shift([1]);}', '[]', 'Error'],
        ];
    }
}
