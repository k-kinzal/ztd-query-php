<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Alter\AlterIndexRemovalLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexLock;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AlterIndexRemovalLowering::class)]
#[Medium]
final class AlterIndexRemovalLoweringTest extends TestCase
{
    public function testRemovalRecognizesOneKeyActionAmongPolicyDirectives(): void
    {
        $inspected = (new Inspection())->inspect('ALTER TABLE app.t LOCK=NONE, DROP PRIMARY KEY, ALGORITHM=COPY');
        self::assertInstanceOf(AlterTable::class, $inspected->statement);
        $selected = (new AlterIndexRemovalLowering())->removal($inspected->statement);

        self::assertSame(ElementKind::PrimaryKey, $selected?->kind);
    }

    public function testRemovalKeepsOtherEffectsOutOfThisRole(): void
    {
        $inspected = (new Inspection())->inspect('ALTER TABLE app.t DROP INDEX first_key, DROP COLUMN spare');
        self::assertInstanceOf(AlterTable::class, $inspected->statement);

        self::assertNull((new AlterIndexRemovalLowering())->removal($inspected->statement));
    }

    public function testLowerRetainsOnlyTheFinalCheckedDirectives(): void
    {
        $semantics = new Semantics();
        $catalog = $semantics->context(['CREATE TABLE app.t (a INT, INDEX by_a (a))']);
        $result = $semantics->analyze(
            'ALTER TABLE app.t ALGORITHM=INSTANT, LOCK=NONE, DROP KEY by_a, LOCK=SHARED, ALGORITHM=COPY',
            $catalog,
        );
        self::assertInstanceOf(DropIndex::class, $result);
        self::assertSame(IndexAlgorithm::Copy, $result->policy->algorithm);
        self::assertSame(IndexLock::Shared, $result->policy->lock);
    }
}
