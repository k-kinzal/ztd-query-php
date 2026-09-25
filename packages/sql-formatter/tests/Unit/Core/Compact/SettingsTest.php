<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Compact;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\FormatOptions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\FormattingException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Style::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Keywords::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Document::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Visitor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Rules::class)]
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
final class SettingsTest extends \PHPUnit\Framework\TestCase
{
    public function testInjectedSeparatorPrecedesLexerProbing(): void
    {
        $parser = $this->createMock(\SqlParser\Parser\SqlParser::class);
        $parser->expects(self::never())->method('tokenize');
        $settings = new \SqlFormatter\Core\Compact\Settings(
            new \SqlFormatter\Core\Compact\Keywords([], [], []),
            new \SqlFormatter\Core\Compact\Rules([], [], []),
            new \SqlFormatter\Core\Compact\Grouping([]),
            [],
            static fn (string $text, int $start, int $offset, int $depth, bool $executable): bool => false,
            static fn (\SqlParser\Lexer\Token $left, \SqlParser\Lexer\Token $right, ?\SqlParser\Lexer\Token $before): string => ':',
        );
        $spacing = new \SqlFormatter\Core\Compact\Spacing($parser, $settings);

        self::assertSame(':', $spacing->between(new \SqlParser\Lexer\Token(1, 'WORD', 'a', 0), new \SqlParser\Lexer\Token(1, 'WORD', 'b', 1), null));
    }
}
