<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Testing\Sqlite\InspectedStatement;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Assignment::class)]
#[Medium]
final class AssignmentTest extends TestCase
{
    public function testRenderWritesTheColumnAnEqualsSignAndTheValue(): void
    {
        $semantics = new Inspection();
        $target = new MutationTarget(new QualifiedName(new Name('t')));
        $built = new InspectedStatement($semantics->context(), new Update($target, [new Assignment(new Name('a'), new IntegerLiteral('1')), new Assignment(new Name('b'), new TextLiteral('x'))]));
        $query = $semantics->inspect("update t set a = 1, b = 'x'");

        self::assertSame("UPDATE t SET a = 1, b = 'x'", $built->toString());
        self::assertSame("UPDATE t SET a = 1, b = 'x'", $query->toString());
        self::assertInstanceOf(Update::class, $query->statement);
        self::assertInstanceOf(Assignment::class, $query->statement->assignments[1]);
        self::assertSame('b', $query->statement->assignments[1]->column->value);
        self::assertInstanceOf(TextLiteral::class, $query->statement->assignments[1]->value);
    }

    public function testValueSeesTheRowOfTheTarget(): void
    {
        $semantics = new Inspection();
        $t = $semantics->inspect('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->inspect('UPDATE t SET a = a + 1', [$t]);

        self::assertInstanceOf(Update::class, $query->statement);
        self::assertInstanceOf(Assignment::class, $query->statement->assignments[0]);
        self::assertInstanceOf(Binary::class, $query->statement->assignments[0]->value);
        $resolution = $query->facts->scalar($query->statement->assignments[0]->value->left)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($query->statement->target, $resolution->relation);
        self::assertSame($t->declarations()[0]->columns[1], $resolution->declaration());
        self::assertSame([], $query->facts->diagnostics);
    }
}
