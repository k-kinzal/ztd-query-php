<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule;

#[CoversClass(KindRule::class)]
#[Small]
final class KindRuleTest extends TestCase
{
    public function testCasesHoldTheMessagesOfTheServer(): void
    {
        self::assertSame('"%1$s" is not a view', KindRule::NotView->value);
    }

    public function testNamesRelationTellsWhetherTheMessageNamesTheRelation(): void
    {
        self::assertSame([true, false], [KindRule::NotSequence->namesRelation(), KindRule::ForeignPrimaryKey->namesRelation()]);
    }
}
