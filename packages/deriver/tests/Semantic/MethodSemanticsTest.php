<?php

declare(strict_types=1);

namespace Tests\Semantic;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

/**
 * Checks method access, magic dispatch, and construction against PHP 8.3 results.
 */
#[CoversNothing]
#[Small]
final class MethodSemanticsTest extends TestCase
{
    /**
     * @param string $source Independently executed PHP 8.3 fixture
     * @param string $expectedJson Captured PHP runtime result
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProvider('providerCases')]
    public function testMemberAccessMatchesTheTargetRuntime(string $source, string $expectedJson): void
    {
        $result = Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @return array<string, array{string, string}> Native method and allocation outcomes
     */
    public static function providerCases(): array
    {
        return [
            'reference:nullableReference' => ['<?php class B{public ?int $x;}function target(){$b=new B;$r=&$b->x;return [$b->x,$r];}', '[null,null]'],
            'reference:nonNullableReference' => ['<?php class B{public int $x;}function target(){$b=new B;try{$r=&$b->x;}catch(Error $e){return "uninitialized";}return 999;}', '"uninitialized"'],
            'reference:propertyConstraint' => ['<?php class B{public int $x=1;public int|string $y=1;}function target(){$b=new B;$b->y=&$b->x;try{$b->y="word";}catch(TypeError $e){return [$b->x,$b->y];}return 999;}', '[1,1]'],
            'reference:variadicConstraint' => ['<?php class B{public int $x=1;}function change(float &...$values){$values[0]=2.5;}function target(){$b=new B;try{change($b->x);}catch(TypeError $e){return $b->x;}return 999;}', '1'],

            'trait:privateProperty' => ['<?php trait T{private $x=4;function f(){return $this->x;}}class B{use T;}function target(){return (new B)->f();}', '4'],
            'trait:privateMethod' => ['<?php trait T{private function f(){return 4;}function g(){return $this->f();}}class B{use T;}function target(){return (new B)->g();}', '4'],
            'trait:self' => ['<?php trait T{static function f(){return self::class;}}class B{use T;}function target(){return B::f();}', '"B"'],
            'trait:parent' => ['<?php trait T{function f(){return parent::f()+1;}}class A{function f(){return 2;}}class B extends A{use T;}function target(){return (new B)->f();}', '3'],
            'trait:precedence' => ['<?php trait T{function f(){return 2;}}class A{function f(){return 1;}}class B extends A{use T;}class C{use T;function f(){return 3;}}function target(){return [(new B)->f(),(new C)->f()];}', '[2,3]'],
            'trait:alias' => ['<?php trait T{function f(){return 2;}}class B{use T{f as g;}}function target(){return [(new B)->f(),(new B)->g()];}', '[2,2]'],
            'trait:visibility' => ['<?php trait T{function f(){return 2;}}class B{use T{f as private;}function g(){return $this->f();}}function target(){try{(new B)->f();}catch(Error $e){return (new B)->g();}return 999;}', '2'],
            'trait:aliasVisibility' => ['<?php trait T{private function f(){return 2;}}class B{use T{f as public g;}}function target(){return (new B)->g();}', '2'],
            'trait:conflict' => ['<?php trait T{function f(){return 1;}}trait U{function f(){return 2;}}class B{use T,U{T::f insteadof U;U::f as g;}}function target(){return [(new B)->f(),(new B)->g()];}', '[1,2]'],
            'trait:nested' => ['<?php trait U{private $x=4;function f(){return $this->x;}}trait T{use U;}class B{use T;}function target(){return (new B)->f();}', '4'],
            'trait:closure' => ['<?php trait T{function f(){return fn()=>self::class;}}class A{use T;}class B{use T;}function target(){$a=(new A)->f();$b=(new B)->f();return [$a(),$b()];}', '["A","B"]'],
            'trait:staticProperties' => ['<?php trait T{public static $x=0;static function inc(){return ++self::$x;}}class A{use T;}class B extends A{use T;}function target(){return [A::inc(),B::inc(),A::inc(),B::inc()];}', '[1,1,2,2]'],
            'trait:staticLocal' => ['<?php trait T{function f(){static $x=0;return ++$x;}}class A{use T;}class B{use T;}function target(){return [(new A)->f(),(new B)->f(),(new A)->f()];}', '[1,1,2]'],
            'trait:aliasStatic' => ['<?php trait T{function f(){static $x=0;return ++$x;}}class A{use T{f as g;}}function target(){$a=new A;return [$a->f(),$a->g(),$a->f(),$a->g()];}', '[1,1,2,2]'],
            'trait:magic' => ['<?php trait T{function f(){return [__CLASS__,__TRAIT__,__METHOD__,__FUNCTION__];}}class C{use T{f as g;}}function target(){return [(new C)->f(),(new C)->g()];}', '[["C","T","T::f","f"],["C","T","T::f","f"]]'],
            'trait:privateChild' => ['<?php trait T{private $x=1;function a(){return $this->x;}}class A{use T;}class B extends A{private $x=2;function b(){return $this->x;}}function target(){$b=new B;return [$b->a(),$b->b()];}', '[1,2]'],
            'trait:propertyDefault' => ['<?php trait T{public $x=self::C;}class B{use T;const C=4;}function target(){return (new B)->x;}', '4'],
            'trait:constant' => ['<?php trait T{const C=3;function f(){return self::C;}}class B{use T;}function target(){return [(new B)->f(),B::C];}', '[3,3]'],
            'trait:abstractParent' => ['<?php trait T{abstract public function f();function g(){return $this->f();}}class A{function f(){return 4;}}class B extends A{use T;}function target(){return (new B)->g();}', '4'],

            'native:messageCode' => ['<?php function target(){$e=new Exception("hello",7);return [$e->getMessage(),$e->getCode(),$e->getPrevious()];}', '["hello",7,null]'],
            'native:named' => ['<?php function target(){$e=new RuntimeException(code:7,message:"hello");return [$e->getMessage(),$e->getCode()];}', '["hello",7]'],
            'native:inherited' => ['<?php class X extends Exception{}function target(){$e=new X(code:7,message:"hello");return [$e->getMessage(),$e->getCode()];}', '["hello",7]'],
            'native:codeZero' => ['<?php class X extends Exception{protected $message="x";protected $code=9;}function target(){$a=new X;$b=new X(code:0);$c=new X(message:"a");return [$a->getMessage(),$a->getCode(),$b->getMessage(),$b->getCode(),$c->getMessage(),$c->getCode()];}', '["x",9,"",9,"a",9]'],
            'native:previous' => ['<?php function target(){$p=new Exception("first");$e=new Exception(previous:$p);$previous=$e->getPrevious();$equal=$previous===$p;return [$equal,$previous->getMessage()];}', '[true,"first"]'],
            'native:parent' => ['<?php class X extends Exception{function __construct(){parent::__construct("a",2);}}function target(){$e=new X;return [$e->getMessage(),$e->getCode()];}', '["a",2]'],
            'native:clone' => ['<?php function target(){try{$x=clone new Exception;}catch(Error $e){return "error";}return 999;}', '"error"'],
            'native:arrayMessage' => ['<?php function target(){try{new Exception([]);}catch(TypeError $e){return "type";}return 999;}', '"type"'],
            'native:badCode' => ['<?php function target(){try{new Exception(code:"no");}catch(TypeError $e){return "type";}return 999;}', '"type"'],
            'native:badPrevious' => ['<?php function target(){try{new Exception(previous:new stdClass);}catch(TypeError $e){return "type";}return 999;}', '"type"'],
            'native:unknownNamed' => ['<?php function target(){try{new Exception(other:1);}catch(Error $e){return "name";}return 999;}', '"name"'],
            'native:extra' => ['<?php function target(){try{new Exception("",0,null,1);}catch(ArgumentCountError $e){return "count";}return 999;}', '"count"'],
            'native:strict' => ['<?php declare(strict_types=1);function target(){try{new Exception(1);}catch(TypeError $e){return "type";}return 999;}', '"type"'],
            'native:weak' => ['<?php function target(){$e=new Exception(1,"2");return [$e->getMessage(),$e->getCode()];}', '["1",2]'],
            'native:errorDefaults' => ['<?php class X extends ErrorException{protected $message="x";protected $code=9;protected int $severity=2;}function target(){$x=new X;return [$x->getMessage(),$x->getCode(),$x->getSeverity()];}', '["x",9,1]'],
            'native:repeatPrevious' => ['<?php class X extends Exception{function clear(){parent::__construct();}function reset(){parent::__construct(previous:null);}}function target(){$p=new Exception;$x=new X("x",9,$p);$x->clear();$previous=$x->getPrevious();$a=[$x->getMessage(),$x->getCode(),$previous===$p];$x->reset();$previous=$x->getPrevious();return [$a,$x->getMessage(),$x->getCode(),$previous===$p];}', '[["x",9,true],"",9,true]'],
            'native:severity' => ['<?php function target(){$x=new ErrorException(severity:8);return [$x->getMessage(),$x->getCode(),$x->getSeverity()];}', '["",0,8]'],
            'native:getterArity' => ['<?php function target(){try{(new Exception)->getMessage(1);}catch(ArgumentCountError $e){return "count";}return 999;}', '"count"'],
            'native:protected' => ['<?php function target(){try{return (new Exception("a"))->message;}catch(Error $e){return "access";}}', '"access"'],
            'native:sourceProperty' => ['<?php class X extends Exception{function change(){$this->message=42;$this->code="custom";}}function target(){$e=new X;$e->change();return [$e->getMessage(),$e->getCode()];}', '["42","custom"]'],
            'native:caseInsensitive' => ['<?php function target(){try{throw new runtimeexception;}catch(EXCEPTION $e){return "caught";}}', '"caught"'],
            'native:errorHierarchy' => ['<?php function target(){$e=new DivisionByZeroError("zero");return [$e instanceof ArithmeticError,$e instanceof Throwable,$e->getMessage()];}', '[true,true,"zero"]'],
            'native:missingMethod' => ['<?php function target(){try{(new Exception)->absent();}catch(Error $e){return "missing";}return 999;}', '"missing"'],
            'native:filename' => ['<?php function target(){$e=new ErrorException(filename:"chosen.php");return [$e->getFile(),$e->getLine()];}', '["chosen.php",0]'],
            'native:line' => ['<?php function target(){$e=new ErrorException(filename:"chosen.php",line:42);return [$e->getFile(),$e->getLine()];}', '["chosen.php",42]'],

            'abstractAllocation' => ['<?php abstract class A{}function target(){try{new A;return "bad";}catch(Error $e){return "error";}}', '"error"'],
            'interfaceAllocation' => ['<?php interface A{}function target(){try{new A;return "bad";}catch(Error $e){return "error";}}', '"error"'],
            'enumAllocation' => ['<?php enum A{case One;}function target(){try{new A;return "bad";}catch(Error $e){return "error";}}', '"error"'],
            'traitAllocation' => ['<?php trait A{}function target(){try{new A;return "bad";}catch(Error $e){return "error";}}', '"error"'],
            'privateConstructor' => ['<?php class A{private function __construct(){}}function target(){try{new A;return "bad";}catch(Error $e){return "error";}}', '"error"'],
            'privateFactory' => ['<?php class A{public int $x=3;private function __construct(){}public static function make(){return new self;}}function target(){return A::make()->x;}', '3'],
            'privateParentConstructor' => ['<?php class A{private function __construct(){}}class B extends A{}function target(){try{new B;return "ok";}catch(Error $e){return "error";}}', '"error"'],
            'privateParentFactory' => ['<?php class A{public int $x=3;private function __construct(){}public static function make(){return new B;}}class B extends A{}function target(){try{return A::make()->x;}catch(Error $e){return "error";}}', '3'],
            'protectedFactory' => ['<?php class A{public int $x=3;protected function __construct(){}}class B extends A{public static function make(){return new self;}}function target(){return B::make()->x;}', '3'],
            'privateMethod' => ['<?php class A{private function value(){return "bad";}}function target(){try{return (new A)->value();}catch(Error $e){return "error";}}', '"error"'],
            'protectedMethod' => ['<?php class A{protected function value(){return "bad";}}function target(){try{return (new A)->value();}catch(Error $e){return "error";}}', '"error"'],
            'privateLexicalMethod' => ['<?php class A{private function value(){return "parent";}public function run(){return $this->value();}}class B extends A{public function value(){return "child";}}function target(){return (new B)->run();}', '"parent"'],
            'protectedSibling' => ['<?php class A{protected function value(){return 7;}}class B extends A{public function run(A $other){return $other->value();}}class C extends A{}function target(){return (new B)->run(new C);}', '7'],
            'nonStaticCall' => ['<?php class A{public function value(){return "bad";}}function target(){try{return A::value();}catch(Error $e){return "error";}}', '"error"'],
            'staticMethodThis' => ['<?php class A{public static function value(){return isset($this);}}function target(){return (new A)->value();}', 'false'],
            'staticMethodReadThis' => ['<?php class A{public static function value(){return $this;}}function target(){try{(new A)->value();return "bad";}catch(Error $e){return "error";}}', '"error"'],
            'selfInstanceCall' => ['<?php class A{public function value(){return 3;}public function run(){return self::value();}}function target(){return (new A)->run();}', '3'],
            'unrelatedInstanceCall' => ['<?php class A{public function value(){return 3;}}class B{public function run(){return A::value();}}function target(){try{return (new B)->run();}catch(Error $e){return "error";}}', '"error"'],
            'parentCallingInheritedChild' => ['<?php class A{public function value(){return 3;}public function run(){return B::value();}}class B extends A{}function target(){try{return (new A)->run();}catch(Error $e){return "error";}}', '"error"'],
            'magicMissingMethod' => ['<?php class A{public function __call($name,$args){return [$name,$args];}}function target(){return (new A)->missing(2, label:3);}', '["missing",{"0":2,"label":3}]'],
            'magicPrivateMethod' => ['<?php class A{private function missing(){return "bad";}public function __call($name,$args){return [$name,$args];}}function target(){return (new A)->missing(2);}', '["missing",[2]]'],
            'magicMissingStatic' => ['<?php class A{public static function __callStatic($name,$args){return [$name,$args];}}function target(){return A::missing(label:3);}', '["missing",{"label":3}]'],
            'missingKnownMethod' => ['<?php class A{}function target(){try{return (new A)->missing();}catch(Error $e){return "error";}}', '"error"'],
            'missingKnownStatic' => ['<?php class A{}function target(){try{return A::missing();}catch(Error $e){return "error";}}', '"error"'],
            'scalarMethod' => ['<?php function target(){try{$x=1;return $x->missing();}catch(Error $e){return "error";}}', '"error"'],
            'privateClone' => ['<?php class A{private function __clone(){}}function target(){try{$a=new A;clone $a;return "bad";}catch(Error $e){return "error";}}', '"error"'],
            'noConstructorNamed' => ['<?php class A{}function target(){try{new A(label:3);return "ok";}catch(Error $e){return "error";}}', '"error"'],
            'noConstructorPositional' => ['<?php class A{}function target(){try{new A(3);return "ok";}catch(Error $e){return "error";}}', '"ok"'],
            'abstractStaticMethod' => ['<?php abstract class A{abstract public static function value();}function target(){try{return A::value();}catch(Error $e){return "error";}}', '"error"'],
            'newFromObject' => ['<?php class A{public int $x=3;}function target(){$a=new A;$b=new $a;return $b->x;}', '3'],
            'newFromScalar' => ['<?php function target(){try{$class=3;new $class;return "bad";}catch(Error $e){return "error";}}', '"error"'],
        ];
    }
}
