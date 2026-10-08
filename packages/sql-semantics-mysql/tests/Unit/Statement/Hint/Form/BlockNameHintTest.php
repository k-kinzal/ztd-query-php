<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\BlockNameHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;

#[CoversClass(BlockNameHint::class)]
#[Small]
final class BlockNameHintTest extends TestCase
{
    public function testNameAnswersQbName(): void
    {
        self::assertSame(HintName::QbName, (new BlockNameHint('q'))->name());
    }

    public function testTextQuotesTheName(): void
    {
        self::assertSame('QB_NAME(`select#1`)', (new BlockNameHint('select#1'))->text());
    }

    public function testAnEmptyNameIsRefused(): void
    {
        $this->expectExceptionMessage('A query block has a name.');

        new BlockNameHint('');
    }
}
