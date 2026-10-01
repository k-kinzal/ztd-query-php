<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\AnalysisException::class)]
#[UsesClass(\SqlSemantics\Facade\Semantics::class)]
#[UsesClass(\SqlSemantics\Statement\Element::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class WriterTest extends TestCase
{
    public function testRenderPreservesOperandGroupingAfterAnImmutableUpdate(): void
    {
        $one = new \SqlSemantics\Statement\Model\Sqlite\Value\TermWithInteger_298801b2('1');
        $two = new \SqlSemantics\Statement\Model\Sqlite\Value\TermWithInteger_298801b2('2');
        $three = new \SqlSemantics\Statement\Model\Sqlite\Value\TermWithInteger_298801b2('3');
        $sum = new \SqlSemantics\Statement\Model\Sqlite\Value\ExprWithExprPlusMinusExpr_82e360dc($one, '+', $two);
        $original = new \SqlSemantics\Statement\Model\Sqlite\Value\ExprWithExprStarSlashRemExpr_6ca99fe8($one, '*', $three);
        $updated = $original->withExpr(new \SqlSemantics\Statement\Model\Sqlite\Value\ExprWithLpExprRp_ad646753($sum));
        self::assertSame('1 * 3', \SqlSemantics\Statement\Writer::render($original));
        self::assertSame('( 1 + 2 ) * 3', \SqlSemantics\Statement\Writer::render($updated));
    }

    public function testRenderWritesAFragmentWithoutTreatingItAsACompleteCommand(): void
    {
        $value = new \SqlSemantics\Statement\Model\Sqlite\Value\TermWithInteger_298801b2('42');
        self::assertSame('42', \SqlSemantics\Statement\Writer::render($value));
    }

    public function testAppendKeepsIdentifierAndKeywordDotBoundaries(): void
    {
        $identifier = new \SqlSemantics\Statement\Writer();
        $identifier->append('select', true);
        $identifier->append('.');
        $identifier->append('id', true);
        self::assertSame('select.id', $identifier->toString());
        $keyword = new \SqlSemantics\Statement\Writer();
        $keyword->append('DEFAULT');
        $keyword->append('.');
        $keyword->append('id', true);
        self::assertSame('DEFAULT .id', $keyword->toString());
    }

    public function testAppendKeepsFunctionAndIdentifierParenthesisBoundaries(): void
    {
        $function = new \SqlSemantics\Statement\Writer();
        $function->append('COUNT');
        $function->append('(');
        self::assertSame('COUNT(', $function->toString());
        $identifier = new \SqlSemantics\Statement\Writer();
        $identifier->append('count', true);
        $identifier->append('(');
        self::assertSame('count (', $identifier->toString());
    }

    public function testAppendSeparatesOperatorsWithoutCreatingAComment(): void
    {
        $writer = new \SqlSemantics\Statement\Writer();
        $writer->append('-');
        $writer->append('-');
        self::assertSame('- -', $writer->toString());
    }

    public function testToStringPreservesSeparateQuotedValues(): void
    {
        $writer = new \SqlSemantics\Statement\Writer();
        $writer->append("'one'");
        $writer->append("'two'");
        self::assertSame("'one' 'two'", $writer->toString());
    }

    public function testAppendAttachesTheNextValueToAPrefixButSeparatesOperators(): void
    {
        $variable = new \SqlSemantics\Statement\Writer();
        $variable->append('@', prefix: true);
        $variable->append('@', prefix: true);
        $variable->append('name', true);
        self::assertSame('@@name', $variable->toString());
        $operator = new \SqlSemantics\Statement\Writer();
        $operator->append('@');
        $operator->append('-');
        $operator->append('1');
        self::assertSame('@ - 1', $operator->toString());
    }

    public function testCommentsWritesEachCommentBeforeItsSymbolAndEndsALineComment(): void
    {
        $comments = new \SqlSemantics\Statement\Comments([0 => ['/* lead */'], 1 => ['-- one', '#two'], 2 => ['/* c */']]);
        $writer = new \SqlSemantics\Statement\Writer();
        $writer->comments($comments, 0);
        $writer->append('SELECT');
        $writer->comments($comments, 1);
        $writer->append('COUNT');
        $writer->comments($comments, 2);
        $writer->append('(');
        $writer->append('*');
        $writer->append(')');
        $writer->comments($comments, 3);
        self::assertSame("/* lead */ SELECT -- one\n#two\nCOUNT /* c */ ( * )", $writer->toString());
    }

    public function testRenderWritesTheCommentsOfEveryValue(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("SELECT /* a */ foo -- b\n FROM items /* c */ WHERE foo = /* d */ 1");
        self::assertSame("SELECT /* a */ foo -- b\nFROM items /* c */ WHERE foo = /* d */ 1", \SqlSemantics\Statement\Writer::render($statement->command));
    }
}
