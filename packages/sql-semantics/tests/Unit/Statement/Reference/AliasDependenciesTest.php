<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\Subquery\SqliteExists;
use SqlSemantics\Statement\Expression\Subquery\SqliteSubquery;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Reference\AliasDependencies;
use SqlSemantics\Statement\Reference\NamedAlias;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(AliasDependencies::class)]
#[Small]
final class AliasDependenciesTest extends TestCase
{
    public function testReferencesFindsNamedTargetsAcrossQueryBoundaries(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $field = new Field(new NullConstant(), new Name('answer'));
        $fields = new Fields($outer, $field);
        $nested = new Scope(new SqliteAliasScope($fields, $field));
        $reference = new ColumnReference($nested, new Name('answer'));
        self::assertInstanceOf(NamedAlias::class, $reference->resolution);
        $query = new SqliteExists(new SqliteSubquery($outer, new Select(new Fields($nested, new Field($reference)))));
        self::assertSame([$reference->resolution], (new AliasDependencies())->references($query));
    }

    public function testPreservedRejectsRemovalAndShadowingButAllowsAddingUnrelatedFields(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $field = new Field(new NullConstant(), new Name('answer'));
        $fields = new Fields($outer, $field);
        $reference = new ColumnReference(new Scope(new SqliteAliasScope($fields, $field)), new Name('answer'));
        $dependencies = new AliasDependencies();
        self::assertTrue($dependencies->preserved($reference, $fields));
        self::assertTrue($dependencies->preserved($reference, $fields->addField(new Field(new NullConstant(), new Name('other')))));
        self::assertFalse($dependencies->preserved($reference, new Fields($outer)));
        self::assertFalse($dependencies->preserved($reference, new Fields($outer, clone $field, $field)));
    }

}
