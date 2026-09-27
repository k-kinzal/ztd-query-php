<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Records PHP 8.3 assignment-pattern values, reference effects, and diagnostics.
 * @visibility root
 */
final class DestructuringPrograms
{
    /**
     * @return array<string,array{string,string,string,bool}> Source, normal returns, exception, diagnostic presence
     */
    public static function cases(): array
    {
        return [
            'ArrayAccess values' => ['<?php class Box implements ArrayAccess{public array $data=[1,2];public array $log=[];function offsetGet(mixed $k):mixed{$this->log[]=$k;return $this->data[$k];}function offsetExists(mixed $k):bool{return true;}function offsetSet(mixed $k,mixed $v):void{$this->data[$k]=$v;}function offsetUnset(mixed $k):void{unset($this->data[$k]);}}function target(){$b=new Box;[$x,$y]=$b;return [$x,$y,$b->log];}', '[[1,2,[0,1]]]', '', false],
            'ArrayAccess reference return' => ['<?php class Box implements ArrayAccess{public array $data=[1,2];function &offsetGet(mixed $k):mixed{return $this->data[$k];}function offsetExists(mixed $k):bool{return true;}function offsetSet(mixed $k,mixed $v):void{$this->data[$k]=$v;}function offsetUnset(mixed $k):void{unset($this->data[$k]);}}function target(){$b=new Box;[&$x,$y]=$b;$x=9;return [$b->data,$x,$y];}', '[[[9,2],9,2]]', '', false],
            'ArrayAccess temporary reference notice' => ['<?php class Box implements ArrayAccess{public array $data=[1,2];function offsetGet(mixed $k):mixed{return $this->data[$k];}function offsetExists(mixed $k):bool{return true;}function offsetSet(mixed $k,mixed $v):void{$this->data[$k]=$v;}function offsetUnset(mixed $k):void{unset($this->data[$k]);}}function target(){$b=new Box;[&$x,$y]=$b;$x=9;return [$b->data,$x,$y];}', '[[[1,2],9,2]]', '', true],
            'keys evaluate in entry order' => ['<?php function target(){$k=0;[$k=>$x,$k++=>$y]=[10,20];return [$k,$x,$y];}', '[[1,10,10]]', '', false],
            'undefined source initialized by reference' => ['<?php function target(){[&$x]=$a;return [$a,$x];}', '[[[null],null]]', '', false],
            'array reference' => ['<?php function target(){$a=[1,2];[&$x,$y]=$a;$x=7;return [$a,$x,$y];}', '[[[7,2],7,2]]', '', false],
            'nested reference' => ['<?php function target(){$a=[[1,2]];[[&$x,$y]]=$a;$x=7;return [$a,$x,$y];}', '[[[[7,2]],7,2]]', '', false],
            'missing reference' => ['<?php function target(){$a=[];[&$x]=$a;return [$a,$x];}', '[[[null],null]]', '', false],
            'string values' => ['<?php function target(){[$x,$y]="ab";return [$x,$y];}', '[[null,null]]', '', false],
            'integer values' => ['<?php function target(){[$x,$y]=4;return [$x,$y];}', '[[null,null]]', '', false],
            'float values' => ['<?php function target(){[$x,$y]=4.5;return [$x,$y];}', '[[null,null]]', '', false],
            'boolean values' => ['<?php function target(){[$x,$y]=true;return [$x,$y];}', '[[null,null]]', '', false],
            'null values' => ['<?php function target(){[$x,$y]=null;return [$x,$y];}', '[[null,null]]', '', false],
            'missing value warns' => ['<?php function target(){[$x,$y]=[1];return [$x,$y];}', '[[1,null]]', '', true],
            'snapshot before overlapping value writes' => ['<?php function target(){$a=[1,2];[$a[1],$x]=$a;return [$a,$x];}', '[[[1,1],2]]', '', false],
            'live source after overlapping reference binding' => ['<?php function target(){$a=[1,2];[&$a[1],$x]=$a;return [$a,$x];}', '[[[1,1],1]]', '', false],
            'integer reference error' => ['<?php function target(){$a=4;try{[&$x]=$a;}catch(Throwable $e){return [get_class($e),$a];}return [$a,$x];}', '[["Error",4]]', '', false],
            'null reference creates array' => ['<?php function target(){$a=null;[&$x]=$a;return [$a,$x];}', '[[[null],null]]', '', false],
            'string reference error' => ['<?php function target(){$a="abc";try{[&$x]=$a;}catch(Throwable $e){return [get_class($e),$a];}return [$a,$x];}', '[["Error","abc"]]', '', false],
            'ordinary function return reference notice' => ['<?php function make(){return [1];}function target(){[&$x]=make();$x=7;return $x;}', '[7]', '', true],
            'foreach reference pattern' => ['<?php function target(){$a=[[1,2],[3,4]];foreach($a as [&$x,$y]){$x+=10;}$x=20;return [$a,$x,$y];}', '[[[[11,2],[20,4]],20,4]]', '', false],
            'foreach temporary pattern' => ['<?php function target(){$sum=0;foreach([[1],[2]] as [&$x]){$sum+=$x;}$x=20;return [$sum,$x];}', '[[3,20]]', '', false],
            'foreach value before key' => ['<?php function target(){$a=[[5]];foreach($a as $x=>[$x]){}return $x;}', '[0]', '', false],
            'foreach reference before key' => ['<?php function target(){$a=[[5]];foreach($a as $x=>[&$x]){}return [$a,$x];}', '[[[[0]],0]]', '', false],
            'foreach ordinary value before key' => ['<?php function target(){foreach([5] as $x=>$x){}return $x;}', '[0]', '', false],
            'anchored source variable' => ['<?php function target(){$a=[1,2];[&$a,$x]=$a;return [$a,$x];}', '[[1,2]]', '', false],
            'anchored nested source element' => ['<?php function target(){$a=[[1,2],9];[&$a[0],$x]=$a[0];return [$a,$x];}', '[[[1,9],2]]', '', false],
            'assignment expression reference result' => ['<?php function target(){$a=[1,2];$b=([&$x,$y]=$a);$x=7;return [$a,$b,$x,$y];}', '[[[7,2],[7,2],7,2]]', '', false],
            'assignment expression value result' => ['<?php function target(){$a=[1,2];$b=([$x,$y]=$a);$x=7;return [$a,$b,$x,$y];}', '[[[1,2],[1,2],7,2]]', '', false],
            'holes keep numeric positions' => ['<?php function target(){list(,$x,,$y)=[1,2,3,4];return [$x,$y];}', '[[2,4]]', '', false],
            'keyed dynamic pattern' => ['<?php function target(){$k="b";["a"=>$x,$k=>$y]=["b"=>2,"a"=>1];return [$x,$y];}', '[[1,2]]', '', false],
            'nested keyed references' => ['<?php function target(){$a=["outer"=>["inner"=>1]];["outer"=>["inner"=>&$x]]=$a;$x=7;return [$a,$x];}', '[[{"outer":{"inner":7}},7]]', '', false],
            'list syntax references' => ['<?php function target(){$a=[1,2];list(&$x,$y)=$a;$x=8;return [$a,$x,$y];}', '[[[8,2],8,2]]', '', false],
            'declared reference return' => ['<?php function &source(&$a){return $a;}function target(){$a=[1];[&$x]=source($a);$x=8;return $a;}', '[[8]]', '', false],
            'property source reference' => ['<?php class Box{public array $items=[1];}function target(){$b=new Box;[&$x]=$b->items;$x=9;return $b->items;}', '[[9]]', '', false],
            'readonly source reference rejected' => ['<?php class Box{function __construct(public readonly array $items=[1]){}}function target(){$b=new Box;try{[&$x]=$b->items;}catch(Error $e){return $b->items;}return 999;}', '[[1]]', '', false],
            'typed property destination coerces source' => ['<?php class Box{public int $x=0;}function target(){$a=["12"];$b=new Box;[&$b->x]=$a;return [$a,$b->x];}', '[[[12],12]]', '', false],
            'typed property destination rejects reference' => ['<?php declare(strict_types=1);class Box{public int $x=0;}function target(){$a=["12"];$b=new Box;try{[&$b->x]=$a;}catch(TypeError $e){return [$a,$b->x];}return 999;}', '[[["12"],0]]', '', false],
            'plain object destructuring error' => ['<?php class Box{}function target(){$b=new Box;try{[$x]=$b;}catch(Error $e){return 1;}return 999;}', '[1]', '', false],
            'nested scalar destructuring' => ['<?php function target(){[[$x],[$y]]=["abc",null];return [$x,$y];}', '[[null,null]]', '', false],
            'foreach temporary function result' => ['<?php function source(){return [[1],[2]];}function target(){$sum=0;foreach(source() as [&$x]){$sum+=$x;}$x=8;return [$sum,$x];}', '[[3,8]]', '', false],
            'foreach ordinary reference temporary' => ['<?php function target(){foreach([1,2] as &$x){$x+=10;}return $x;}', '[12]', '', false],
        ];
    }

    /**
     * @return array<string,array{string,string}> Invalid source and target diagnostic fragment
     */
    public static function invalid(): array
    {
        return [
            'temporary object property source' => ['<?php class Box{public array $items=[1];}function target(){[&$x]=(new Box)->items;}', 'Cannot use temporary expression in write context'],
            'literal reference source' => ['<?php function target(){[&$x]=[1];}', 'Cannot assign reference to non referenceable value'],
            'scalar reference source' => ['<?php function target(){[&$x]=1;}', 'Cannot assign reference to non referenceable value'],
            'nested literal reference source' => ['<?php function target(){[[&$x]]=[[1]];}', 'Cannot assign reference to non referenceable value'],
            'empty pattern' => ['<?php function target(){[]=[1];}', 'Cannot use empty list'],
            'all holes' => ['<?php function target(){list(,)=[1];}', 'Cannot use empty list'],
            'mixed keyed pattern' => ['<?php function target(){[0=>$x,$y]=[1,2];}', 'Cannot mix keyed and unkeyed array entries'],
            'keyed hole' => ['<?php function target(){[0=>$x, ,2=>$y]=[1,2,3];}', 'Cannot use empty array entries in keyed array assignment'],
            'mixed nested style' => ['<?php function target(){list([$x])=[[1]];}', 'Cannot mix [] and list()'],
            'spread pattern' => ['<?php function target(){[$x,...$y]=[1,2];}', 'Spread operator is not supported in assignments'],
            'nullsafe reference source' => ['<?php function target($b){[&$x]=$b?->items;}', 'Cannot take reference of a nullsafe chain'],
        ];
    }
}
