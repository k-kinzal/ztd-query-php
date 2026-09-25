<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

#[\PHPUnit\Framework\Attributes\CoversNothing]
final class DialectTest extends \PHPUnit\Framework\TestCase
{
    public function testCompactRulesCanProvideAnIndependentGrammar(): void
    {
        $tree = new \SqlParser\Parser\Node('custom_statement', 0, [new \SqlParser\Lexer\Token(1, 'WORD', 'value', 0)]);
        $parser = $this->createMock(\SqlParser\Parser\SqlParser::class);
        $parser->expects(self::exactly(2))->method('parse')->willReturn($tree);
        $dialect = $this->createMock(\SqlFormatter\Core\Dialect::class);
        $dialect->expects(self::never())->method('syntaxRules');
        $dialect->expects(self::once())->method('compactRules')->willReturn(new \SqlFormatter\Core\Compact\Settings(
            new \SqlFormatter\Core\Compact\Keywords([], [], []),
            new \SqlFormatter\Core\Compact\Rules([], [], []),
            new \SqlFormatter\Core\Compact\Grouping([]),
            [],
            static fn (string $text, int $start, int $offset, int $depth, bool $executable): bool => false,
            static fn (\SqlParser\Lexer\Token $left, \SqlParser\Lexer\Token $right, ?\SqlParser\Lexer\Token $before): string => ':',
        ));
        $formatter = new \SqlFormatter\Core\Formatter($parser, $dialect, new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));

        self::assertSame('VALUE', $formatter->format('value'));
    }
    public function testSyntaxRulesCanProvideAnIndependentGrammar(): void
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
