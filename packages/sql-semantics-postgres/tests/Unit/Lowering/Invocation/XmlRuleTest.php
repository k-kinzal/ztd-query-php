<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Invocation\SyntaxRule;
use SqlSemantics\Platform\PostgreSql\Lowering\Invocation\XmlRule;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlIndent;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(XmlRule::class)]
#[Small]
final class XmlRuleTest extends TestCase
{
    public function testExpressionLowersEveryXmlFunction(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT XMLELEMENT(NAME a, XMLATTRIBUTES(1 AS b), 2), XMLELEMENT(NAME a, 1), XMLPARSE(CONTENT \'x\' PRESERVE WHITESPACE), XMLPI(NAME p), XMLSERIALIZE(DOCUMENT \'x\' AS text INDENT)');
        $node = array_map(static fn ($target) => (new SyntaxRule($lowering))->subexpression($target->find('func_expr_common_subexpr')[0]), $tree->find('target_list')[0]->find('target_el'));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->list($node);
        self::assertSame('XMLELEMENT(NAME a, XMLATTRIBUTES(1 AS b), 2), XMLELEMENT(NAME a, 1), XMLPARSE(CONTENT \'x\' PRESERVE WHITESPACE), XMLPI(NAME p), XMLSERIALIZE(DOCUMENT \'x\' AS text INDENT)', (new Lexical())->join($out->pieces()));
    }

    public function testRootLowersTheVersionAndStandalone(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT XMLROOT(\'x\', VERSION \'1.0\', STANDALONE YES)');
        $node = (new SyntaxRule($lowering))->subexpression($tree->find('func_expr_common_subexpr')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('XMLROOT(\'x\', VERSION \'1.0\', STANDALONE YES)', (new Lexical())->join($out->pieces()));
    }

    public function testOptionAnswersTheTableEntry(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT XMLSERIALIZE(CONTENT 1 AS text INDENT)');
        self::assertSame(XmlIndent::Indent, (new XmlRule($lowering))->option(['xml_indent_option: INDENT' => XmlIndent::Indent], $tree->find('xml_indent_option')[0]));
    }

    public function testWrappedLowersXmlAttributes(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT XMLELEMENT(NAME a, XMLATTRIBUTES(1 AS b, 2 AS c))');
        $node = (new XmlRule($lowering))->wrapped($tree->find('xml_attributes')[0]);
        self::assertCount(2, $node);
    }

    public function testAttributesLowersUnnamedValues(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT XMLFOREST(a)');
        $node = (new XmlRule($lowering))->attributes($tree->find('xml_attribute_list')[0]);
        self::assertNull($node[0]->name);
    }

    public function testPassingDropsThePassingMechanism(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT XMLEXISTS(\'x\' PASSING BY REF \'y\')');
        $node = (new XmlRule($lowering))->passing($tree->find('xmlexists_argument')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('PASSING \'y\'', (new Lexical())->join($out->pieces()));
    }
}
