<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Trusted runtime class-operand fixtures with independently checked expected values.
 * @visibility root
 */
final class DynamicClassPrograms
{
    /**
     * @return iterable<string,array{string,string}> Source and expected JSON
     */
    public static function cases(): iterable
    {
        $operations = [
            'new' => 'return get_class(new $class);',
            'method' => 'return $class::value();',
            'property' => 'return $class::$value;',
            'instance' => 'return $object instanceof $class;',
            'first-class' => '$callable=$class::value(...);return $callable();',
        ];
        foreach (['self','parent','static','\\self','SELF','Box','\\bOx','Base'] as $class) {
            foreach ($operations as $name => $operation) {
                $known = in_array($class, ['Box','\\bOx','Base'], true);
                $expected = $name === 'instance' ? ($known ? 'true' : 'false') : (!$known ? '"Error"' : ($name === 'new' ? ($class === 'Base' ? '"Base"' : '"Box"') : ($class === 'Base' ? '3' : '7')));
                $source = '<?php class Base{public static $value=3;public static function value(){return 3;}}class Box extends Base{public static $value=7;public static function value(){return 7;}public static function run(){$object=new Box;$class=' . var_export($class, true) . ';try{' . $operation . '}catch(Throwable $e){return get_class($e);}}}function target(){return Box::run();}';
                yield $class . ' ' . $name => [$source, $expected];
            }
        }
        foreach (['self','parent','static'] as $class) {
            foreach ($operations as $name => $operation) {
                $body = str_replace('$class', $class, $operation);
                $expected = $name === 'instance' ? 'true' : ($name === 'new' ? ($class === 'parent' ? '"Base"' : '"Box"') : ($class === 'parent' ? '3' : '7'));
                yield 'literal ' . $class . ' ' . $name => ['<?php class Base{public static $value=3;static function value(){return 3;}}class Box extends Base{public static $value=7;static function value(){return 7;}static function run(){$object=new Box;' . $body . '}}function target(){return Box::run();}', $expected];
            }
        }
        yield 'method arguments remain unevaluated' => ['<?php class Box{static function value($x){return $x;}static function run(){$log=[];$class="self";try{$class::value($log[]=1);}catch(Error $e){}return $log;}}function target(){return Box::run();}', '[]'];
        yield 'constructor arguments remain unevaluated' => ['<?php class Box{function __construct($x){}static function run(){$log=[];$class="self";try{new $class($log[]=1);}catch(Error $e){}return $log;}}function target(){return Box::run();}', '[]'];
        yield 'static syntax on an object' => ['<?php class Box{static function value(){return 7;}}function target(){$object=new Box;return $object::value();}', '7'];
        yield 'static syntax on an enum' => ['<?php enum E{case A;static function value(){return 7;}}function target(){$object=E::A;return $object::value();}', '7'];
        yield 'static property on an object' => ['<?php class Box{static $value=7;}function target(){$object=new Box;return $object::$value;}', '7'];
        yield 'canonical class after first-class acquisition' => ['<?php class Base{static function value(){return static::class;}}class Box extends Base{}function target(){$class="\\bOx";$callable=$class::value(...);return $callable();}', '"Box"'];
        yield 'object instanceof object class' => ['<?php class Box{}function target(){$bound=new Box;return new Box instanceof $bound;}', 'true'];
        yield 'closure instanceof closure class' => ['<?php function target(){$bound=fn()=>1;return (fn()=>2) instanceof $bound;}', 'true'];
        yield 'object instanceof array rejected' => ['<?php class Box{}function target(){$bound=[];try{return new Box instanceof $bound;}catch(Error $e){return "Error";}}', '"Error"'];
        yield 'object instanceof integer rejected' => ['<?php class Box{}function target(){$bound=1;try{return new Box instanceof $bound;}catch(Error $e){return "Error";}}', '"Error"'];
        yield 'object instanceof null rejected' => ['<?php class Box{}function target(){$bound=null;try{return new Box instanceof $bound;}catch(Error $e){return "Error";}}', '"Error"'];
        yield 'scalar instanceof invalid bound short circuits' => ['<?php function target(){$bound=1;return 0 instanceof $bound;}', 'false'];
        yield 'allocation from an object' => ['<?php class Box{}function target(){$class=new Box;return get_class(new $class);}', '"Box"'];
        yield 'object static nonstatic method rejected' => ['<?php class Box{function value(){return 7;}}function target(){$object=new Box;try{return $object::value();}catch(Error $e){return "Error";}}', '"Error"'];
        yield 'literal called class is canonical' => ['<?php class Box{static function value(){return static::class;}}function target(){return bOx::value();}', '"Box"'];
        yield 'dynamic called class is canonical' => ['<?php class Box{static function value(){return static::class;}}function target(){$class="bOx";return $class::value();}', '"Box"'];
    }

    /**
     * @return iterable<string,array{string,string}> Allocation fixtures
     */
    public static function allocations(): iterable
    {
        foreach (self::cases() as $name => $case) {
            if (str_contains($name, 'new') || str_contains($name, 'constructor') || str_contains($name, 'allocation')) {
                yield $name => $case;
            }
        }
    }

    /**
     * @return iterable<string,array{string,string}> Method and callable fixtures
     */
    public static function methods(): iterable
    {
        foreach (self::cases() as $name => $case) {
            if (str_contains($name, 'method') || str_contains($name, 'first-class') || str_contains($name, 'called class') || str_contains($name, 'static syntax')) {
                yield $name => $case;
            }
        }
    }

    /**
     * @return iterable<string,array{string,string}> Static property fixtures
     */
    public static function properties(): iterable
    {
        foreach (self::cases() as $name => $case) {
            if (str_contains($name, 'property')) {
                yield $name => $case;
            }
        }
    }

    /**
     * @return iterable<string,array{string,string}> Class-relation fixtures
     */
    public static function instances(): iterable
    {
        foreach (self::cases() as $name => $case) {
            if (str_contains($name, 'instance')) {
                yield $name => $case;
            }
        }
    }
}
