<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Trusted PHP 8.3 fixtures for case-sensitive initializer identities.
 * @visibility root
 */
final class IdentityPrograms
{
    /**
     * @return array<string,array{string,string}> Captured source and independent result
     */
    public static function cases(): array
    {
        return [
            'function defaults' => ['<?php function helper($X=1,$x=2){return [$X,$x];}function target(){return helper();}','[1,2]'],
            'arrow defaults' => ['<?php function target(){$f=fn($X=1,$x=2)=>[$X,$x];return $f();}','[1,2]'],
            'closure defaults' => ['<?php function target(){$f=function($X=1,$x=2){return [$X,$x];};return $f();}','[1,2]'],
            'method defaults' => ['<?php class Box{function read($X=1,$x=2){return [$X,$x];}}function target(){return (new Box)->read();}','[1,2]'],
            'reference defaults' => ['<?php function helper(&$X=1,&$x=2){$X++;$x++;return [$X,$x];}function target(){return helper();}','[2,3]'],
            'namespace defaults' => ['<?php namespace N{function helper($X=1,$x=2){return [$X,$x];}}namespace{function target(){return N\\helper();}}','[1,2]'],
            'global constants' => ['<?php const VALUE=1;const value=2;function target(){return [VALUE,value];}','[1,2]'],
            'namespace constants' => ['<?php namespace N{const VALUE=1;const value=2;}namespace{function target(){return [N\\VALUE,N\\value];}}','[1,2]'],
            'class constants' => ['<?php class Box{const VALUE=1;const value=2;}function target(){return [Box::VALUE,Box::value];}','[1,2]'],
            'property defaults' => ['<?php class Box{public $Value=1;public $value=2;}function target(){$b=new Box;return [$b->Value,$b->value];}','[1,2]'],
            'enum backing constants' => ['<?php enum E:int{case VALUE=1;case value=2;}function target(){return [E::VALUE->value,E::value->value];}','[1,2]'],
            'function case remains insensitive' => ['<?php function HELPER(){return 3;}function target(){return [helper(),HELPER(),\\helper()];}','[3,3,3]'],
        ];
    }
}
