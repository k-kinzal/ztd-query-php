<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Trusted PHP 8.3 fixtures distinguishing literal class references from runtime values.
 * @visibility root
 */
final class ClassNamePrograms
{
    /**
     * @return array<string,array{string,string}> Captured source and independently checked JSON result
     */
    public static function cases(): array
    {
        return [
            'literal missing class' => ['<?php function target(){return Missing::class;}','"Missing"'],
            'literal spelling' => ['<?php class Box{}function target(){return bOx::class;}','"bOx"'],
            'resolved namespace alias' => ['<?php namespace N{class Box{}}namespace{use N\\Box as Alias;function target(){return Alias::class;}}','"N\\\\Box"'],
            'object class name' => ['<?php class Box{}function target(){$x=new Box;return $x::class;}','"Box"'],
            'canonical object class name' => ['<?php class Box{}function target(){$x=new bOx;return $x::class;}','"Box"'],
            'closure class name' => ['<?php function target(){$x=fn()=>1;return $x::class;}','"Closure"'],
            'enum class name' => ['<?php enum E{case A;}function target(){$x=E::A;return $x::class;}','"E"'],
            'native exception class name' => ['<?php function target(){$x=new RuntimeException;return $x::class;}','"RuntimeException"'],
            'string class name rejected' => ['<?php class Box{}function target(){$x="Box";try{return $x::class;}catch(TypeError $e){return "type";}}','"type"'],
            'array class name rejected' => ['<?php function target(){$x=[];try{return $x::class;}catch(TypeError $e){return "type";}}','"type"'],
            'integer class name rejected' => ['<?php function target(){$x=1;try{return $x::class;}catch(TypeError $e){return "type";}}','"type"'],
            'null class name rejected' => ['<?php function target(){$x=null;try{return $x::class;}catch(TypeError $e){return "type";}}','"type"'],
            'literal class dynamic name' => ['<?php class Box{}function target(){$name="class";return bOx::{$name};}','"Box"'],
            'string class dynamic name' => ['<?php class Box{}function target(){$class="bOx";$name="class";return $class::{$name};}','"Box"'],
            'object class dynamic name' => ['<?php class Box{}function target(){$class=new Box;$name="class";return $class::{$name};}','"Box"'],
            'object constant' => ['<?php class Box{const VALUE=3;}function target(){$x=new Box;return $x::VALUE;}','3'],
            'closure constant error' => ['<?php function target(){$x=fn()=>1;try{return $x::VALUE;}catch(Error $e){return "error";}}','"error"'],
            'unbound self closure' => ['<?php function target(){$f=fn()=>self::class;try{return $f();}catch(Error $e){return "error";}}','"error"'],
            'unbound parent closure' => ['<?php function target(){$f=fn()=>parent::class;try{return $f();}catch(Error $e){return "error";}}','"error"'],
            'unbound static closure' => ['<?php function target(){$f=fn()=>static::class;try{return $f();}catch(Error $e){return "error";}}','"error"'],
            'trait parent' => ['<?php trait T{function value(){return parent::class;}}class Base{}class Box extends Base{use T;}function target(){return (new Box)->value();}','"Base"'],
            'trait missing parent runtime' => ['<?php trait T{function value(){try{return parent::class;}catch(Error $e){return "error";}}}class Box{use T;}function target(){return (new Box)->value();}','"error"'],
            'late static class' => ['<?php class Base{static function value(){return [self::class,static::class];}}class Box extends Base{}function target(){return Box::value();}','["Base","Box"]'],
            'dynamic self constant' => ['<?php class Box{const VALUE=7;static function value(){$class="self";try{return $class::VALUE;}catch(Error $e){return "error";}}}function target(){return Box::value();}','"error"'],
            'dynamic parent constant' => ['<?php class Base{const VALUE=7;}class Box extends Base{static function value(){$class="parent";try{return $class::VALUE;}catch(Error $e){return "error";}}}function target(){return Box::value();}','"error"'],
            'dynamic static constant' => ['<?php class Box{const VALUE=7;static function value(){$class="static";try{return $class::VALUE;}catch(Error $e){return "error";}}}function target(){return Box::value();}','"error"'],
            'dynamic relative class keyword' => ['<?php class Box{static function value(){$class="self";$name="class";try{return $class::{$name};}catch(Error $e){return "error";}}}function target(){return Box::value();}','"error"'],
        ];
    }

    /**
     * @return array<string,array{string,string}> Invalid source and required compiler diagnostic fragment
     */
    public static function invalid(): array
    {
        return [
            'free self' => ['<?php function target(){return self::class;}','no class scope'],
            'free parent' => ['<?php function target(){return parent::class;}','no class scope'],
            'free static' => ['<?php function target(){return static::class;}','no class scope'],
            'missing parent' => ['<?php class Box{function value(){return parent::class;}}function target(){return 1;}','has no parent'],
            'nested named function' => ['<?php class Box{function value(){function nested(){return self::class;}}}function target(){return 1;}','no class scope'],
            'free static call' => ['<?php function target(){return self::value();}','no class scope'],
            'free allocation' => ['<?php function target(){return new self;}','no class scope'],
            'free static property' => ['<?php function target(){return parent::$value;}','no class scope'],
            'free type test' => ['<?php function target(){return new stdClass instanceof self;}','no class scope'],
            'missing parent allocation' => ['<?php class Box{function value(){return new parent;}}function target(){return 1;}','has no parent'],
            'missing parent initializer' => ['<?php class Box{const VALUE=parent::class;}function target(){return 1;}','has no parent'],
            'free default initializer' => ['<?php function value($x=self::class){}function target(){return 1;}','no class scope'],
        ];
    }
}
