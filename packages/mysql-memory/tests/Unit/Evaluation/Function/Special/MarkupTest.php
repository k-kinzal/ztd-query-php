<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Special;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Special\Markup;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Markup::class)]
#[Small]
final class MarkupTest extends TestCase
{
    public function testRoutinesNamesTheXmlFunctions(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Markup())->routines());

        self::assertSame(['EXTRACTVALUE', 'UPDATEXML'], $names);
    }

    public function testPrepareRefusesAnXPathThatIsNotConstant(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Only constant XPATH queries are supported');

        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (p VARCHAR(10))');
        $session->query("SELECT EXTRACTVALUE('<a/>', p) FROM t");
    }

    public function testPrepareReadsTheXPathBeforeAnyRow(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("XPATH syntax error: ''");

        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (x VARCHAR(10))');
        $session->query("SELECT EXTRACTVALUE(x, '/a[') FROM t WHERE 0");
    }

    public function testEvaluateWarnsOfAFragmentThatDoesNotRead(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXTRACTVALUE('<a>c</a><b', '//a'), EXTRACTVALUE(NULL, '/a'), EXTRACTVALUE('<a/>', NULL)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[[null, null, null]], [['Warning', '1525', "Incorrect XML value: 'parse error at line 1 pos 11: END-OF-INPUT unexpected ('>' wanted)'"]]], [$result->rows, $warnings->rows]);
    }

    public function testExtractJoinsTheTextsOfTheSelectedNodes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXTRACTVALUE('<a>ccc<b>ddd</b></a>', '/a'), EXTRACTVALUE('<a>ccc<b>ddd</b><b>eee</b></a>', '//b'), EXTRACTVALUE('<a><b/></a>', 'count(/a/b)'), EXTRACTVALUE(_binary'<a>x</a>', '/a')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['ccc', 'ddd eee', '1', 'x']], $result->rows);
        self::assertSame([[Field::LongBlob, 268435456], [Field::LongBlob, 16777216]], [[$result->columns[0]->type, $result->columns[0]->length], [$result->columns[3]->type, $result->columns[3]->length]]);
    }

    public function testUpdateReplacesTheOneSelectedNode(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT UpdateXML('<a><b>ccc</b><d></d></a>', '/a', '<e>fff</e>'), UpdateXML('<a><b>ccc</b><d></d></a>', '/b', '<e>fff</e>'), UpdateXML('<a><b>ccc</b><d></d></a>', '//b', '<e>fff</e>'), UpdateXML('<a><d></d><b>ccc</b><d></d></a>', '/a/d', '<e>fff</e>'), UpdateXML('<a><b c=\"1\"/></a>', '/a/b/@c', 'x'), UpdateXML('<a/>', 'count(/a)', 'x')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['<e>fff</e>', '<a><b>ccc</b><d></d></a>', '<a><e>fff</e><d></d></a>', '<a><d></d><b>ccc</b><d></d></a>', '<a><b x/></a>', null]], $result->rows);
    }
}
