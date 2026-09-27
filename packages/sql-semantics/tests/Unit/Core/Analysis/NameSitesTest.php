<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\Forms;
use SqlSemantics\Core\Analysis\NameSite;
use SqlSemantics\Core\Analysis\NameSites;
use SqlSemantics\Core\Language;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Statement\ReferenceKind;
use SqlSemantics\Statement\Traversal;
use SqlSemantics\Statement\Writer;
use Tests\Contract\Resolving;

#[CoversClass(NameSites::class)]
#[UsesClass(NameSite::class)]
#[UsesClass(Forms::class)]
#[UsesClass(Semantics::class)]
#[UsesClass(Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Resolver::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Core\Policy\RelationRules::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(Writer::class)]
#[UsesClass(Traversal::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[Medium]
final class NameSitesTest extends TestCase
{
    public function testFindListsDeclarationsDropsDefinitionsAndReferencesInWalkingOrder(): void
    {
        $sites = Resolving::sites(MySqlDialect::MySql);
        $found = $sites->find((new Semantics(MySqlDialect::MySql))->analyze('WITH c AS (SELECT 1 FROM db.t) SELECT * FROM c JOIN u')->command);
        self::assertSame(['c:CommonTableExpression', 'db.t:Dependency', 'c:Dependency', 'u:Dependency'], array_map(static fn (NameSite $site): string => implode('.', $site->name) . ':' . $site->kind->name, $found));
        $drop = $sites->find((new Semantics(MySqlDialect::MySql))->analyze('DROP TABLE IF EXISTS a, b')->command);
        self::assertSame([ReferenceKind::Drop, ReferenceKind::Drop], array_map(static fn (NameSite $site): ReferenceKind => $site->kind, $drop));
        self::assertSame([true, true], array_map(static fn (NameSite $site): bool => $site->conditional, $drop));
        $create = $sites->find((new Semantics(MySqlDialect::MySql))->analyze('CREATE TABLE t (id INT)')->command);
        self::assertSame([ReferenceKind::Declaration], array_map(static fn (NameSite $site): ReferenceKind => $site->kind, $create));
        self::assertSame([], $sites->find((new Semantics(MySqlDialect::MySql))->analyze('CREATE VIEW v AS SELECT 1')->command));
    }

    public function testNamedReadsTheValuesOfAListANameAndAPairSite(): void
    {
        $sites = Resolving::sites(MySqlDialect::MySql);
        $symbols = ['DROP', 'opt_temporary', 'table_or_tables', 'if_exists', 'table_list', 'opt_restrict'];
        $drop = Resolving::form(MySqlDialect::MySql, (new Semantics(MySqlDialect::MySql))->analyze('DROP TABLE a, b')->command, 'drop_table_stmt');
        $taken = [];
        $consumed = [];
        $named = $sites->named($symbols, $drop->children(), ['rule' => 'drop_table_stmt', 'names' => 'table_list', 'list' => ['table_list', ['table_list', ',', 'table_ident']]], $taken, $consumed);
        self::assertSame([['a'], ['b']], array_column($named, 1));
        self::assertSame([4 => true], $taken);
        self::assertNotEmpty($consumed);
        $taken = [];
        $consumed = [];
        $named = $sites->named($symbols, $drop->children(), ['rule' => 'drop_table_stmt', 'name' => 'table_list'], $taken, $consumed);
        self::assertSame([['a', 'b']], array_column($named, 1));
        $qualified = Resolving::form(MySqlDialect::MySql, (new Semantics(MySqlDialect::MySql))->analyze('SELECT 1 FROM db.t')->command, 'table_ident');
        $taken = [];
        $consumed = [];
        $named = $sites->named(['ident', '.', 'ident'], $qualified->children(), ['rule' => 'table_ident', 'pair' => ['ident', 'ident']], $taken, $consumed);
        self::assertSame([['db', 't']], array_column($named, 1));
        self::assertSame('db', Writer::render($named[0][0]));
        self::assertSame([0 => true, 2 => true], $taken);
    }

    public function testConsumeMarksAValueAndEverythingBelowIt(): void
    {
        $list = Resolving::form(MySqlDialect::MySql, (new Semantics(MySqlDialect::MySql))->analyze('DROP TABLE a, b')->command, 'table_list');
        $consumed = [];
        Resolving::sites(MySqlDialect::MySql)->consume($consumed, $list);
        self::assertArrayHasKey(spl_object_id($list), $consumed);
        self::assertArrayHasKey(spl_object_id($list->children()[0]), $consumed);
        self::assertGreaterThan(2, count($consumed));
    }

    public function testHoldsNamesTellsFormsThatWriteNamesFromNamesThemselves(): void
    {
        $sites = Resolving::sites(MySqlDialect::MySql);
        self::assertTrue($sites->holdsNames(Resolving::form(MySqlDialect::MySql, (new Semantics(MySqlDialect::MySql))->analyze('DROP TABLE a, b')->command, 'table_list')));
        self::assertFalse($sites->holdsNames(Resolving::form(MySqlDialect::MySql, (new Semantics(MySqlDialect::MySql))->analyze('SELECT 1 FROM db.t')->command, 'table_ident')));
    }

    public function testPartsDecodesQuotedNamesInWritingOrder(): void
    {
        $name = Resolving::form(MySqlDialect::MySql, (new Semantics(MySqlDialect::MySql))->analyze('SELECT 1 FROM `db`.`t`')->command, 'table_ident');
        self::assertSame(['db', 't'], Resolving::sites(MySqlDialect::MySql)->parts($name));
    }
}
