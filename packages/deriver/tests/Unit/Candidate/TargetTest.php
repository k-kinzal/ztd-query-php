<?php

declare(strict_types=1);

namespace Tests\Unit\Candidate;

use Deriver\Candidate\Target;
use Deriver\Query\ReturnQuery;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TargetTest extends TestCase
{
    public function testKeepsTheValidatedQueryAndItsSelection(): void
    {
        $query = new ReturnQuery('f');
        $target = new Target($query, 'f', 'return', 'f');
        self::assertSame($query, $target->query);
        self::assertSame('return', $target->kind);
        self::assertSame('f', $target->selector);
        self::assertNull($target->source);
        self::assertNull($target->register);
        self::assertSame([], $target->registers);
        self::assertNull($target->projection);
    }
}
