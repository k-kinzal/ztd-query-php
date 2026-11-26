<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rules\Dml\InsertFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(InsertFacts::class)]
#[Medium]
final class InsertFactsTest extends TestCase
{
    public function testRowsChecksEveryRowAgainstTheFirst(): void
    {
        $operation = (new Inspection())->diagnose('INSERT INTO t VALUES (1, 2), (3)');

        self::assertSame(["Column count doesn't match value count at row 2"], array_map(static fn ($diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testRowsChecksAnEmptyRowAgainstTheOtherRows(): void
    {
        $semantics = new Inspection();
        $tables = $semantics->diagnose('CREATE TABLE t (a INT, b INT)')->declarations();
        $messages = static fn (string $sql): array => array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->diagnose($sql, $tables)->facts->diagnostics);

        self::assertSame(["Column count doesn't match value count at row 2"], $messages('INSERT INTO t VALUES (1, 2), ()'));
        self::assertSame(["Column count doesn't match value count at row 2"], $messages('INSERT INTO t VALUES (), (1, 2)'));
        self::assertSame([], $messages('INSERT INTO t VALUES (), ()'));
    }

    public function testSetDerivesTheAssignments(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->diagnose('INSERT INTO t SET a = 1, b = a', [$t]);
        self::assertInstanceOf(InsertSet::class, $operation->statement);

        self::assertInstanceOf(ResolvedColumn::class, $operation->facts->scalar($operation->statement->assignments[1]->value)->resolution);
    }

    public function testQueryDerivesTheSourceWithoutTheTable(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->diagnose('INSERT INTO t (a) SELECT b', [$t]);

        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testOpenReportsAQualifiedStar(): void
    {
        $operation = (new Inspection('mysql-5.6.51'))->diagnose('INSERT INTO t (t.*) VALUES (1)');

        self::assertSame("Unknown column '*' in 'field list'", $operation->facts->diagnostics[0]->message());
    }

    public function testColumnDependsOnAnUndeclaredTable(): void
    {
        $operation = (new Inspection())->diagnose('INSERT INTO t VALUES (DEFAULT)');
        self::assertInstanceOf(InsertRows::class, $operation->statement);

        self::assertInstanceOf(Dependent::class, $operation->facts->scalar($operation->statement->rows[0]->values[0])->type);
    }

    public function testColumnIsInvalidBeyondTheWrittenColumns(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->diagnose('INSERT INTO t (a) VALUES (1, DEFAULT)', [$t]);
        self::assertInstanceOf(InsertRows::class, $operation->statement);

        self::assertInstanceOf(Invalid::class, $operation->facts->scalar($operation->statement->rows[0]->values[1])->type);
    }

    public function testDuplicatesReportsAnAliasThatIsTheTableName(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->diagnose('INSERT INTO t VALUES (1, 2) AS t ON DUPLICATE KEY UPDATE a = 1', [$t]);

        self::assertSame('Not unique table/alias', $operation->facts->diagnostics[0]->message());
    }
}
