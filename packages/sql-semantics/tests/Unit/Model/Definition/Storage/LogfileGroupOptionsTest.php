<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Storage\CompletionWait;
use SqlSemantics\Model\Definition\Storage\LogfileGroupOptions;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(LogfileGroupOptions::class)]
#[Medium]
final class LogfileGroupOptionsTest extends TestCase
{
    public function testCarriesEveryInitialProperty(): void
    {
        $options = new LogfileGroupOptions(1, 2, 3, 4, 'NDB', 'logs', CompletionWait::NoWait);
        self::assertSame([1, 2, 3, 4, 'NDB', 'logs', CompletionWait::NoWait], [$options->initialSize, $options->undoBufferSize, $options->redoBufferSize, $options->nodegroup, $options->engine, $options->comment, $options->waiting]);
    }

    public function testRejectsANegativeNodeGroup(): void
    {
        $this->expectException(InvalidStructure::class);
        new LogfileGroupOptions(nodegroup: -1);
    }
}
