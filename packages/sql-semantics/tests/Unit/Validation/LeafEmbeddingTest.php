<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Validation\LeafEmbedding;
use SqlSemantics\Validation\ValueGraph;

#[CoversClass(LeafEmbedding::class)]
#[Small]
final class LeafEmbeddingTest extends TestCase
{
    public function testDroppedIsNullWhenEveryLeafIsReachable(): void
    {
        $leaves = new Leaves();
        $name = $leaves->record(new Name('a'));
        $statement = new Select([new ResultColumn(new ColumnUse($name))]);

        self::assertNull((new LeafEmbedding())->dropped($leaves, (new ValueGraph(['SqlSemantics\\']))->objects($statement)));
    }

    public function testDroppedAnswersTheFirstLeafTheStructureLost(): void
    {
        $leaves = new Leaves();
        $kept = $leaves->record(new Name('a'));
        $lost = $leaves->record(new Name('b'));
        $statement = new Select([new ResultColumn(new ColumnUse($kept))]);

        self::assertSame($lost, (new LeafEmbedding())->dropped($leaves, (new ValueGraph(['SqlSemantics\\']))->objects($statement)));
    }

    public function testDroppedComparesByIdentityAndNotByValue(): void
    {
        $leaves = new Leaves();
        $recorded = $leaves->record(new Name('a'));
        $statement = new Select([new ResultColumn(new ColumnUse(new Name('a')))]);

        self::assertSame($recorded, (new LeafEmbedding())->dropped($leaves, (new ValueGraph(['SqlSemantics\\']))->objects($statement)));
    }
}
