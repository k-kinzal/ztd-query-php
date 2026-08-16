<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\InsertRowProjector;

#[CoversClass(InsertRowProjector::class)]
final class InsertRowProjectorTest extends TestCase
{
    public function testProjectsOmittedAndExplicitDefaultsToCompleteShape(): void
    {
        $projector = new InsertRowProjector();

        self::assertSame([
            'id' => '1',
            'name' => "'anonymous'",
            'status' => "'active'",
            'note' => 'NULL',
        ], $projector->project(
            ['id', 'name', 'status', 'note'],
            ['id', 'name'],
            ['1', 'DEFAULT'],
            ['name' => "'anonymous'", 'status' => "'active'"],
        ));
    }

    public function testRejectsMismatchedInputShape(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new InsertRowProjector())->project(['id'], ['id'], [], []);
    }
}
