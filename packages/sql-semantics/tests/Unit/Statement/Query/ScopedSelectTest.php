<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(\SqlSemantics\Statement\Query\ScopedSelect::class)]
#[Small]
final class ScopedSelectTest extends TestCase
{
    public function testFieldsReadsTheBodyAtItsLexicalOwner(): void
    {
        $context = new Catalog(new SearchPath(new Name('main')));
        $outer = new \SqlSemantics\Statement\Relation\Scope($context);
        $body = new \SqlSemantics\Statement\Query\ScopedSelect($outer, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition(new C\Expression\ColumnUse(new Name('id')))), new C\Query\Inputs(new C\Query\NamedInput(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('items'))))));
        self::assertSame($outer, $body->scope->parent);
        self::assertSame(1, $body->fields()->count());
    }

    public function testFieldReadsTheBodyAtItsLexicalOwner(): void
    {
        $context = new Catalog(new SearchPath(new Name('main')));
        $outer = new \SqlSemantics\Statement\Relation\Scope($context);
        $body = new \SqlSemantics\Statement\Query\ScopedSelect($outer, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition(new C\Expression\ColumnUse(new Name('id')))), new C\Query\Inputs(new C\Query\NamedInput(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('items'))))));
        self::assertSame($outer, $body->scope->parent);
        self::assertSame('id', $body->field('id')->name->value);
    }

    public function testContextReadsTheBodyAtItsLexicalOwner(): void
    {
        $context = new Catalog(new SearchPath(new Name('main')));
        $outer = new \SqlSemantics\Statement\Relation\Scope($context);
        $body = new \SqlSemantics\Statement\Query\ScopedSelect($outer, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition(new C\Expression\ColumnUse(new Name('id')))), new C\Query\Inputs(new C\Query\NamedInput(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('items'))))));
        self::assertSame($outer, $body->scope->parent);
        self::assertSame($context, $body->context());
    }

    public function testProfileReadsTheBodyAtItsLexicalOwner(): void
    {
        $context = new Catalog(new SearchPath(new Name('main')));
        $outer = new \SqlSemantics\Statement\Relation\Scope($context);
        $body = new \SqlSemantics\Statement\Query\ScopedSelect($outer, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition(new C\Expression\ColumnUse(new Name('id')))), new C\Query\Inputs(new C\Query\NamedInput(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('items'))))));
        self::assertSame($outer, $body->scope->parent);
        self::assertSame($context->profile, $body->profile());
    }

    public function testLookupFieldReadsTheBodyAtItsLexicalOwner(): void
    {
        $context = new Catalog(new SearchPath(new Name('main')));
        $outer = new \SqlSemantics\Statement\Relation\Scope($context);
        $body = new \SqlSemantics\Statement\Query\ScopedSelect($outer, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition(new C\Expression\ColumnUse(new Name('id')))), new C\Query\Inputs(new C\Query\NamedInput(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('items'))))));
        self::assertSame($outer, $body->scope->parent);
        self::assertSame(\SqlSemantics\Statement\Projection\AbsentField::Value, $body->lookupField('absent'));
    }

    public function testSingleNamedInputReadsTheBodyAtItsLexicalOwner(): void
    {
        $context = new Catalog(new SearchPath(new Name('main')));
        $outer = new \SqlSemantics\Statement\Relation\Scope($context);
        $body = new \SqlSemantics\Statement\Query\ScopedSelect($outer, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition(new C\Expression\ColumnUse(new Name('id')))), new C\Query\Inputs(new C\Query\NamedInput(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('items'))))));
        self::assertSame($outer, $body->scope->parent);
        self::assertSame('items', $body->singleNamedInput()->name->name->value);
    }

    public function testToStringReadsTheBodyAtItsLexicalOwner(): void
    {
        $context = new Catalog(new SearchPath(new Name('main')));
        $outer = new \SqlSemantics\Statement\Relation\Scope($context);
        $body = new \SqlSemantics\Statement\Query\ScopedSelect($outer, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition(new C\Expression\ColumnUse(new Name('id')))), new C\Query\Inputs(new C\Query\NamedInput(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('items'))))));
        self::assertSame($outer, $body->scope->parent);
        self::assertSame('SELECT id FROM items', $body->toString());
    }
}
