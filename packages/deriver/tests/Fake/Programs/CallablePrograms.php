<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Independent PHP 8.3 observations for callable evaluation.
 * @visibility root
 */
final class CallablePrograms
{
    /**
     * Supplies trusted programs and observations obtained from PHP 8.3.
     * @return array<string, array{string, string, string, bool}> Source, normal returns, exception class, diagnostic presence
     */
    public static function cases(): array
    {
        return [
            'magic-namespaced-function' => ['<?php namespace N{function names($a=__FUNCTION__,$b=__METHOD__){return [$a,$b,__FUNCTION__,__METHOD__,__NAMESPACE__,__CLASS__];}}namespace{function target(){return N\\names();}}', '[["N\\\\names","N\\\\names","N\\\\names","N\\\\names","N",""]]', '', false],
            'magic-method' => ['<?php namespace N{class Box{function names($a=__FUNCTION__,$b=__METHOD__){return [$a,$b,__FUNCTION__,__METHOD__,__NAMESPACE__,__CLASS__];}}}namespace{function target(){return (new N\\Box)->names();}}', '[["names","N\\\\Box::names","names","N\\\\Box::names","N","N\\\\Box"]]', '', false],
            'magic-closure-method' => ['<?php namespace N{class Box{function names(){return (fn()=>[__FUNCTION__,__METHOD__,__NAMESPACE__,__CLASS__])();}}}namespace{function target(){return (new N\\Box)->names();}}', '[["N\\\\{closure}","N\\\\{closure}","N","N\\\\Box"]]', '', false],
            'magic-closure-function' => ['<?php namespace N{function names(){return (function(){return [__FUNCTION__,__METHOD__,__NAMESPACE__,__CLASS__];})();}}namespace{function target(){return N\\names();}}', '[["N\\\\{closure}","N\\\\{closure}","N",""]]', '', false],
            'magic-global-closure' => ['<?php function target(){return (fn()=>[__FUNCTION__,__METHOD__,__NAMESPACE__,__CLASS__])();}', '[["{closure}","{closure}","",""]]', '', false],
            'array-call-extra-entry' => ['<?php class Box{static function run(){return 1;}}function target(){$f=["Box","run",2];try{return $f();}catch(Error $e){return 3;}}', '[3]', '', false],
            'array-call-extra-entry-capture' => ['<?php class Box{static function run(){return 1;}}function target(){$f=["Box","run",2];try{$g=$f(...);return $g();}catch(Error $e){return 3;}}', '[3]', '', false],
            'array-call-wrong-keys' => ['<?php class Box{static function run(){return 1;}}function target(){$f=[1=>"Box",2=>"run"];try{return $f();}catch(Error $e){return 3;}}', '[3]', '', false],
            'array-call-key-order' => ['<?php class Box{static function run(){return 1;}}function target(){$f=[1=>"run",0=>"Box"];return $f();}', '[1]', '', false],
            'array-call-reference-elements' => ['<?php class Box{static function run(&$x){$x=2;return 1;}}function target(){$c="Box";$m="run";$f=[&$c,&$m];$x=0;$r=$f($x);return [$r,$x];}', '[[1,2]]', '', false],
            'array-capture-reference-elements' => ['<?php class Box{static function run(&$x){$x=2;return 1;}}function target(){$c="Box";$m="run";$f=[&$c,&$m];$g=$f(...);$m="missing";$x=0;$r=$g($x);return [$r,$x];}', '[[1,2]]', '', false],
            'private-escaped' => ['<?php class Box{private function hidden(&$x){$x=3;return 2;}function callback(){return $this->hidden(...);}}function target(){$f=(new Box)->callback();$a=1;return [$f($a),$a];}', '[[2,3]]', '', false],
            'private-creation-error' => ['<?php class Box{private function hidden(){return 1;}}function target(){$b=new Box;try{$f=$b->hidden(...);}catch(Error $e){return 2;}return 3;}', '[2]', '', false],
            'private-array-creation' => ['<?php class Box{private function hidden(){return 1;}function callback(){return [$this,"hidden"](...);}}function target(){$f=(new Box)->callback();return $f();}', '[1]', '', false],
            'private-string-creation' => ['<?php class Box{private static function hidden(){return 1;}static function callback(){return "Box::hidden"(...);}}function target(){$f=Box::callback();return $f();}', '[1]', '', false],
            'private-static-creation' => ['<?php class Box{private static function hidden(){return 1;}static function callback(){return self::hidden(...);}}function target(){$f=Box::callback();return $f();}', '[1]', '', false],
            'inherited-self' => ['<?php class Base{static function name(){return static::class;}static function callback(){return self::name(...);}}class Child extends Base{}function target(){$f=Child::callback();return $f();}', '["Child"]', '', false],
            'inherited-parent' => ['<?php class Base{static function name(){return static::class;}}class Child extends Base{static function callback(){return parent::name(...);}}function target(){$f=Child::callback();return $f();}', '["Child"]', '', false],
            'inherited-instance-parent' => ['<?php class Base{function name(){return [static::class,$this->x];}}class Child extends Base{public $x=2;function callback(){return parent::name(...);}}function target(){$f=(new Child)->callback();return $f();}', '[["Child",2]]', '', false],
            'explicit-base' => ['<?php class Base{static function name(){return static::class;}static function callback(){return Base::name(...);}}class Child extends Base{}function target(){$f=Child::callback();return $f();}', '["Base"]', '', false],
            'ordinary-forward-static' => ['<?php class Base{static function name(){return static::class;}static function call(){return self::name();}}class Child extends Base{}function target(){return Child::call();}', '["Child"]', '', false],
            'ordinary-call-does-not-rebind' => ['<?php class Other{static function name(){return 1;}}class Base{static function call(){Other::name();return static::class;}}class Child extends Base{}function target(){return Child::call();}', '["Child"]', '', false],
            'closure-called-class' => ['<?php class Base{static function callback(){return static fn()=>static::class;}}class Child extends Base{}function target(){$f=Child::callback();return $f();}', '["Child"]', '', false],
            'closure-static-this' => ['<?php class Box{function callback(){return static function(){return isset($this);};}}function target(){$f=(new Box)->callback();return $f();}', '[false]', '', false],
            'magic-escaped' => ['<?php class Box{public $x=1;function __call($name,$args){$this->x++;return [$name,$args,$this->x];}}function target(){$b=new Box;$f=$b->missing(...);return [$f(2),$b->x];}', '[[["missing",[2],2],2]]', '', false],
            'magic-static-escaped' => ['<?php class Box{static function __callStatic($name,$args){return [$name,$args,static::class];}static function callback(){return self::missing(...);}}class Child extends Box{}function target(){$f=Child::callback();return $f(x:2);}', '[["missing",{"x":2},"Child"]]', '', false],
            'invoke-object' => ['<?php class Box{function __invoke(&$x){$x=2;return 3;}}function target(){$b=new Box;$a=1;$f=$b(...);return [$f($a),$a];}', '[[3,2]]', '', false],
            'closure-first-class-identity' => ['<?php function target(){$f=fn()=>1;$g=$f(...);return $f===$g;}', '[true]', '', false],
            'first-class-identity' => ['<?php function f(){}function target(){$a=f(...);$b=f(...);return [$a===$a,$a===$b];}', '[[true,false]]', '', false],
            'ordinary-array-private-error' => ['<?php class Box{private function hidden(){return 1;}function callback(){return [$this,"hidden"];}}function target(){$f=(new Box)->callback();try{return $f();}catch(Error $e){return 2;}}', '[2]', '', false],
            'ordinary-string-inherited' => ['<?php class Base{static function name(){return static::class;}}class Child extends Base{}function target(){$f="Child::name";return $f();}', '["Child"]', '', false],
            'parent-target-frozen' => ['<?php class Base{function foo(){return 1;}}class Child extends Base{function foo(){return 2;}function callback(){return parent::foo(...);}}function target(){$f=(new Child)->callback();return $f();}', '[1]', '', false],
            'private-target-frozen' => ['<?php class Base{private function foo(){return 1;}function callback(){return $this->foo(...);}}class Child extends Base{function foo(){return 2;}}function target(){$f=(new Child)->callback();return $f();}', '[1]', '', false],
            'caller-this-restored' => ['<?php class Base{public $x=1;function foo(){return $this->x;}function callback(){return self::foo(...);}}class Other{public $x=2;function run($f){return [$f(),$this->x];}}function target(){$f=(new Base)->callback();return (new Other)->run($f);}', '[[1,2]]', '', false],
            'callable-original-this-live' => ['<?php class Box{public $x=1;function foo(){return $this->x;}}function target(){$b=new Box;$f=$b->foo(...);$b->x=2;return $f();}', '[2]', '', false],
            'closure-fresh-same-site' => ['<?php function make(){return fn()=>1;}function target(){$a=make();$b=make();return [$a===$a,$a===$b];}', '[[true,false]]', '', false],
            'callable-fresh-same-site' => ['<?php function f(){return 1;}function make(){return f(...);}function target(){$a=make();$b=make();return [$a===$a,$a===$b];}', '[[true,false]]', '', false],
            'callable-closure-class' => ['<?php function f(){return 1;}function target(){$a=f(...);return [get_class($a),$a instanceof Closure,is_object($a),is_callable($a)];}', '[["Closure",true,true,true]]', '', false],
            'namespace-fallback' => ['<?php namespace N{function build(){$f=strlen(...);return [$f("abc"),strlen("abcd")];}}namespace{function target(){return \\N\\build();}}', '[[3,4]]', '', false],
            'captured-strict-invocation' => ['<?php declare(strict_types=1);function f(int $x){return $x;}function target(){$g=f(...);try{return $g("2");}catch(\\TypeError $e){return 3;}}', '[3]', '', false],
            'captured-weak-invocation' => ['<?php function f(int $x){return $x;}function target(){$g=f(...);return $g("2");}', '[2]', '', false],
            'forward-after-nested-call' => ['<?php class Other{static function name(){return 1;}}class Base{static function name(){return static::class;}static function callback(){Other::name();return self::name(...);}}class Child extends Base{}function target(){$f=Child::callback();return $f();}', '["Child"]', '', false],
            'closure-called-class-interleaved' => ['<?php class Base{static function callback(){return static fn()=>static::class;}}class Child extends Base{}class Other extends Base{}function target(){$a=Child::callback();$b=Other::callback();return [$a(),$b()];}', '[["Child","Other"]]', '', false],
        ];
    }
}
