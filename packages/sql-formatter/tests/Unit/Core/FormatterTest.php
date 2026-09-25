<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\FormatOptions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\FormattingException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Style::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Keywords::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Document::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Visitor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Rules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Renderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Reductions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Grouping::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Spacing::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Shape::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Trivia::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Layout\Headers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Layout\Writer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Layout\Renderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Layout\Policy::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Layout\Spacing::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Layout\Block::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Layout\Elements::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Syntax\Headers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Syntax\Document::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Syntax\Rules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Syntax\Markers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Syntax\Analyzer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Syntax\Brackets::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Syntax\Fingerprint::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Syntax\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Syntax\Lists::class)]
final class FormatterTest extends \PHPUnit\Framework\TestCase
{
    public function testFormatUsesInjectedParserAndGrammarRoles(): void
    {
        $tree = new \SqlParser\Parser\Node('custom_statement', 0, [new \SqlParser\Lexer\Token(1, 'WORD', 'value', 0)]);
        $parser = $this->createMock(\SqlParser\Parser\SqlParser::class);
        $parser->expects(self::exactly(2))->method('parse')->with('value')->willReturn($tree);
        $dialect = $this->createMock(\SqlFormatter\Core\Dialect::class);
        $dialect->expects(self::once())->method('syntaxRules')->willReturn(new \SqlFormatter\Core\Syntax\Rules([], []));
        $dialect->expects(self::never())->method('compactRules');

        self::assertSame('value', (new \SqlFormatter\Core\Formatter($parser, $dialect))->format('value'));
    }
}
