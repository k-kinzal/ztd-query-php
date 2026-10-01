<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;

#[CoversClass(TableReference::class)]
#[Small]
final class TableReferenceTest extends TestCase
{
    public function testVisibleNameAndToStringUseTheCorrelationName(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $alias = new Name('u');
        $relation = new TableReference($catalog, new QualifiedName(new Name('users')), $alias);
        self::assertSame($alias, $relation->visibleName());
        self::assertSame('users AS u', $relation->toString());
    }

    public function testToStringWithoutAliasUsesTheQualifiedRelationName(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $relation = new TableReference($catalog, new QualifiedName(new Name('users'), new Name('main')));
        self::assertSame('main.users', $relation->toString());
        self::assertSame($relation->name->name, $relation->visibleName());
    }

    public function testMatchesHidesTheOriginalTableNameWhenAliased(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $relation = new TableReference($catalog, new QualifiedName(new Name('users')), new Name('u'));
        self::assertTrue($relation->matches(null));
        self::assertTrue($relation->matches(new QualifiedName(new Name('u'))));
        self::assertFalse($relation->matches(new QualifiedName(new Name('users'))));
        self::assertFalse($relation->matches(new QualifiedName(new Name('u'), new Name('main'))));
    }

    public function testMatchesUsesTheResolvedNamespaceRatherThanTheFirstSearchSchema(): void
    {
        $table = new Table(new QualifiedName(new Name('users'), new Name('public')));
        $catalog = new Catalog(new SearchPath(new Name('app'), new Name('public')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, $table);
        $relation = new TableReference($catalog, new QualifiedName(new Name('users')));
        self::assertSame([$table], $relation->declarations);
        self::assertTrue($relation->matches($table->name));
        self::assertFalse($relation->matches(new QualifiedName(new Name('users'), new Name('app'))));
    }

    public function testMatchesDoesNotInventAResolvedSchemaWhenDeclarationsAreAbsent(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('app'), new Name('public')), complete: false);
        $relation = new TableReference($catalog, new QualifiedName(new Name('users')));
        self::assertTrue($relation->matches(new QualifiedName(new Name('users'), new Name('app'))));
        self::assertTrue($relation->matches(new QualifiedName(new Name('users'), new Name('public'))));
        self::assertFalse($relation->matches(new QualifiedName(new Name('users'), new Name('absent'))));
    }
    public function testMatchesUsesTheDeclarationNamespaceWhenLookupStartsElsewhere(): void
    {
        $table = new Table(new QualifiedName(new Name('bar')));
        $catalog = new Catalog(new SearchPath(new Name('temp'), new Name('main')), declarationSchema: new Name('main'), tables: $table);
        $reference = new TableReference($catalog, new QualifiedName(new Name('bar')));
        self::assertSame([$table], $reference->declarations);
        self::assertTrue($reference->matches(new QualifiedName(new Name('bar'), new Name('main'))));
        self::assertFalse($reference->matches(new QualifiedName(new Name('bar'), new Name('temp'))));
    }

}
