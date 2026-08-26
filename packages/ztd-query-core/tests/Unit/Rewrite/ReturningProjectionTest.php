<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Rewrite\ReturningProjection;

#[CoversClass(ReturningProjection::class)]
final class ReturningProjectionTest extends TestCase
{
    public function testProjectItemsProjectsNamedWildcardAndAliasedItemsForEveryRow(): void
    {
        $projection = ReturningProjection::fromItems([
            ['source' => 'id', 'output' => 'original_id'],
            ['source' => null, 'output' => null],
            ['source' => 'name', 'output' => 'display_name'],
            ['source' => 'missing', 'output' => null],
        ]);

        self::assertSame([
            ['source' => 'id', 'output' => 'original_id'],
            ['source' => null, 'output' => null],
            ['source' => 'name', 'output' => 'display_name'],
            ['source' => 'missing', 'output' => null],
        ], $projection->items());

        self::assertSame([
            ['original_id' => 1, 'id' => 1, 'name' => 'Alice', 'display_name' => 'Alice', 'missing' => null],
            ['original_id' => 2, 'id' => 2, 'name' => 'Bob', 'display_name' => 'Bob', 'missing' => null],
        ], $projection->project([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]));
    }

    public function testRejectsEmptyProjection(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('Returning projection requires at least one item.');

        ReturningProjection::fromItems([]);
    }

    public function testRejectsWildcardOutputName(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('Wildcard returning projections cannot have an output name.');

        ReturningProjection::fromItems([['source' => null, 'output' => 'all']]);
    }

    public function testRejectsEmptySourceName(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('Returning projection names must not be empty.');

        ReturningProjection::fromItems([['source' => '', 'output' => null]]);
    }

    public function testRejectsEmptyOutputName(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('Returning projection names must not be empty.');

        ReturningProjection::fromItems([['source' => 'id', 'output' => '']]);
    }
}
