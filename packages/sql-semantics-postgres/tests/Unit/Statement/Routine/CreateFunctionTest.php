<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\AtomicBody;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ReturnStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\RoutineParameters;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(CreateFunction::class)]
#[Medium]
final class CreateFunctionTest extends TestCase
{
    public function testDeriveStatementResolvesParametersInTheBody(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('CREATE FUNCTION add(a int4, b int4) RETURNS int4 RETURN add.a + b', []);
        $statement = $operation->statement;
        self::assertInstanceOf(CreateFunction::class, $statement);
        self::assertInstanceOf(ReturnStatement::class, $statement->body);
        self::assertInstanceOf(BinaryOperation::class, $statement->body->value);
        $resolution = $operation->facts->scalar($statement->body->value->left)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($statement->parameters, $resolution->relation);
        self::assertEquals(new Known(Builtin::Int4), $operation->facts->scalar($statement->body->value)->type);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->shape());
    }

    public function testDeriveStatementLetColumnsShadowParameters(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Text)]);
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('CREATE FUNCTION f(a int4) RETURNS text BEGIN ATOMIC SELECT a FROM t; END', [$table]);
        $statement = $operation->statement;
        self::assertInstanceOf(CreateFunction::class, $statement);
        self::assertInstanceOf(AtomicBody::class, $statement->body);
        $select = $statement->body->statements[0];
        self::assertInstanceOf(Select::class, $select);
        $target = $select->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);
        $resolution = $operation->facts->scalar($target->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($table->columns[0], $resolution->declaration());
    }

    public function testDeriveStatementReportsProblems(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('CREATE FUNCTION f(a int4 DEFAULT 1, b int4) STRICT STRICT AS $$x$$', []);
        self::assertEquals([
            new RoutineProblem(RoutineProblemKind::ConflictingOptions),
            new RoutineProblem(RoutineProblemKind::MissingLanguage),
            new RoutineProblem(RoutineProblemKind::MissingDefault),
            new RoutineProblem(RoutineProblemKind::MissingResultType),
        ], $operation->facts->diagnostics);
    }

    public function testRenderWritesProcedures(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze("create or replace procedure p(inout x int4 = 1) security definer language sql as 'select 1'");
        self::assertSame("CREATE OR REPLACE PROCEDURE p (INOUT x int4 = 1) SECURITY DEFINER LANGUAGE sql AS 'select 1'", $operation->toString());
    }

    public function testRejectsAProcedureResult(): void
    {
        $this->expectExceptionMessage('A procedure has no result.');
        new CreateFunction(new DottedName([new Name('p')]), new RoutineParameters([]), new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), [], null, true);
    }
}
