<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\ControlFlow\CallableGraph;
use Deriver\Evaluation\Context;
use RuntimeException;

/**
 * Supplies entry bodies of a class hierarchy whose properties cover every declaration form an entry can initialize.
 * @visibility root
 */
final class ReceiverFixture
{
    /**
     * Compiles the fixture world and selects one entry callable.
     * @param string $symbol Callable identity
     * @return array{Context, CallableGraph} Declaration world and entry body
     * @throws RuntimeException If the fixture does not declare the callable
     */
    public static function entry(string $symbol): array
    {
        $context = SolverFixture::context('<?php declare(strict_types=1); class Base{private string $table="users";protected ?int $limit=null;}'
            . 'final class Box extends Base{private string $order="name";public static int $count=0;public int|string $key=0;public $loose;function run(){}static function make(){}}function free(){}');
        $body = $context->program->callable($symbol);
        if ($body === null) {
            throw new RuntimeException('The receiver fixture does not declare ' . $symbol);
        }
        return [$context, $body];
    }
}
