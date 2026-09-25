<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Dialect;

#[CoversClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\AnalysisException::class)]
#[UsesClass(\SqlSemantics\Facade\Semantics::class)]
#[UsesClass(\SqlSemantics\Statement\Element::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(Dialect::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class WriterTest extends TestCase
{
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
}
