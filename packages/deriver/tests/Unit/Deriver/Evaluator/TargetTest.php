<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator;

use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Target;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TargetTest extends TestCase
{
    public function testKeepsTheValidatedQueryAndItsSelection(): void
    {
        $query = new ReturnQuery('f');
        $target = new Target('f', \Deriver\Deriver\Evaluator\TargetKind::Return, 'f');
        self::assertFalse(property_exists($target, 'query'));
        self::assertSame(\Deriver\Deriver\Evaluator\TargetKind::Return, $target->kind);
        self::assertSame('f', $target->selector);
        self::assertNull($target->source);
        self::assertNull($target->register);
        self::assertSame([], $target->registers);
        self::assertNull($target->projection);
    }
}
