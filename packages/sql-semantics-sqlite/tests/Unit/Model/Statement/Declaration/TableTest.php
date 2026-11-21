<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\Sqlite\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\ImplicitColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

#[CoversClass(Table::class)]
#[Small]
final class TableTest extends TestCase
{
    public function testMatchingColumnsFindsDeclaredColumnsBeforeImplicitOnes(): void
    {
        $id = new Column(new Name('id'), Storage::Integer);
        $rowid = new Column(new Name('rowid'), Storage::Integer);
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::Sqlite3472), [$id], [new ImplicitColumn([new Name('rowid'), new Name('id')], $rowid)]);

        self::assertSame([$id], $table->matchingColumns('id', Comparison::Sensitive));
        self::assertSame([$rowid], $table->matchingColumns('rowid', Comparison::Sensitive));
        self::assertSame([], $table->matchingColumns('other', Comparison::Sensitive));
    }

    public function testMatchingColumnsKeepsARepeatedDeclaredName(): void
    {
        $first = new Column(new Name('a'), Storage::Integer);
        $second = new Column(new Name('A'), Storage::Text);
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::Sqlite3472), [$first, $second]);

        self::assertSame([$first, $second], $table->matchingColumns('a', Comparison::AsciiInsensitive));
        self::assertSame([$first], $table->matchingColumns('a', Comparison::Sensitive));
    }

    public function testMatchingColumnsSearchesEveryImplicitNameWhenNoDeclaredColumnMatches(): void
    {
        $rowid = new Column(new Name('rowid'), Storage::Integer);
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::Sqlite3472), [], [new ImplicitColumn([new Name('rowid'), new Name('oid')], $rowid)], false);

        self::assertSame([$rowid], $table->matchingColumns('OID', Comparison::AsciiInsensitive));
        self::assertSame([], $table->matchingColumns('OID', Comparison::Sensitive));
        self::assertFalse($table->complete);
    }

    public function testACompleteTableIsTheDefault(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::Sqlite3472), []);

        self::assertTrue($table->complete);
        self::assertSame([], $table->implicit);
    }

    public function testKindIsABaseTableUnlessStated(): void
    {
        $profile = (new Semantics())->profile();
        $name = new QualifiedName(new Name('v'));

        self::assertSame(RelationKind::BaseTable, (new Table($name, $profile, []))->kind);
        self::assertSame(RelationKind::View, (new Table($name, $profile, [], [], true, RelationKind::View))->kind);
    }
}
