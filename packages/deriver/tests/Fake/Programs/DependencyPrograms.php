<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Crosses independent PHP syntax dimensions with dependency entry paths.
 * Generated programs are trusted test fixtures, never application input.
 * @visibility root
 */
final class DependencyPrograms
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function dispatch(): iterable
    {
        yield 'lexical-private' => ['class A{private function name(){return "private";}function get(){return $this->name();}}class B extends A{function name(){return "child";}}function target(){return (new B)->get();}'];
        yield 'inherited-constructor' => ['class A{public $name="base";function __construct(){$this->name=$this->label();}function label(){return "base";}}class B extends A{function label(){return "child";}}function target(){return (new B)->name;}'];
        foreach (['BaseRepo', 'Repo', ''] as $type) {
            foreach (['$repo', '$alias'] as $receiver) {
                foreach (['table', 'dynamic'] as $method) {
                    $name = $method === 'dynamic' ? '$method' : 'table';
                    $body = 'interface Repo{function table();}class BaseRepo implements Repo{public $name="base";function table(){return $this->name;}}class UserRepo extends BaseRepo{public $name="users";function table(){return "child:".$this->name;}}';
                    $body .= 'function sql('.$type.' $repo){$alias=$repo;$method="table";return '.$receiver.'->'.$name.'();}';
                    yield $type.'-'.$receiver.'-'.$method => [$body.'function target(){return sql(repo:new UserRepo);}'];
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mutations(): iterable
    {
        $forms = [
            'assign' => '$value = $value + 3;',
            'add' => '$value += 3;',
            'subtract' => '$value -= 3;',
            'multiply' => '$value *= 3;',
            'divide' => '$value /= 2;',
            'modulo' => '$value %= 3;',
            'power' => '$value **= 2;',
            'left-shift' => '$value <<= 2;',
            'right-shift' => '$value >>= 1;',
            'and' => '$value &= 3;',
            'or' => '$value |= 3;',
            'xor' => '$value ^= 3;',
            'concat' => '$value .= "_archive";',
            'string-increment' => '$value = "a9"; $value++;',
            'null-increment' => '$value = null; ++$value;',
            'pre-increment' => '++$value;',
            'post-increment' => '$value++;',
            'pre-decrement' => '--$value;',
            'post-decrement' => '$value--;',
            'element' => '$value = [4]; $value[0] += 3;',
        ];
        foreach ($forms as $name => $operation) {
            foreach (['local', 'constructor', 'origin', 'static-origin'] as $entry) {
                $mutation = str_replace('$value', $entry === 'local' ? '$value' : ($entry === 'static-origin' ? 'self::$value' : '$this->value'), $operation);
                if ($entry === 'local') {
                    yield $name.'-'.$entry => ['function target(){$value=4;'.$mutation.'return $value;}'];
                } elseif ($entry === 'constructor') {
                    yield $name.'-'.$entry => ['class Box{public $value=4;function __construct(){'.$mutation.'}}function target(){return (new Box)->value;}'];
                } else {
                    $static = $entry === 'static-origin' ? 'static ' : '';
                    $address = $entry === 'static-origin' ? 'self::$value' : '$this->value';
                    yield $name.'-'.$entry => ['class Box{public '.$static.'$value=4;function change(){'.$address.'=4;'.$mutation.'}function get(){return '.$address.';}}function target(){$box=new Box;$box->change();return $box->get();}'];
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function recursion(): iterable
    {
        $forms = [
            'expression' => ['', '$n-1'],
            'assignment' => ['$n=$n-1;', '$n'],
            'compound' => ['$n-=1;', '$n'],
            'post-statement' => ['$n--;', '$n'],
            'pre-expression' => ['', '--$n'],
            'assignment-expression' => ['', '$n-=1'],
            'alias' => ['$other=&$n;$other-=1;', '$n'],
            'reference-call' => ['step($n);', '$n'],
        ];
        foreach ($forms as $name => [$before, $argument]) {
            foreach (['', 'n:'] as $named) {
                foreach ([1, 4, 6] as $initial) {
                    yield $name.'-'.$named.'-'.$initial => ['function step(&$n){$n-=1;}function countDown($n){if($n===0){return 0;}'.$before.'return 1+countDown('.$named.$argument.');}function target(){return countDown('.$initial.');}'];
                }
            }
        }
    }
}
