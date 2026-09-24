<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Declaration\TableConstraint;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\SchemaReader;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\ConstraintKind;
use SqlSemantics\Schema\ReferentialAction;

#[CoversClass(TableConstraint::class)]
#[Medium]
final class TableConstraintTest extends TestCase
{
    public function testReaderRetainsForeignKeyActionsMatchingAndDeferrability(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TABLE t(id INTEGER, n TEXT, CONSTRAINT fk FOREIGN KEY (id) REFERENCES p(a) MATCH FULL ON DELETE SET NULL (id) ON UPDATE CASCADE DEFERRABLE INITIALLY DEFERRED, CHECK (id > 0), UNIQUE (n))');
        $table = (new SchemaReader(new Identifiers(Dialect::PostgreSql), 'public'))->table($tree);
        $foreign = $table->constraints[0];
        self::assertSame(ConstraintKind::ForeignKey, $foreign->kind);
        self::assertSame('fk', $foreign->name);
        self::assertSame(['id'], $foreign->columns);
        self::assertSame(['p'], $foreign->referencedTable);
        self::assertSame(['a'], $foreign->referencedColumns);
        self::assertSame(ReferentialAction::SetNull, $foreign->onDelete);
        self::assertSame(ReferentialAction::Cascade, $foreign->onUpdate);
        self::assertSame('full', $foreign->match);
        self::assertTrue($foreign->deferrable);
        self::assertTrue($foreign->initiallyDeferred);
        self::assertSame(['id'], $foreign->deleteColumns);
        self::assertNull($foreign->expression);
        $check = $table->constraints[1];
        self::assertSame(ConstraintKind::Check, $check->kind);
        self::assertSame([], $check->columns);
        self::assertSame('id > 0', trim($check->expression?->toString() ?? ''));
        self::assertSame(ConstraintKind::Unique, $table->constraints[2]->kind);
        self::assertSame(['n'], $table->constraints[2]->columns);
    }

    public function testDefaultsDescribeAnImmediateSimpleConstraint(): void
    {
        $source = new Node('TableConstraint', 0, []);
        $constraint = new TableConstraint(ConstraintKind::Unique, ['a'], $source);
        self::assertNull($constraint->name);
        self::assertSame([], $constraint->referencedTable);
        self::assertSame([], $constraint->referencedColumns);
        self::assertNull($constraint->expression);
        self::assertSame(ReferentialAction::NoAction, $constraint->onDelete);
        self::assertSame(ReferentialAction::NoAction, $constraint->onUpdate);
        self::assertSame('simple', $constraint->match);
        self::assertFalse($constraint->deferrable);
        self::assertFalse($constraint->initiallyDeferred);
        self::assertSame([], $constraint->deleteColumns);
    }
}
