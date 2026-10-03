<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;

#[CoversClass(Catalog::class)]
#[Small]
final class CatalogTest extends TestCase
{
    public function testMatchingTablesUsesSearchPathPrecedenceAndExactDeclarations(): void
    {
        $first = new Table(new QualifiedName(new Name('users'), new Name('app')));
        $second = new Table(new QualifiedName(new Name('users'), new Name('public')));
        $catalog = new Catalog(new SearchPath(new Name('app'), new Name('public')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, $second, $first);
        self::assertSame([$first], $catalog->matchingTables(new QualifiedName(new Name('users'))));
        self::assertSame([$second], $catalog->matchingTables($second->name));
        self::assertSame([], $catalog->matchingTables(new QualifiedName(new Name('absent'))));
        self::assertSame([$second, $first], $catalog->tables);
    }

    public function testMatchingTablesKeepsConflictingDeclarationsWithoutDuplicatingTheSameObject(): void
    {
        $first = new Table(new QualifiedName(new Name('users')));
        $second = new Table($first->name);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, $first, $second, $first);
        self::assertSame([$first, $second], $catalog->matchingTables($first->name));
    }

    public function testMatchingTablesDistinguishesCatalogs(): void
    {
        $table = new Table(new QualifiedName(new Name('users')));
        $catalog = new Catalog(new SearchPath(new Name('app')), Comparison::Sensitive, Comparison::AsciiInsensitive, true, new Name('database'), null, $table);
        self::assertSame([$table], $catalog->matchingTables(new QualifiedName(new Name('users'), new Name('app'), new Name('database'))));
        self::assertSame([], $catalog->matchingTables(new QualifiedName(new Name('users'), new Name('app'), new Name('other'))));
    }
    public function testMatchingTablesSeparatesDeclarationNamespaceFromLookupPrecedence(): void
    {
        $main = new Table(new QualifiedName(new Name('bar')));
        $temporary = new Table(new QualifiedName(new Name('bar'), new Name('temp')));
        $catalog = new Catalog(new SearchPath(new Name('temp'), new Name('main')), Comparison::AsciiInsensitive, Comparison::AsciiInsensitive, true, null, new Name('main'), $main, $temporary);
        self::assertSame([$temporary], $catalog->matchingTables(new QualifiedName(new Name('bar'))));
        self::assertSame([$main], $catalog->matchingTables(new QualifiedName(new Name('bar'), new Name('main'))));
        self::assertSame('main', $catalog->declarationSchema->value);
        self::assertSame([$main, $temporary], $catalog->tables);
    }

}
