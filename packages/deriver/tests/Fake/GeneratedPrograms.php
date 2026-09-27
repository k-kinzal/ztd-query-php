<?php

declare(strict_types=1);

namespace Tests\Fake;

/**
 * A deterministic grammar of small side-effect-free and stateful PHP fixtures.
 * @visibility root
 */
final class GeneratedPrograms
{
    /**
     * Produces independently generated programs for concrete runtime comparison.
     * @return array<string, array{string}> Reproducible fixture corpus
     */
    public static function cases(): array
    {
        $cases = [];
        foreach ([-5, -1, 0, 1, 2, 5] as $left) {
            foreach ([-2, 0, 1, 3] as $right) {
                foreach (['+', '-', '*', '<=>', '===', '==', '<', '<=', '>', '>='] as $operator) {
                    $cases['scalar:' . $left . ':' . $operator . ':' . $right] = ['<?php function target(){return ' . '(' . $left . ') ' . $operator . ' (' . $right . ')' . ';}'];
                }
                $cases['alias:' . $left . ':' . $right] = ['<?php function target(){$x=' . $left . ';$a=[&$x];$b=$a;$b[0]=' . $right . ';return [$x,$a,$b];}'];
                $cases['branch:' . $left . ':' . $right] = ['<?php function f($x,$y){if($x>$y){$a="greater";$b=$x;}else{$a="less";$b=$y;}return [$a,$b];}function target(){return f(' . $left . ',' . $right . ');}'];
            }
        }
        foreach (range(0, 8) as $count) {
            $cases['loop:' . $count] = ['<?php function target(){$s="";for($i=0;$i<' . $count . ';$i++){$s.="x";}return $s;}'];
            $cases['recursive:' . $count] = ['<?php function f($n){if($n<=0)return 0;$tail=f($n-1);return $n+$tail;}function target(){return f(' . $count . ');}'];
        }
        $cases['boundary:arrayRebind'] = ['<?php function target(){$x=1;$y=2;$a=[&$x];$a[0]=&$y;$a[0]=3;return [$x,$y,$a];}'];
        $cases['boundary:unsetAppend'] = ['<?php function target(){$a=[5=>1];unset($a[5]);$a[]=2;return $a;}'];
        $cases['boundary:unsetNegative'] = ['<?php function target(){$a=[-5=>1];unset($a[-5]);$a[]=2;return $a;}'];
        $cases['boundary:returnReference'] = ['<?php function &get(&$x){return $x;}function target(){$x=1;$y=&get($x);$y=2;return $x;}'];
        $cases['boundary:floatPromotion'] = ['<?php function f(float $x):float{return $x;}function target(){return f(2);}'];
        $cases['boundary:returnCoercion'] = ['<?php function f():int{return "42";}function target(){return f();}'];
        $cases['boundary:returnFailure'] = ['<?php declare(strict_types=1);function f():int{return "42";}function target(){try{return f();}catch(TypeError $e){return "caught";}}'];
        $cases['boundary:referenceCoercion'] = ['<?php function f(int &$x){}function target(){$x="42";f($x);return $x;}'];
        $cases['boundary:arrayCallable'] = ['<?php class C{function f($x){return $x+1;}}function target(){$c=new C;$f=[$c,"f"];return $f(2);}'];
        $cases['boundary:invoke'] = ['<?php class C{function __invoke($x){return $x+1;}}function target(){$c=new C;return $c(2);}'];
        $cases['boundary:sortNumeric'] = ['<?php function target(){$a=["b"=>3,"a"=>1,2];$r=sort($a);return [$r,$a];}'];
        $cases['boundary:replaceCount'] = ['<?php function target(){$count=99;$r=str_replace("a","b","aaba",$count);return [$r,$count];}'];
        $cases['boundary:format'] = ['<?php function target(){return sprintf("user:%s:%d:%%","a",7);}'];
        $cases['boundary:implodeOverload'] = ['<?php function target(){return implode(["a","b"]);}'];
        $cases['boundary:keysFilter'] = ['<?php function target(){return array_keys(["a"=>1,"b"=>"1","c"=>2],1,true);}'];
        $cases['boundary:recursiveCount'] = ['<?php function target(){return count([1,[2,3]],COUNT_RECURSIVE);}'];
        $cases['boundary:filterKey'] = ['<?php function target(){return array_filter(["a"=>1,"b"=>2],fn($k)=>$k==="b",ARRAY_FILTER_USE_KEY);}'];
        $cases['property:typedWeak'] = ['<?php class B{public int $x=1;}function target(){$b=new B;$r=($b->x="42");return [$r,$b->x];}'];
        $cases['property:typedStrict'] = ['<?php declare(strict_types=1);class B{public int $x=1;}function target(){$b=new B;try{$b->x="42";}catch(TypeError $e){return $b->x;}return 999;}'];
        $cases['property:privateOutside'] = ['<?php class B{private $x=1;}function target(){try{return (new B)->x;}catch(Error $e){return "denied";}}'];
        $cases['property:privateSlots'] = ['<?php class A{private $x=1;function a(){return $this->x;}}class B extends A{private $x=2;function b(){return $this->x;}}function target(){$b=new B;return [$b->a(),$b->b()];}'];
        $cases['property:protectedParent'] = ['<?php class A{protected $x=1;}class B extends A{function get(){return $this->x;}}function target(){return (new B)->get();}'];
        $cases['property:staticDefault'] = ['<?php class A{public static $x=3;static function f(){return self::$x;}}function target(){return A::f();}'];
        $cases['property:staticInherited'] = ['<?php class A{public static $x=3;}class B extends A{}function target(){B::$x=4;return [A::$x,B::$x];}'];
        $cases['property:staticLate'] = ['<?php class A{public static $x=3;static function f(){return static::$x;}}class B extends A{public static $x=4;}function target(){return [A::f(),B::f()];}'];
        $cases['property:magicGetSet'] = ['<?php class B{private $v=0;function __get($n){return $this->v;}function __set($n,$v){$this->v=$v*2;}}function target(){$b=new B;$r=($b->x=3);return [$r,$b->x];}'];
        $cases['property:magicIsset'] = ['<?php class B{function __isset($n){return $n==="x";}function __get($n){return 3;}}function target(){$b=new B;return [isset($b->x),isset($b->y),$b->x??9,$b->y??9];}'];
        $cases['property:magicCoalesceWithoutIsset'] = ['<?php class B{function __get($n){return 3;}}function target(){$b=new B;return [isset($b->x),$b->x??9];}'];
        $cases['property:readonlyInitialize'] = ['<?php class B{public readonly int $x;function set(){ $this->x=1;}}function target(){$b=new B;$b->set();try{$b->set();}catch(Error $e){return $b->x;}return 999;}'];
        $cases['property:readonlyClone'] = ['<?php class B{function __construct(public readonly int $x){}function __clone(){$this->x=2;}}function target(){$a=new B(1);$b=clone $a;return [$a->x,$b->x];}'];
        $cases['property:readonlyCloneTwice'] = ['<?php class B{function __construct(public readonly int $x){}function __clone(){$this->x=2;$this->x=3;}}function target(){$a=new B(1);try{$b=clone $a;}catch(Error $e){return $a->x;}return 999;}'];
        $cases['property:typedUnset'] = ['<?php class B{public int $x=1;}function target(){$b=new B;unset($b->x);try{return $b->x;}catch(Error $e){return "uninitialized";}}'];
        $cases['property:strictClosure'] = ['<?php declare(strict_types=1);function f(int $x){return $x;}function target(){$g=fn()=>f("1");try{return $g();}catch(TypeError $e){return "strict";}}'];
        $cases['property:callableString'] = ['<?php function f(){}function target(){return is_callable("f");}'];
        $cases['property:enumObject'] = ['<?php enum E{case A;}function target(){return is_object(E::A);}'];
        $cases['property:arrayIdentityMetadata'] = ['<?php function target(){$a=[1];$a[]=2;return $a===[1,2];}'];
        $cases['property:arrayXor'] = ['<?php function target(){return [1] xor [];}'];
        $cases['property:traitProperty'] = ['<?php trait T{public $x=4;}class B{use T;}function target(){return (new B)->x;}'];
        $cases['mutation:privateIsset'] = ['<?php class B{private $x=1;}function target(){$b=new B;return [isset($b->x),$b->x??9,empty($b->x)];}'];
        $cases['mutation:nullIsset'] = ['<?php function target(){$b=null;return [isset($b->x),$b->x??9,empty($b->x)];}'];
        $cases['mutation:scalarWrite'] = ['<?php function target(){$b=1;try{$b->x=2;}catch(Error $e){return $b;}return 999;}'];
        $cases['mutation:nullWrite'] = ['<?php function target(){$b=null;try{$b->x=2;}catch(Error $e){return $b;}return 999;}'];
        $cases['mutation:scalarClone'] = ['<?php function target(){$b=1;try{$c=clone $b;}catch(Error $e){return $b;}return 999;}'];
        $cases['mutation:readonlyClass'] = ['<?php readonly class B{function __construct(public int $x){}}function target(){$b=new B(1);try{$b->x=2;}catch(Error $e){return $b->x;}return 999;}'];
        $cases['mutation:readonlyArray'] = ['<?php class B{function __construct(public readonly array $x){}}function target(){$b=new B([1]);try{$b->x[]=2;}catch(Error $e){return $b->x;}return 999;}'];
        $cases['mutation:readonlyNested'] = ['<?php class B{function __construct(public readonly array $x){}}function target(){$b=new B([[1]]);try{$b->x[0][0]=2;}catch(Error $e){return $b->x;}return 999;}'];
        $cases['mutation:readonlyUnsetElement'] = ['<?php class B{function __construct(public readonly array $x){}}function target(){$b=new B([1]);try{unset($b->x[0]);}catch(Error $e){return $b->x;}return 999;}'];
        $cases['mutation:readonlyReference'] = ['<?php class B{function __construct(public readonly int $x){}}function target(){$b=new B(1);try{$ref=&$b->x;}catch(Error $e){return $b->x;}return 999;}'];
        $cases['mutation:readonlyUnsetUninitialized'] = ['<?php class B{public readonly int $x;function initialize(){unset($this->x);$this->x=3;}}function target(){$b=new B;$b->initialize();return $b->x;}'];
        $cases['mutation:typedIncrementOverflow'] = ['<?php class B{public int $x=9223372036854775807;}function target(){$b=new B;try{$b->x++;}catch(TypeError $e){return $b->x;}return 999;}'];
        $cases['mutation:typedReference'] = ['<?php class B{public int $x=1;}function target(){$b=new B;$ref=&$b->x;try{$ref=[];}catch(TypeError $e){return $b->x;}return 999;}'];
        $cases['mutation:typedReferenceCoercion'] = ['<?php class B{public int $x=1;}function target(){$b=new B;$ref=&$b->x;$ref="42";return [$b->x,$ref];}'];
        $cases['mutation:enumParameter'] = ['<?php enum E{case A;}function f(E $x){return $x===E::A;}function target(){return f(E::A);}'];
        $cases['mutation:callableParameter'] = ['<?php function f(callable $g){return $g();}function g(){return 3;}function target(){return f("g");}'];
        $cases['mutation:staticInitializer'] = ['<?php function nextValue(){static $n=0;return ++$n;}function f(){static $n=nextValue();return $n;}function target(){return [f(),f(),nextValue()];}'];
        $cases['mutation:readonlyByReference'] = ['<?php class B{function __construct(public readonly int $x){}}function change(&$x){$x=2;}function target(){$b=new B(1);try{change($b->x);}catch(Error $e){return $b->x;}return 999;}'];
        $cases['mutation:propertyElementWrite'] = ['<?php class B{public array $x=[1];}function target(){$b=new B;$b->x[0]=2;return $b->x;}'];
        $cases['mutation:referenceCalleeWrite'] = ['<?php class B{public int $x=1;}function change(&$x){$x=[];}function target(){$b=new B;try{change($b->x);}catch(TypeError $e){return $b->x;}return 999;}'];
        $cases['mutation:referenceCalleeCoercion'] = ['<?php class B{public int $x=1;}function change(&$x){$x="42";}function target(){$b=new B;change($b->x);return $b->x;}'];
        $cases['mutation:referenceDestinationType'] = ['<?php class B{public int $x=1;}function target(){$b=new B;$x="42";$b->x=&$x;return [$b->x,$x];}'];
        $cases['mutation:referenceDestinationInvalid'] = ['<?php class B{public int $x=1;}function target(){$b=new B;$x=[];try{$b->x=&$x;}catch(TypeError $e){return [$b->x,$x];}return 999;}'];
        $cases['mutation:referenceConflictingCoercions'] = ['<?php class B{public int $x=1;public float $y=2.0;}function target(){$b=new B;try{$b->x=&$b->y;}catch(TypeError $e){return [$b->x,$b->y];}return 999;}'];
        $cases['mutation:referenceUnsetRemovesConstraint'] = ['<?php class B{public int $x=1;}function target(){$b=new B;$x=&$b->x;unset($b->x);$x="unbound";return $x;}'];
        $cases['mutation:referenceCloneKeepsConstraint'] = ['<?php class B{public int $x=1;}function target(){$b=new B;$x=&$b->x;$c=clone $b;unset($b->x);try{$x=[];}catch(TypeError $e){return $c->x;}return 999;}'];
        $cases['member:abstractAllocation'] = ['<?php abstract class A{}function target(){try{new A;return "bad";}catch(Error $e){return "error";}}'];
        $cases['member:interfaceAllocation'] = ['<?php interface A{}function target(){try{new A;return "bad";}catch(Error $e){return "error";}}'];
        $cases['member:enumAllocation'] = ['<?php enum A{case One;}function target(){try{new A;return "bad";}catch(Error $e){return "error";}}'];
        $cases['member:traitAllocation'] = ['<?php trait A{}function target(){try{new A;return "bad";}catch(Error $e){return "error";}}'];
        $cases['member:privateConstructor'] = ['<?php class A{private function __construct(){}}function target(){try{new A;return "bad";}catch(Error $e){return "error";}}'];
        $cases['member:privateFactory'] = ['<?php class A{public int $x=3;private function __construct(){}public static function make(){return new self;}}function target(){return A::make()->x;}'];
        $cases['member:privateParentConstructor'] = ['<?php class A{private function __construct(){}}class B extends A{}function target(){try{new B;return "ok";}catch(Error $e){return "error";}}'];
        $cases['member:privateParentFactory'] = ['<?php class A{public int $x=3;private function __construct(){}public static function make(){return new B;}}class B extends A{}function target(){try{return A::make()->x;}catch(Error $e){return "error";}}'];
        $cases['member:protectedFactory'] = ['<?php class A{public int $x=3;protected function __construct(){}}class B extends A{public static function make(){return new self;}}function target(){return B::make()->x;}'];
        $cases['member:privateMethod'] = ['<?php class A{private function value(){return "bad";}}function target(){try{return (new A)->value();}catch(Error $e){return "error";}}'];
        $cases['member:protectedMethod'] = ['<?php class A{protected function value(){return "bad";}}function target(){try{return (new A)->value();}catch(Error $e){return "error";}}'];
        $cases['member:privateLexicalMethod'] = ['<?php class A{private function value(){return "parent";}public function run(){return $this->value();}}class B extends A{public function value(){return "child";}}function target(){return (new B)->run();}'];
        $cases['member:protectedSibling'] = ['<?php class A{protected function value(){return 7;}}class B extends A{public function run(A $other){return $other->value();}}class C extends A{}function target(){return (new B)->run(new C);}'];
        $cases['member:nonStaticCall'] = ['<?php class A{public function value(){return "bad";}}function target(){try{return A::value();}catch(Error $e){return "error";}}'];
        $cases['member:staticMethodThis'] = ['<?php class A{public static function value(){return isset($this);}}function target(){return (new A)->value();}'];
        $cases['member:staticMethodReadThis'] = ['<?php class A{public static function value(){return $this;}}function target(){try{(new A)->value();return "bad";}catch(Error $e){return "error";}}'];
        $cases['member:selfInstanceCall'] = ['<?php class A{public function value(){return 3;}public function run(){return self::value();}}function target(){return (new A)->run();}'];
        $cases['member:unrelatedInstanceCall'] = ['<?php class A{public function value(){return 3;}}class B{public function run(){return A::value();}}function target(){try{return (new B)->run();}catch(Error $e){return "error";}}'];
        $cases['member:parentCallingInheritedChild'] = ['<?php class A{public function value(){return 3;}public function run(){return B::value();}}class B extends A{}function target(){try{return (new A)->run();}catch(Error $e){return "error";}}'];
        $cases['member:magicMissingMethod'] = ['<?php class A{public function __call($name,$args){return [$name,$args];}}function target(){return (new A)->missing(2, label:3);}'];
        $cases['member:magicPrivateMethod'] = ['<?php class A{private function missing(){return "bad";}public function __call($name,$args){return [$name,$args];}}function target(){return (new A)->missing(2);}'];
        $cases['member:magicMissingStatic'] = ['<?php class A{public static function __callStatic($name,$args){return [$name,$args];}}function target(){return A::missing(label:3);}'];
        $cases['member:missingKnownMethod'] = ['<?php class A{}function target(){try{return (new A)->missing();}catch(Error $e){return "error";}}'];
        $cases['member:missingKnownStatic'] = ['<?php class A{}function target(){try{return A::missing();}catch(Error $e){return "error";}}'];
        $cases['member:scalarMethod'] = ['<?php function target(){try{$x=1;return $x->missing();}catch(Error $e){return "error";}}'];
        $cases['member:privateClone'] = ['<?php class A{private function __clone(){}}function target(){try{$a=new A;clone $a;return "bad";}catch(Error $e){return "error";}}'];
        $cases['member:noConstructorNamed'] = ['<?php class A{}function target(){try{new A(label:3);return "ok";}catch(Error $e){return "error";}}'];
        $cases['member:noConstructorPositional'] = ['<?php class A{}function target(){try{new A(3);return "ok";}catch(Error $e){return "error";}}'];
        $cases['member:abstractStaticMethod'] = ['<?php abstract class A{abstract public static function value();}function target(){try{return A::value();}catch(Error $e){return "error";}}'];
        $cases['member:newFromObject'] = ['<?php class A{public int $x=3;}function target(){$a=new A;$b=new $a;return $b->x;}'];
        $cases['member:newFromScalar'] = ['<?php function target(){try{$class=3;new $class;return "bad";}catch(Error $e){return "error";}}'];
        $cases['native:messageCode'] = ['<?php function target(){$e=new Exception("hello",7);return [$e->getMessage(),$e->getCode(),$e->getPrevious()];}'];
        $cases['native:named'] = ['<?php function target(){$e=new RuntimeException(code:7,message:"hello");return [$e->getMessage(),$e->getCode()];}'];
        $cases['native:inherited'] = ['<?php class X extends Exception{}function target(){$e=new X(code:7,message:"hello");return [$e->getMessage(),$e->getCode()];}'];
        $cases['native:codeZero'] = ['<?php class X extends Exception{protected $message="x";protected $code=9;}function target(){$a=new X;$b=new X(code:0);$c=new X(message:"a");return [$a->getMessage(),$a->getCode(),$b->getMessage(),$b->getCode(),$c->getMessage(),$c->getCode()];}'];
        $cases['native:previous'] = ['<?php function target(){$p=new Exception("first");$e=new Exception(previous:$p);$previous=$e->getPrevious();$equal=$previous===$p;return [$equal,$previous->getMessage()];}'];
        $cases['native:parent'] = ['<?php class X extends Exception{function __construct(){parent::__construct("a",2);}}function target(){$e=new X;return [$e->getMessage(),$e->getCode()];}'];
        $cases['native:clone'] = ['<?php function target(){try{$x=clone new Exception;}catch(Error $e){return "error";}return 999;}'];
        $cases['native:arrayMessage'] = ['<?php function target(){try{new Exception([]);}catch(TypeError $e){return "type";}return 999;}'];
        $cases['native:badCode'] = ['<?php function target(){try{new Exception(code:"no");}catch(TypeError $e){return "type";}return 999;}'];
        $cases['native:badPrevious'] = ['<?php function target(){try{new Exception(previous:new stdClass);}catch(TypeError $e){return "type";}return 999;}'];
        $cases['native:unknownNamed'] = ['<?php function target(){try{new Exception(other:1);}catch(Error $e){return "name";}return 999;}'];
        $cases['native:extra'] = ['<?php function target(){try{new Exception("",0,null,1);}catch(ArgumentCountError $e){return "count";}return 999;}'];
        $cases['native:strict'] = ['<?php declare(strict_types=1);function target(){try{new Exception(1);}catch(TypeError $e){return "type";}return 999;}'];
        $cases['native:weak'] = ['<?php function target(){$e=new Exception(1,"2");return [$e->getMessage(),$e->getCode()];}'];
        $cases['native:errorDefaults'] = ['<?php class X extends ErrorException{protected $message="x";protected $code=9;protected int $severity=2;}function target(){$x=new X;return [$x->getMessage(),$x->getCode(),$x->getSeverity()];}'];
        $cases['native:repeatPrevious'] = ['<?php class X extends Exception{function clear(){parent::__construct();}function reset(){parent::__construct(previous:null);}}function target(){$p=new Exception;$x=new X("x",9,$p);$x->clear();$previous=$x->getPrevious();$a=[$x->getMessage(),$x->getCode(),$previous===$p];$x->reset();$previous=$x->getPrevious();return [$a,$x->getMessage(),$x->getCode(),$previous===$p];}'];
        $cases['native:severity'] = ['<?php function target(){$x=new ErrorException(severity:8);return [$x->getMessage(),$x->getCode(),$x->getSeverity()];}'];
        $cases['native:getterArity'] = ['<?php function target(){try{(new Exception)->getMessage(1);}catch(ArgumentCountError $e){return "count";}return 999;}'];
        $cases['native:protected'] = ['<?php function target(){try{return (new Exception("a"))->message;}catch(Error $e){return "access";}}'];
        $cases['native:sourceProperty'] = ['<?php class X extends Exception{function change(){$this->message=42;$this->code="custom";}}function target(){$e=new X;$e->change();return [$e->getMessage(),$e->getCode()];}'];
        $cases['native:caseInsensitive'] = ['<?php function target(){try{throw new runtimeexception;}catch(EXCEPTION $e){return "caught";}}'];
        $cases['native:errorHierarchy'] = ['<?php function target(){$e=new DivisionByZeroError("zero");return [$e instanceof ArithmeticError,$e instanceof Throwable,$e->getMessage()];}'];
        $cases['native:missingMethod'] = ['<?php function target(){try{(new Exception)->absent();}catch(Error $e){return "missing";}return 999;}'];
        $cases['native:filename'] = ['<?php function target(){$e=new ErrorException(filename:"chosen.php");return [$e->getFile(),$e->getLine()];}'];
        $cases['native:line'] = ['<?php function target(){$e=new ErrorException(filename:"chosen.php",line:42);return [$e->getFile(),$e->getLine()];}'];
        $cases['trait:privateProperty'] = ['<?php trait T{private $x=4;function f(){return $this->x;}}class B{use T;}function target(){return (new B)->f();}'];
        $cases['trait:privateMethod'] = ['<?php trait T{private function f(){return 4;}function g(){return $this->f();}}class B{use T;}function target(){return (new B)->g();}'];
        $cases['trait:self'] = ['<?php trait T{static function f(){return self::class;}}class B{use T;}function target(){return B::f();}'];
        $cases['trait:parent'] = ['<?php trait T{function f(){return parent::f()+1;}}class A{function f(){return 2;}}class B extends A{use T;}function target(){return (new B)->f();}'];
        $cases['trait:precedence'] = ['<?php trait T{function f(){return 2;}}class A{function f(){return 1;}}class B extends A{use T;}class C{use T;function f(){return 3;}}function target(){return [(new B)->f(),(new C)->f()];}'];
        $cases['trait:alias'] = ['<?php trait T{function f(){return 2;}}class B{use T{f as g;}}function target(){return [(new B)->f(),(new B)->g()];}'];
        $cases['trait:visibility'] = ['<?php trait T{function f(){return 2;}}class B{use T{f as private;}function g(){return $this->f();}}function target(){try{(new B)->f();}catch(Error $e){return (new B)->g();}return 999;}'];
        $cases['trait:aliasVisibility'] = ['<?php trait T{private function f(){return 2;}}class B{use T{f as public g;}}function target(){return (new B)->g();}'];
        $cases['trait:conflict'] = ['<?php trait T{function f(){return 1;}}trait U{function f(){return 2;}}class B{use T,U{T::f insteadof U;U::f as g;}}function target(){return [(new B)->f(),(new B)->g()];}'];
        $cases['trait:nested'] = ['<?php trait U{private $x=4;function f(){return $this->x;}}trait T{use U;}class B{use T;}function target(){return (new B)->f();}'];
        $cases['trait:closure'] = ['<?php trait T{function f(){return fn()=>self::class;}}class A{use T;}class B{use T;}function target(){$a=(new A)->f();$b=(new B)->f();return [$a(),$b()];}'];
        $cases['trait:staticProperties'] = ['<?php trait T{public static $x=0;static function inc(){return ++self::$x;}}class A{use T;}class B extends A{use T;}function target(){return [A::inc(),B::inc(),A::inc(),B::inc()];}'];
        $cases['trait:staticLocal'] = ['<?php trait T{function f(){static $x=0;return ++$x;}}class A{use T;}class B{use T;}function target(){return [(new A)->f(),(new B)->f(),(new A)->f()];}'];
        $cases['trait:aliasStatic'] = ['<?php trait T{function f(){static $x=0;return ++$x;}}class A{use T{f as g;}}function target(){$a=new A;return [$a->f(),$a->g(),$a->f(),$a->g()];}'];
        $cases['trait:magic'] = ['<?php trait T{function f(){return [__CLASS__,__TRAIT__,__METHOD__,__FUNCTION__];}}class C{use T{f as g;}}function target(){return [(new C)->f(),(new C)->g()];}'];
        $cases['trait:privateChild'] = ['<?php trait T{private $x=1;function a(){return $this->x;}}class A{use T;}class B extends A{private $x=2;function b(){return $this->x;}}function target(){$b=new B;return [$b->a(),$b->b()];}'];
        $cases['trait:propertyDefault'] = ['<?php trait T{public $x=self::C;}class B{use T;const C=4;}function target(){return (new B)->x;}'];
        $cases['trait:constant'] = ['<?php trait T{const C=3;function f(){return self::C;}}class B{use T;}function target(){return [(new B)->f(),B::C];}'];
        $cases['trait:abstractParent'] = ['<?php trait T{abstract public function f();function g(){return $this->f();}}class A{function f(){return 4;}}class B extends A{use T;}function target(){return (new B)->g();}'];
        $cases['reference:nullableReference'] = ['<?php class B{public ?int $x;}function target(){$b=new B;$r=&$b->x;return [$b->x,$r];}'];
        $cases['reference:nonNullableReference'] = ['<?php class B{public int $x;}function target(){$b=new B;try{$r=&$b->x;}catch(Error $e){return "uninitialized";}return 999;}'];
        $cases['reference:propertyConstraint'] = ['<?php class B{public int $x=1;public int|string $y=1;}function target(){$b=new B;$b->y=&$b->x;try{$b->y="word";}catch(TypeError $e){return [$b->x,$b->y];}return 999;}'];
        $cases['reference:variadicConstraint'] = ['<?php class B{public int $x=1;}function change(float &...$values){$values[0]=2.5;}function target(){$b=new B;try{change($b->x);}catch(TypeError $e){return $b->x;}return 999;}'];
        return $cases;
    }
}
