<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Independent PHP 8.3 observations for live foreach array mutation and non-iterable subjects.
 * @visibility root
 */
final class IterationPrograms
{
    /**
     * Supplies trusted programs and observations obtained from PHP 8.3.
     * @return array<string, array{string, string, string, bool}> Source, normal returns, exception class, diagnostic presence
     */
    public static function cases(): array
    {
        return [
            'remove-current' => ['<?php function target(){$a=[10,20,30];$out=[];foreach($a as $k=>&$v){$out[]=[$k,$v];if($k===0)unset($a[0]);}return $out;}', '[[[0,10],[1,20],[2,30]]]', '', false],
            'remove-next' => ['<?php function target(){$a=[10,20,30];$out=[];foreach($a as $k=>&$v){$out[]=[$k,$v];if($k===0)unset($a[1]);}return $out;}', '[[[0,10],[2,30]]]', '', false],
            'remove-previous' => ['<?php function target(){$a=[10,20,30];$out=[];foreach($a as $k=>&$v){$out[]=[$k,$v];if($k===1)unset($a[0]);}return $out;}', '[[[0,10],[1,20],[2,30]]]', '', false],
            'append-during-iteration' => ['<?php function target(){$a=[10,20];$out=[];foreach($a as $k=>&$v){$out[]=[$k,$v];if($k===0)$a[]=30;}return $out;}', '[[[0,10],[1,20],[2,30]]]', '', false],
            'reinsert-current' => ['<?php function target(){$a=[10,20,30];$out=[];$n=0;foreach($a as $k=>&$v){$out[]=[$k,$v];if($n++===0){unset($a[0]);$a[0]=10;}}return $out;}', '[[[0,10],[1,20],[2,30],[0,10]]]', '', false],
            'reinsert-next' => ['<?php function target(){$a=[10,20,30];$out=[];foreach($a as $k=>&$v){$out[]=[$k,$v];if($k===0){unset($a[1]);$a[1]=20;}}return $out;}', '[[[0,10],[2,30],[1,20]]]', '', false],
            'replace-array' => ['<?php function target(){$a=[10,20,30];$out=[];$n=0;foreach($a as $k=>&$v){$out[]=[$k,$v];if($n++===0)$a=[40,50];}return $out;}', '[[[0,10],[0,40],[1,50]]]', '', false],
            'assign-same-array' => ['<?php function target(){$a=[10,20];$out=[];foreach($a as $k=>&$v){$out[]=[$k,$v];$a=$a;}return $out;}', '[[[0,10],[1,20]]]', '', false],
            'replace-empty' => ['<?php function target(){$a=[10,20];$out=[];foreach($a as $k=>&$v){$out[]=[$k,$v];$a=[];}return $out;}', '[[[0,10]]]', '', false],
            'nested-array-replacement' => ['<?php function target(){$root=["items"=>[10,20]];$out=[];$n=0;foreach($root["items"] as $k=>&$v){$out[]=[$k,$v];if($n++===0)$root["items"]=[40,50];}return $out;}', '[[[0,10],[0,40],[1,50]]]', '', false],
            'nested-array-remove' => ['<?php function target(){$root=["items"=>[10,20,30]];$out=[];foreach($root["items"] as $k=>&$v){$out[]=[$k,$v];if($k===0)unset($root["items"][0]);}return $out;}', '[[[0,10],[1,20],[2,30]]]', '', false],
            'helper-removes-current' => ['<?php function remove(&$a){unset($a[0]);}function target(){$a=[10,20,30];$out=[];foreach($a as $k=>&$v){$out[]=[$k,$v];if($k===0)remove($a);}return $out;}', '[[[0,10],[1,20],[2,30]]]', '', false],
            'rebind-variable' => ['<?php function target(){$a=[10,20];$b=[30,40];$out=[];foreach($a as $k=>&$v){$out[]=[$k,$v];if($k===0)$a=&$b;}return [$out,$a];}', '[[[[0,10],[1,20]],[30,40]]]', '', false],
            'copy-before-iteration' => ['<?php function target(){$a=[10,20];$b=$a;foreach($a as &$v){$v++;}unset($v);return [$a,$b];}', '[[[11,21],[10,20]]]', '', false],
            'by-value-snapshot' => ['<?php function target(){$a=[10,20,30];$out=[];foreach($a as $k=>$v){$out[]=[$k,$v];if($k===0){unset($a[1]);$a[]=40;}}return $out;}', '[[[0,10],[1,20],[2,30]]]', '', false],
            'remove-string-keys' => ['<?php function target(){$a=["a"=>1,"b"=>2,"c"=>3];$out=[];foreach($a as $k=>&$v){$out[]=[$k,$v];if($k==="a"){unset($a["b"]);$a["b"]=4;}}return $out;}', '[[["a",1],["c",3],["b",4]]]', '', false],
            'null-subject' => ['<?php function target(){$rows=null;$out=[];foreach($rows as $r){$out[]=$r;}return $out;}', '[[]]', '', true],
            'undefined-subject' => ['<?php function target(){$out=[];foreach($rows as $k=>$r){$out[]=$r;}return [$out,isset($k),isset($r)];}', '[[[],false,false]]', '', true],
            'scalar-subjects' => ['<?php function target(){$out=[];foreach([1,"abc",true,1.5,false] as $s){foreach($s as $k=>$v){$out[]=$k;}}return $out;}', '[[]]', '', true],
            'by-reference-null' => ['<?php function target(){$rows=null;$out=[];foreach($rows as &$r){$out[]=1;}return [$out,$rows,isset($r)];}', '[[[],null,false]]', '', true],
            'by-reference-undefined' => ['<?php function target(){$out=[];foreach($rows as &$r){$out[]=1;}return [$out,isset($rows),isset($r)];}', '[[[],false,false]]', '', true],
            'by-reference-missing-element' => ['<?php function target(){foreach($x["k"] as &$r){$x["seen"]=true;}return $x;}', '[{"k":null}]', '', true],
            'by-reference-int' => ['<?php function target(){$x=5;foreach($x as $k=>&$r){$x=6;}return [$x,isset($k)];}', '[[5,false]]', '', true],
            'nullable-parameter' => ['<?php function rows(?array $rows){$out=[];foreach($rows as $r){$out[]=$r;}return $out;} function target(){return [rows(null),rows([1,2])];}', '[[[],[1,2]]]', '', true],
        ];
    }
}
