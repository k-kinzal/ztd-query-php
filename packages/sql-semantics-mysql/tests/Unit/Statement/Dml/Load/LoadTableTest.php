<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Load;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(LoadTable::class)]
#[Medium]
final class LoadTableTest extends TestCase
{
    public function testDeriveStatementResolvesColumnsAndAssignments(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->inspect("LOAD DATA INFILE 'f' INTO TABLE t (a, @v) SET b = @v + a, a = DEFAULT", [$t]);
        self::assertInstanceOf(LoadTable::class, $operation->statement);

        self::assertInstanceOf(ResolvedColumn::class, $operation->facts->scalar($operation->statement->assignments[0]->column)->resolution);
        self::assertEquals(new Known(new Integral(IntegralKind::Int)), $operation->facts->scalar($operation->statement->assignments[1]->value)->type);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame("LOAD XML CONCURRENT INFILE 'f' REPLACE INTO TABLE t PARTITION (p) CHARSET utf8mb4 ROWS IDENTIFIED BY '<r>' COLUMNS TERMINATED BY ',' LINES TERMINATED BY ';' IGNORE 1 LINES (a, @b) SET c = @b", (new Inspection('mysql-5.6.51'))->inspect("load xml concurrent infile 'f' replace into table t partition (p) charset utf8mb4 rows identified by '<r>' fields terminated by ',' lines terminated by ';' ignore 1 rows (a, @b) set c = @b")->toString());
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE t CHARSET latin1 COMPRESSION = 'zstd'", (new Inspection('mysql-9.1.0'))->inspect("load data infile 'f' into table t character set latin1 compression = 'zstd' ()")->toString());
    }

    public function testRenderRejectsACorrelationName(): void
    {
        $this->expectExceptionMessage('The table of LOAD has no correlation name.');

        new LoadTable(LoadFormat::Data, null, new LoadInput(false, LoadSource::Infile, new Text('f')), null, new WriteTarget(new QualifiedName(new Name('t')), new Name('x')));
    }
}
