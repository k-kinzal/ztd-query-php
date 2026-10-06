<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\RowExpression;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\RowAssignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(RowAssignment::class)]
#[Medium]
final class RowAssignmentTest extends TestCase
{
    public function testRenderWritesTheColumnListAndTheValue(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $assignment = new RowAssignment([new Name('a'), new Name('b')], new RowExpression([new IntegerLiteral('1'), new TextLiteral('x')]));
        $built = new Operation($semantics->context(), new Update(new MutationTarget(new QualifiedName(new Name('t'))), [$assignment]));
        $query = $semantics->analyze("update t set (a, b) = (1, 'x'), (a) = (2)");

        self::assertSame("UPDATE t SET (a, b) = (1, 'x')", $built->toString());
        self::assertSame("UPDATE t SET (a, b) = (1, 'x'), (a) = (2)", $query->toString());
        self::assertInstanceOf(Update::class, $query->statement);
        self::assertInstanceOf(RowAssignment::class, $query->statement->assignments[0]);
        self::assertSame(['a', 'b'], array_map(static fn (Name $name): string => $name->value, $query->statement->assignments[0]->columns));
        self::assertInstanceOf(RowExpression::class, $query->statement->assignments[0]->value);
    }

    public function testColumnsMustMatchTheWidthOfTheValue(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $wide = $semantics->analyze('UPDATE t SET (a, b) = (1, 2, 3)', [$t]);
        $narrow = $semantics->analyze('UPDATE t SET (a) = (1, 2)', [$t]);
        $exact = $semantics->analyze('UPDATE t SET (a, b) = (1, 2)', [$t]);
        $missing = $semantics->analyze('UPDATE t SET (a, zz) = (1, 2)', [$t]);

        self::assertInstanceOf(ArityMismatch::class, $wide->facts->diagnostics[0]);
        self::assertSame(ArityRule::RowAssignment, $wide->facts->diagnostics[0]->rule);
        self::assertSame('2 columns assigned 3 values.', $wide->facts->diagnostics[0]->message());
        self::assertInstanceOf(ArityMismatch::class, $narrow->facts->diagnostics[0]);
        self::assertSame('1 columns assigned 2 values.', $narrow->facts->diagnostics[0]->message());
        self::assertSame([], $exact->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $missing->facts->diagnostics[0]);
        self::assertSame('zz', $missing->facts->diagnostics[0]->name->value);
    }

    public function testRejectsAnEmptyColumnList(): void
    {
        $this->expectExceptionMessage('A row assignment names at least one column.');

        new RowAssignment([], new IntegerLiteral('1'));
    }
}
