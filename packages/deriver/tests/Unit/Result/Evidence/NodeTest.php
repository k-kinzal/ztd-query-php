<?php

declare(strict_types=1);

namespace Tests\Unit\Result\Evidence;

use Deriver\Result\Evidence\Node as Subject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class NodeTest extends TestCase
{
    public function testIdentityIncludesTheInputRole(): void
    {
        $leaf = new Subject('context-any');
        $left = new Subject('operation', ['left' => $leaf]);
        $right = new Subject('operation', ['right' => $leaf]);
        self::assertNotSame($left->id, $right->id);
    }

}
