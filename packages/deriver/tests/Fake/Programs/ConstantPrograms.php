<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Independent PHP 8.3 observations for constant and input evaluation.
 * @visibility root
 */
final class ConstantPrograms
{
    /**
     * Supplies trusted programs and observations obtained from PHP 8.3.
     * @return array<string, array{string, string, string, bool}> Source, normal returns, exception class, diagnostic presence
     */
    public static function cases(): array
    {
        return [
            'inherited-expression' => ['<?php class Base{const A=1+2;}class Child extends Base{}function target(){return Child::A;}', '[3]', '', false],
            'inherited-declaring-self' => ['<?php class Base{const A=1;const B=self::A+2;}class Child extends Base{const A=10;}function target(){return Child::B;}', '[3]', '', false],
            'interface-constant' => ['<?php interface I{const A=1+2;}class Box implements I{}function target(){return Box::A;}', '[3]', '', false],
            'private-access-error' => ['<?php class Box{private const A=1;}function target(){try{return Box::A;}catch(Error $e){return 2;}}', '[2]', '', false],
            'private-access-owner' => ['<?php class Box{private const A=1;static function value(){return self::A;}}function target(){return Box::value();}', '[1]', '', false],
            'inherited-private-error' => ['<?php class Base{private const A=1;}class Child extends Base{static function value(){try{return self::A;}catch(Error $e){return 2;}}}function target(){return Child::value();}', '[2]', '', false],
            'protected-access-child' => ['<?php class Base{protected const A=1;}class Child extends Base{static function value(){return self::A;}}function target(){return Child::value();}', '[1]', '', false],
            'protected-access-error' => ['<?php class Box{protected const A=1;}function target(){try{return Box::A;}catch(Error $e){return 2;}}', '[2]', '', false],
            'trait-private-access' => ['<?php trait T{private const A=1;static function value(){return self::A;}}class Box{use T;}function target(){return Box::value();}', '[1]', '', false],
            'trait-private-error' => ['<?php trait T{private const A=1;}class Box{use T;}function target(){try{return Box::A;}catch(Error $e){return 2;}}', '[2]', '', false],
            'enum-expression-backing' => ['<?php enum Flag:int{case A=1+2;}function target(){return [Flag::A->value,Flag::A->name,Flag::A===Flag::A];}', '[[3,"A",true]]', '', false],
            'enum-class-constant-backing' => ['<?php enum Flag:string{const PREFIX="x";case A=self::PREFIX."y";}function target(){return [Flag::A->value,Flag::A->name];}', '[["xy","A"]]', '', false],
            'typed-class-constant' => ['<?php class Box{const string A="x"."y";const float B=1;}function target(){return [Box::A,Box::B];}', '[["xy",1.0]]', '', false],
            'missing-constant-error' => ['<?php class Box{}function target(){try{return Box::A;}catch(Error $e){return 2;}}', '[2]', '', false],
            'unbacked-enum-identity' => ['<?php enum Flag{case A;}function target(){return [Flag::A->name,Flag::A===Flag::A];}', '[["A",true]]', '', false],
            'nullable-environment-overload' => ['<?php function target(){return [is_array(getenv()),is_array(getenv(null)),is_array(getenv(null,true))];}', '[[true,true,true]]', '', false],
            'random-degenerate-range' => ['<?php function target(){return [random_int(2,2),mt_rand(3,3),rand(4,4)];}', '[[2,3,4]]', '', false],
            'random-reversed-rand-range' => ['<?php function target(){try{rand(2,1);return 1;}catch(ValueError $e){return 2;}}', '[1]', '', false],
        ];
    }
}
