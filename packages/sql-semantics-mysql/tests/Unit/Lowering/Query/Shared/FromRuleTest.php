<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\FromRule;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(FromRule::class)]
#[Medium]
final class FromRuleTest extends TestCase
{
    public function testFromLowersEveryFromClause(): void
    {
        self::assertSame('SELECT 1 FROM DUAL', (new Inspection('mysql-8.4.7'))->inspect('select 1 from dual')->toString());
        self::assertSame('SELECT 1 FROM t, u', (new Inspection('mysql-5.7.44'))->inspect('select 1 from t, u')->toString());
    }

    public function testTablesAnswersOneReferenceOrAList(): void
    {
        $one = (new Inspection())->inspect('SELECT 1 FROM t');
        $two = (new Inspection())->inspect('SELECT 1 FROM t, u');

        self::assertInstanceOf(Select::class, $one->statement);
        self::assertInstanceOf(Select::class, $two->statement);
        self::assertInstanceOf(TableReference::class, $one->statement->from);
        self::assertInstanceOf(TableList::class, $two->statement->from);
    }

    public function testMembersLowersTheReferencesInOrder(): void
    {
        self::assertSame('SELECT 1 FROM t, u JOIN v ON 1, w', (new Inspection('mysql-5.6.51'))->inspect('select 1 from t, u join v on 1, w')->toString());
    }

    public function testFactorHandsEachGenerationItsFactors(): void
    {
        self::assertSame('SELECT 1 FROM (t)', (new Inspection('mysql-8.4.7'))->inspect('select 1 from (t)')->toString());
        self::assertSame('SELECT 1 FROM (t)', (new Inspection('mysql-5.6.51'))->inspect('select 1 from (t)')->toString());
    }
}
