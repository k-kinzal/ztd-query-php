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
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Attribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\BooleanArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\AggregateAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\NameArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\TypeArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Define;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\AttributeProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\AttributeProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateArguments;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
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
        self::assertEquals([new AttributeProblem(AttributeProblemKind::MissingAggregateAttribute, ['stype']), new AttributeProblem(AttributeProblemKind::MissingAggregateAttribute, ['sfunc'])], $aggregate->facts->diagnostics);
        self::assertEquals([new AttributeProblem(AttributeProblemKind::MissingOperatorFunction), new AttributeProblem(AttributeProblemKind::MissingRightArgument)], $operator->facts->diagnostics);
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
        new Define(ObjectKind::Operator, new DottedName([new Name('o')]), [new Attribute(new Name('colour'), null)]);
    }

    public function testRejectsAnOldStyleAttributeWithoutValue(): void
    {
        $this->expectExceptionMessage('Every attribute of an old-style aggregate has a value.');
        new Define(ObjectKind::Aggregate, new DottedName([new Name('a')]), [new Attribute(new Name('hypothetical'), AggregateAttribute::Hypothetical, new BooleanArgument())]);
    }

    public function testRejectsArgumentsOfAnotherKind(): void
    {
        $this->expectExceptionMessage('Only an aggregate has an argument list.');
        new Define(ObjectKind::Type, new DottedName([new Name('t')]), null, new AggregateArguments([]));
    }

    public function testRejectsReplaceOfAnotherKind(): void
    {
        $this->expectExceptionMessage('Only an aggregate is defined with OR REPLACE.');
        new Define(ObjectKind::Operator, new OperatorName(new Name('+')), [new Attribute(new Name('colour'), null)], null, true);
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

    public function testRejectsAnAttributeOfAnotherCommand(): void
    {
        $this->expectExceptionMessage('A definition attribute is recognized exactly when its command knows its name.');
        new Define(ObjectKind::Collation, new DottedName([new Name('c')]), [new Attribute(new Name('hashes'), OperatorAttribute::Hashes, new BooleanArgument())]);
    }

    public function testRejectsAnUnrecognizedAttributeTheCommandKnows(): void
    {
        $this->expectExceptionMessage('A definition attribute is recognized exactly when its command knows its name.');
        new Define(ObjectKind::Operator, new OperatorName(new Name('+')), [new Attribute(new Name('hashes'), null)]);
    }

    public function testDeriveStatementReadsTheFunctionOfAnOperatorAsARoutine(): void
    {
        $define = (new Semantics(Dialect::PostgreSql))->analyze('CREATE OPERATOR === (leftarg = int4, rightarg = int4, function = int4eq)')->statement;
        self::assertInstanceOf(Define::class, $define);
        $definition = $define->definition ?? [];
        self::assertSame([OperatorAttribute::Leftarg, OperatorAttribute::Rightarg, OperatorAttribute::Function], array_map(static fn (Attribute $attribute): ?KnownAttribute => $attribute->known, $definition));
        self::assertEquals([new TypeArgument(new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))), new NameArgument(ObjectKind::Function, new DottedName([new Name('int4eq')]))], [$definition[0]->value, $definition[2]->value]);
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
