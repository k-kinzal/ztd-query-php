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
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(Assignment::class)]
#[Medium]
final class AssignmentTest extends TestCase
{
    public function testRenderWritesTheColumnAnEqualsSignAndTheValue(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $target = new MutationTarget(new QualifiedName(new Name('t')));
        $built = Semantics::fromStatement($semantics->context(), new Update($target, [new Assignment(new Name('a'), new IntegerLiteral('1')), new Assignment(new Name('b'), new TextLiteral('x'))]));
        $query = $semantics->analyze("update t set a = 1, b = 'x'");

        self::assertSame("UPDATE t SET a = 1, b = 'x'", $built->toString());
        self::assertSame("UPDATE t SET a = 1, b = 'x'", $query->toString());
        self::assertInstanceOf(Update::class, $query);
        self::assertInstanceOf(Assignment::class, $query->assignments[1]);
        self::assertSame('b', $query->assignments[1]->column->value);
        self::assertInstanceOf(TextLiteral::class, $query->assignments[1]->value);
    }

    public function testValueSeesTheRowOfTheTarget(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('UPDATE t SET a = a + 1', [$t]);

        self::assertInstanceOf(Update::class, $query);
        self::assertInstanceOf(Assignment::class, $query->assignments[0]);
        self::assertInstanceOf(Binary::class, $query->assignments[0]->value);
        $resolution = $query->assignments[0]->value->left->scalarInformation()->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($query->target, $resolution->relation);
        self::assertSame($t->declarations()[0]->columns[1], $resolution->declaration());
        self::assertSame([], $query->diagnostics());
    }
}
