<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\TailFacts;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Dependent;

#[CoversClass(TailFacts::class)]
#[Medium]
final class TailFactsTest extends TestCase
{
    public function testDeriveReportsAnUnknownLockedTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $derivation = new Derivation($semantics->context());
        $fact = new QueryFact([], $semantics->context()->columnNames);
        (new TailFacts())->derive($derivation, $derivation->environment(), $fact, null, [new LockingClause(LockStrength::Update, [new QualifiedName(new Name('t'))])], [new VisibleRelation(new Dual(), new RowShape([]), new Name('u'))]);

        self::assertCount(1, $derivation->facts()->diagnostics);
        self::assertInstanceOf(Misuse::class, $derivation->facts()->diagnostics[0]);
        self::assertSame(MisuseRule::UnknownLockedTable, $derivation->facts()->diagnostics[0]->rule);
    }

    public function testLimitDerivesTheOperands(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t LIMIT ?, ?');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(RowLimit::class, $operation->statement->limit);
        self::assertInstanceOf(Dependent::class, $operation->facts->scalar($operation->statement->limit->count)->type);
    }
}
