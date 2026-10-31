<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\KeyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;

#[CoversClass(KeyHint::class)]
#[Small]
final class KeyHintTest extends TestCase
{
    public function testNameAnswersTheHint(): void
    {
        self::assertSame(HintName::NoRangeOptimization, (new KeyHint(HintName::NoRangeOptimization, null, new HintTable('t'), []))->name());
    }

    public function testTextWritesTheBlockTheTableAndTheIndexes(): void
    {
        self::assertSame('MRR(`t`)', (new KeyHint(HintName::Mrr, null, new HintTable('t'), []))->text());
        self::assertSame('NO_INDEX(`t`@`qb` `i`, `PRIMARY`)', (new KeyHint(HintName::NoIndex, null, new HintTable('t', 'qb'), ['i', 'PRIMARY']))->text());
    }

    public function testAnotherFormIsRefused(): void
    {
        $this->expectExceptionMessage('A key hint is an index-level hint.');

        new KeyHint(HintName::Bka, null, new HintTable('t'), []);
    }

    public function testAnEmptyIndexIsRefused(): void
    {
        $this->expectExceptionMessage('An index of a hint has a name.');

        new KeyHint(HintName::Index, null, new HintTable('t'), ['']);
    }
}
