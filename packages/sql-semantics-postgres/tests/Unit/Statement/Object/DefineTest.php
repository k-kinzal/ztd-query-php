<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Define;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateArguments;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Define::class)]
#[Medium]
final class DefineTest extends TestCase
{
    public function testDeriveStatementReportsMissingAttributes(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $aggregate = $semantics->analyze('CREATE AGGREGATE a(int4) (initcond = 0)');
        $operator = $semantics->analyze('CREATE OPERATOR === (leftarg = int4)');
        self::assertEquals([new RoutineProblem(RoutineProblemKind::MissingTransition, 'stype'), new RoutineProblem(RoutineProblemKind::MissingTransition, 'sfunc')], $aggregate->facts->diagnostics);
        self::assertEquals([new RoutineProblem(RoutineProblemKind::MissingRightArgument), new RoutineProblem(RoutineProblemKind::MissingOperatorFunction)], $operator->facts->diagnostics);
    }

    public function testRenderWritesEachForm(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['CREATE OR REPLACE AGGREGATE a (basetype = int4, sfunc = f, stype = int4)', 'CREATE TYPE t', 'CREATE COLLATION IF NOT EXISTS c (locale = \'C\')', 'CREATE TEXT SEARCH DICTIONARY d (template = simple)'],
            [$semantics->analyze('CREATE OR REPLACE AGGREGATE a (basetype = int4, sfunc = f, stype = int4)')->toString(), $semantics->analyze('CREATE TYPE t')->toString(), $semantics->analyze("CREATE COLLATION IF NOT EXISTS c (locale = 'C')")->toString(), $semantics->analyze('CREATE TEXT SEARCH DICTIONARY d (template = simple)')->toString()],
        );
    }

    public function testRejectsAShellOfAnotherKind(): void
    {
        $this->expectExceptionMessage('Only a shell type is defined without attributes.');
        new Define(ObjectKind::Collation, new DottedName([new Name('c')]), null);
    }

    public function testRejectsAnOperatorNamedByADottedName(): void
    {
        $this->expectExceptionMessage('An operator, and only an operator, is named by an operator name written without OPERATOR(...).');
        new Define(ObjectKind::Operator, new DottedName([new Name('o')]), [new Definition(new Name('function'))]);
    }

    public function testRejectsAnOldStyleAttributeWithoutValue(): void
    {
        $this->expectExceptionMessage('Every attribute of an old-style aggregate has a value.');
        new Define(ObjectKind::Aggregate, new DottedName([new Name('a')]), [new Definition(new Name('sfunc'))]);
    }

    public function testRejectsArgumentsOfAnotherKind(): void
    {
        $this->expectExceptionMessage('Only an aggregate has an argument list.');
        new Define(ObjectKind::Type, new DottedName([new Name('t')]), null, new AggregateArguments([]));
    }

    public function testRejectsReplaceOfAnotherKind(): void
    {
        $this->expectExceptionMessage('Only an aggregate is defined with OR REPLACE.');
        new Define(ObjectKind::Operator, new OperatorName(new Name('+')), [new Definition(new Name('function'))], null, true);
    }

    public function testRejectsIfNotExistsOfAnotherKind(): void
    {
        $this->expectExceptionMessage('Only a collation is defined with IF NOT EXISTS.');
        new Define(ObjectKind::Type, new DottedName([new Name('t')]), null, null, false, true);
    }

    public function testRejectsAnotherKind(): void
    {
        $this->expectExceptionMessage('CREATE with a definition defines an aggregate, an operator, a type, a text search object or a collation.');
        new Define(ObjectKind::Schema, new DottedName([new Name('s')]), []);
    }

    public function testRenderKeepsAnOldStyleAttributeSpelledLikeAKeywordQuoted(): void
    {
        self::assertSame('CREATE AGGREGATE a ("type" = x, sfunc = f, "select" = int4)', (new Semantics(Dialect::PostgreSql))->analyze('CREATE AGGREGATE a ("type" = x, sfunc = f, "select" = int4)')->toString());
    }

    public function testRenderWritesANewStyleAttributeAsALabel(): void
    {
        self::assertSame('CREATE AGGREGATE a (int4) (type = x, sfunc = f)', (new Semantics(Dialect::PostgreSql))->analyze('CREATE AGGREGATE a (int4) ("type" = x, sfunc = f)')->toString());
    }
}
