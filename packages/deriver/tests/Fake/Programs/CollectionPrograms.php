<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Trusted collection fixtures with independently specified PHP 8.3 values and alias effects.
 * @visibility root
 */
final class CollectionPrograms
{
    /**
     * @return array<string,array{string,string}> Source and expected JSON observation
     */
    public static function cases(): array
    {
        return [
            'null map retains reference cells' => ['<?php function target(){$x=1;$a=["x"=>&$x];$b=array_map(null,$a);$x=2;return [$a,$b];}', '[{"x":2},{"x":2}]'],
            'null map writes through reference cells' => ['<?php function target(){$x=1;$a=["x"=>&$x];$b=array_map(null,$a);$b["x"]=3;return [$x,$a,$b];}', '[3,{"x":3},{"x":3}]'],
            'callback map detaches returned values' => ['<?php function target(){$x=1;$a=["x"=>&$x];$b=array_map(fn($v)=>$v,$a);$x=2;return [$a,$b];}', '[{"x":2},{"x":1}]'],
            'filter retains reference cells' => ['<?php function target(){$x=1;$a=["x"=>&$x];$b=array_filter($a);$x=2;return [$a,$b];}', '[{"x":2},{"x":2}]'],
            'callback filter retains reference cells' => ['<?php function target(){$x=1;$a=["x"=>&$x];$b=array_filter($a,fn($v)=>true);$b["x"]=3;return [$x,$a];}', '[3,{"x":3}]'],
            'null map retains sparse keys' => ['<?php function target(){return array_map(null,[7=>null,"key"=>4]);}', '{"7":null,"key":4}'],
            'callback map preserves keys' => ['<?php function target(){return array_map(fn($v)=>$v*2,[7=>3,"key"=>4]);}', '{"7":6,"key":8}'],
            'null map copies ordinary arrays' => ['<?php function target(){$a=[["x"=>1]];$b=array_map(null,$a);$b[0]["x"]=2;return [$a,$b];}', '[[{"x":1}],[{"x":2}]]'],
            'default filter truthiness' => ['<?php function target(){return array_filter([0,false,"",null,[],"0",1]);}', '{"6":1}'],
            'filter value mode' => ['<?php function target(){return array_filter(["keep"=>3,"drop"=>1],fn($v)=>$v>1,0);}', '{"keep":3}'],
            'filter key mode' => ['<?php function target(){return array_filter(["keep"=>0,"drop"=>1],fn($k)=>$k==="keep",2);}', '{"keep":0}'],
            'filter both mode' => ['<?php function target(){return array_filter(["keep"=>3,"drop"=>3],fn($v,$k)=>$v===3&&$k==="keep",1);}', '{"keep":3}'],
            'map callback effects in order' => ['<?php function target(){$n=0;$a=array_map(function($v)use(&$n){$n+=$v;return $n;},[2,3]);return [$a,$n];}', '[[2,5],5]'],
            'filter callback effects in order' => ['<?php function target(){$n=0;$a=array_filter([2,3],function($v)use(&$n){$n+=$v;return $n>2;});return [$a,$n];}', '[{"1":3},5]'],
            'reduce callback order' => ['<?php function target(){return array_reduce(["a","b"],fn($carry,$v)=>$carry.$v,"start:");}', '"start:ab"'],
            'reduce default seed' => ['<?php function target(){return array_reduce([1,2],fn($carry,$v)=>($carry??0)+$v);}', '3'],
            'map stops after callback exception' => ['<?php function target(){$n=0;try{array_map(function($v)use(&$n){$n+=$v;if($v===2){throw new RuntimeException;}return $v;},[1,2,4]);}catch(RuntimeException $e){}return $n;}', '3'],
            'filter stops after callback exception' => ['<?php function target(){$n=0;try{array_filter([1,2,4],function($v)use(&$n){$n+=$v;if($v===2){throw new RuntimeException;}return true;});}catch(RuntimeException $e){}return $n;}', '3'],
            'reduce stops after callback exception' => ['<?php function target(){$n=0;try{array_reduce([1,2,4],function($carry,$v)use(&$n){$n+=$v;if($v===2){throw new RuntimeException;}return $carry+$v;},0);}catch(RuntimeException $e){}return $n;}', '3'],
            'empty map does not call callback' => ['<?php function target(){return array_map(function($v){throw new Error;},[]);}', '[]'],
            'empty filter does not call callback' => ['<?php function target(){return array_filter([],function($v){throw new Error;});}', '[]'],
            'empty reduce keeps seed' => ['<?php function target(){return array_reduce([],function($a,$v){throw new Error;},7);}', '7'],
            'empty reduce default seed' => ['<?php function target(){return array_reduce([],function($a,$v){throw new Error;});}', 'null'],
            'map retains child object identity' => ['<?php class Box{public $value=1;}function target(){$box=new Box;$a=[$box];$b=array_map(null,$a);$b[0]->value=7;return [$a[0]->value,$b[0]===$box];}', '[7,true]'],
        ];
    }
}
