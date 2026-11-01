<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOn;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOperator;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinStep;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(JoinOn::class)]
#[Medium]
final class JoinOnTest extends TestCase
{
    public function testRenderWritesOnBeforeTheCondition(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $step = new JoinStep(new JoinOperator(), new TableInput(new QualifiedName(new Name('u'))), new JoinOn(new IntegerLiteral('1')));
        $built = Semantics::fromStatement($semantics->context(), new Select([new Star()], new JoinChain(new TableInput(new QualifiedName(new Name('t'))), [$step])));

        self::assertSame('SELECT * FROM t JOIN u ON 1', $built->toString());
        self::assertSame('SELECT * FROM t JOIN u ON t.a = u.a', $semantics->analyze('select * from t join u on t.a = u.a')->toString());
    }

    public function testConditionSeesBothSidesOfTheJoin(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $query = $semantics->analyze('SELECT * FROM t JOIN u ON t.a = u.a', [$t, $u]);

        self::assertInstanceOf(Select::class, $query);
        self::assertInstanceOf(JoinChain::class, $query->from);
        $constraint = $query->from->steps[0]->constraint;
        self::assertInstanceOf(JoinOn::class, $constraint);
        self::assertInstanceOf(Binary::class, $constraint->condition);
        $left = $constraint->condition->left->scalarInformation()->resolution;
        $right = $constraint->condition->right->scalarInformation()->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $left);
        self::assertInstanceOf(ResolvedColumn::class, $right);
        self::assertSame($query->from->first, $left->relation);
        self::assertSame($query->from->steps[0]->relation, $right->relation);
        self::assertSame($u->declarations()[0]->columns[0], $right->declaration());
        self::assertSame([], $query->diagnostics());
    }

    public function testConditionMustBeASingleValue(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT * FROM t JOIN t AS t2 ON (1, 2)', [$t]);

        self::assertInstanceOf(Misuse::class, $query->diagnostics()[0]);
        self::assertSame(MisuseRule::TooManyValueColumns, $query->diagnostics()[0]->rule);
    }
}
