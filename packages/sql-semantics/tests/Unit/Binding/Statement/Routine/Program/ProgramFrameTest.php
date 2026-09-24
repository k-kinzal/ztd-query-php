<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Routine\Program\ProgramFrame;
use SqlSemantics\Binding\Statement\Routine\Program\ProgramNamespace;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Condition\SqlState;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\LocalVariable;
use SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ProgramFrame::class)]
#[Medium]
final class ProgramFrameTest extends TestCase
{
    public function testStartAttachesTheNamespaceToTheTables(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $context = new QueryContext(new TableResolver($schema, new Identifiers(Dialect::MySql), ''));
        $names = new ProgramNamespace();
        $frame = ProgramFrame::start(ProgramKind::Event, $context, $names);
        self::assertSame($names, $frame->context->tables->program);
        self::assertSame($context->ids, $frame->context->ids);
    }

    public function testContextSharesDiagnostics(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $context = new QueryContext(new TableResolver($schema, new Identifiers(Dialect::MySql), ''));
        self::assertSame($context->tables->diagnostics, ProgramFrame::context($context, new ProgramNamespace())->tables->diagnostics);
    }

    public function testNamesReturnsTheAttachedNamespace(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $frame = ProgramFrame::start(ProgramKind::Procedure, new QueryContext(new TableResolver($schema, new Identifiers(Dialect::MySql), '')), new ProgramNamespace())
            ->withVariables([new LocalVariable('a', new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer')))]);
        self::assertNotNull($frame->names()->variable('a'));
    }

    public function testScopeResolvesDeclaredVariables(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $frame = ProgramFrame::start(ProgramKind::Procedure, new QueryContext(new TableResolver($schema, new Identifiers(Dialect::MySql), '')), new ProgramNamespace())
            ->withVariables([new LocalVariable('a', new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer')))]);
        self::assertInstanceOf(LocalVariableReference::class, $frame->scope()->column(['a'], new Token(0, 'IDENT', 'a', 0)));
    }

    public function testExpressionBindsProgramNames(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $frame = ProgramFrame::start(ProgramKind::Procedure, new QueryContext(new TableResolver($schema, new Identifiers(Dialect::MySql), '')), new ProgramNamespace())
            ->withVariables([new LocalVariable('a', new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer')))]);
        self::assertInstanceOf(LocalVariableReference::class, $frame->expression(new Node('simple_ident', 0, [new Token(0, 'IDENT', 'a', 0)])));
    }

    public function testWithVariablesLeavesTheOriginalFrameUnchanged(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $frame = ProgramFrame::start(ProgramKind::Procedure, new QueryContext(new TableResolver($schema, new Identifiers(Dialect::MySql), '')), new ProgramNamespace());
        $inner = $frame->withVariables([new LocalVariable('a', new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer')))]);
        self::assertNull($frame->names()->variable('a'));
        self::assertNotNull($inner->names()->variable('a'));
    }

    public function testWithConditionAddsACaseFoldedName(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $frame = ProgramFrame::start(ProgramKind::Procedure, new QueryContext(new TableResolver($schema, new Identifiers(Dialect::MySql), '')), new ProgramNamespace());
        $state = new SqlState('42S02');
        self::assertSame(['gone' => $state], $frame->withCondition('Gone', $state)->conditions);
    }

    public function testWithCursorAddsACaseFoldedName(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $frame = ProgramFrame::start(ProgramKind::Procedure, new QueryContext(new TableResolver($schema, new Identifiers(Dialect::MySql), '')), new ProgramNamespace());
        self::assertSame(['rows' => true], $frame->withCursor('Rows')->cursors);
    }

    public function testWithLabelRecordsLoops(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $frame = ProgramFrame::start(ProgramKind::Procedure, new QueryContext(new TableResolver($schema, new Identifiers(Dialect::MySql), '')), new ProgramNamespace());
        self::assertSame(['outer' => false, 'spin' => true], $frame->withLabel('Outer', false)->withLabel('spin', true)->labels);
    }

    public function testInHandlerHidesLabelsButKeepsNames(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $frame = ProgramFrame::start(ProgramKind::Procedure, new QueryContext(new TableResolver($schema, new Identifiers(Dialect::MySql), '')), new ProgramNamespace())->withLabel('l', true)->withCursor('c');
        $handler = $frame->inHandler();
        self::assertSame([], $handler->labels);
        self::assertSame(['c' => true], $handler->cursors);
    }
}
