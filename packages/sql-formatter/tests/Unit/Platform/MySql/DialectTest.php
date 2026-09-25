<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlFormatter\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\FormatOptions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
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
final class DialectTest extends \PHPUnit\Framework\TestCase
{
    public function testSyntaxRulesDeclareClauseOwnership(): void
    {
        self::assertTrue((new \SqlFormatter\Platform\MySql\Dialect())->syntaxRules()->has('clauses', 'where_clause'));
        self::assertFalse((new \SqlFormatter\Platform\MySql\Dialect())->syntaxRules()->has('clauses', 'custom_rule'));
    }

    public function testCompactRulesNormalizeDeclaredTerminalAliases(): void
    {
        $settings = (new \SqlFormatter\Platform\MySql\Dialect())->compactRules();
        self::assertSame('RLIKE', $settings->keywords->text(new \SqlParser\Lexer\Token(1, 'REGEXP', '!=', 0), false));
    }
}
