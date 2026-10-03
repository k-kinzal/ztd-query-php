<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Reference\CandidateColumn;
use SqlSemantics\Statement\Reference\MissingColumn;
use SqlSemantics\Statement\Reference\NamedAlias;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(SqliteAliasScope::class)]
#[Small]
final class SqliteAliasScopeTest extends TestCase
{
    public function testResolveKeepsNearerAliasesAheadOfOuterNames(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $inner = new Scope($outer);
        $field = new Field(new NullConstant(), new Name('answer'));
        $namespace = new SqliteAliasScope(new Fields($outer, $field), $field);
        $nested = new Scope($namespace);
        $reference = $nested->resolve(new Name('answer'));
        self::assertInstanceOf(NamedAlias::class, $reference);
        self::assertSame($field, $reference->field);
        self::assertSame(MissingColumn::Value, $nested->resolve(new Name('answer'), new QualifiedName(new Name('table'))));
        self::assertSame(MissingColumn::Value, $namespace->resolve(new Name('absent')));
    }

    public function testResolveRetainsConditionalAliasWhenLocalDeclarationIsAbsent(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $table = new TableReference($catalog, new QualifiedName(new Name('missing')));
        $scope = new Scope($catalog, $table);
        $field = new Field(new NullConstant(), new Name('answer'));
        $reference = (new SqliteAliasScope(new Fields($scope, $field), $field))->resolve(new Name('answer'));
        self::assertInstanceOf(CandidateColumn::class, $reference);
        self::assertSame($table, $reference->first);
        self::assertInstanceOf(NamedAlias::class, $reference->possibilities[1]);
        self::assertSame($field, $reference->possibilities[1]->field);
    }

}
