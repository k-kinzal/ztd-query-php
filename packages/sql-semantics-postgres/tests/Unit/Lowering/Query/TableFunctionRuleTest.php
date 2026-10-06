<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Query\TableFunctionRule::class)]
#[Medium]
final class TableFunctionRuleTest extends TestCase
{
    public function testXmlLowersTheNamespaces(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse("SELECT * FROM XMLTABLE (XMLNAMESPACES ('u' AS x, DEFAULT 'v'), '/r' PASSING '<r/>' COLUMNS n FOR ORDINALITY, a text PATH 'a' DEFAULT 'z' NOT NULL, b int)");
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\TableFunctionRule($lowering);
        self::assertCount(2, $rule->xml($tree->find('xmltable')[0], [null, []], false)->namespaces);
    }

    public function testNamespacesReadsDefault(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse("SELECT * FROM XMLTABLE (XMLNAMESPACES ('u' AS x, DEFAULT 'v'), '/r' PASSING '<r/>' COLUMNS n FOR ORDINALITY, a text PATH 'a' DEFAULT 'z' NOT NULL, b int)");
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\TableFunctionRule($lowering);
        self::assertNull($rule->namespaces($tree->find('xml_namespace_list')[0])[1]->prefix);
    }

    public function testXmlColumnsReadsOrdinality(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse("SELECT * FROM XMLTABLE (XMLNAMESPACES ('u' AS x, DEFAULT 'v'), '/r' PASSING '<r/>' COLUMNS n FOR ORDINALITY, a text PATH 'a' DEFAULT 'z' NOT NULL, b int)");
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\TableFunctionRule($lowering);
        self::assertTrue($rule->xmlColumns($tree->find('xmltable_column_list')[0])[0]->ordinality());
    }

    public function testOptionsKeepsTheWrittenOrder(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse("SELECT * FROM XMLTABLE (XMLNAMESPACES ('u' AS x, DEFAULT 'v'), '/r' PASSING '<r/>' COLUMNS n FOR ORDINALITY, a text PATH 'a' DEFAULT 'z' NOT NULL, b int)");
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\TableFunctionRule($lowering);
        self::assertSame([\SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOptionKind::Path, \SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOptionKind::Default, \SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOptionKind::NotNull], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOption $option): \SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOptionKind => $option->kind, $rule->options($tree->find('xmltable_column_option_list')[0])));
    }

    public function testJsonLowersTheColumns(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (n FOR ORDINALITY, v integer PATH '\$.v', f jsonb FORMAT JSON, e boolean EXISTS, NESTED '\$.b' AS q COLUMNS (b text)))");
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\TableFunctionRule($lowering);
        self::assertCount(5, $rule->json($tree->find('json_table')[0], [null, []], false)->columns);
    }

    public function testJsonColumnsLowersNestedColumns(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (n FOR ORDINALITY, v integer PATH '\$.v', f jsonb FORMAT JSON, e boolean EXISTS, NESTED '\$.b' AS q COLUMNS (b text)))");
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\TableFunctionRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonNestedColumns::class, $rule->jsonColumns($tree->find('json_table_column_definition_list')[0])[4]);
    }

    public function testValueReadsThePath(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (n FOR ORDINALITY, v integer PATH '\$.v', f jsonb FORMAT JSON, e boolean EXISTS, NESTED '\$.b' AS q COLUMNS (b text)))");
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\TableFunctionRule($lowering);
        self::assertSame('$.v', $rule->value($lowering->productions->form($tree->find('json_table_column_definition')[1]), null, 2)->path?->value);
    }

    public function testNestedReadsThePathName(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (n FOR ORDINALITY, v integer PATH '\$.v', f jsonb FORMAT JSON, e boolean EXISTS, NESTED '\$.b' AS q COLUMNS (b text)))");
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\TableFunctionRule($lowering);
        self::assertSame('q', $rule->nested($lowering->productions->form($tree->find('json_table_column_definition')[4]), 7, new \SqlSemantics\Statement\Identifier\Name('q'))->pathName?->value);
    }

    public function testPathIsNullWithoutClause(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (n FOR ORDINALITY, v integer PATH '\$.v', f jsonb FORMAT JSON, e boolean EXISTS, NESTED '\$.b' AS q COLUMNS (b text)))");
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\TableFunctionRule($lowering);
        self::assertNull($rule->path($tree->find('json_table_column_path_clause_opt')[2]));
    }

    public function testClauseRefusesANodeThatIsNoClause(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (n FOR ORDINALITY, v integer PATH '\$.v', f jsonb FORMAT JSON, e boolean EXISTS, NESTED '\$.b' AS q COLUMNS (b text)))");
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\TableFunctionRule($lowering);
        $this->expectExceptionMessage('No semantic rule is implemented for: json_table:');
        $rule->clause(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonOrdinalityColumn(new \SqlSemantics\Statement\Identifier\Name('n')), $lowering->productions->form($tree->find('json_table')[0]));
    }
}
