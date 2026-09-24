<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Storage\CompletionWait;
use SqlSemantics\Model\Definition\Storage\StorageEncryption;
use SqlSemantics\Model\Definition\Storage\TablespaceOptions;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(TablespaceOptions::class)]
#[Medium]
final class TablespaceOptionsTest extends TestCase
{
    public function testDefaultsOmitEveryOptionAndWait(): void
    {
        $options = new TablespaceOptions();
        self::assertNull($options->initialSize);
        self::assertNull($options->engine);
        self::assertSame(CompletionWait::Wait, $options->waiting);
    }

    public function testCarriesEveryInitialProperty(): void
    {
        $options = new TablespaceOptions(1, 2, 3, 4, 5, 6, 'NDB', 'note', StorageEncryption::Enabled, '{}', CompletionWait::NoWait);
        self::assertSame([1, 2, 3, 4, 5, 6], [$options->initialSize, $options->autoextendSize, $options->maxSize, $options->extentSize, $options->fileBlockSize, $options->nodegroup]);
        self::assertSame(['NDB', 'note', StorageEncryption::Enabled, '{}'], [$options->engine, $options->comment, $options->encryption, $options->engineAttribute]);
    }

    public function testRejectsANegativeSize(): void
    {
        $this->expectException(InvalidStructure::class);
        new TablespaceOptions(extentSize: -1);
    }

    public function testRejectsAnEngineAttributeThatIsNotJson(): void
    {
        $this->expectException(InvalidStructure::class);
        new TablespaceOptions(engineAttribute: '{');
    }
}
