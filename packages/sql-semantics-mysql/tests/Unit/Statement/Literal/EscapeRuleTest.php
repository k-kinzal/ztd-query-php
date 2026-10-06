<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(EscapeRule::class)]
#[Medium]
final class EscapeRuleTest extends TestCase
{
    public function testCasesNameBothRules(): void
    {
        self::assertSame(['Backslash', 'Verbatim'], array_column(EscapeRule::cases(), 'name'));
    }

    public function testUnderAnswersVerbatimWhenNoBackslashEscapesIsSet(): void
    {
        self::assertSame(EscapeRule::Verbatim, EscapeRule::under(new LexicalSettings('NO_BACKSLASH_ESCAPES')));
    }

    public function testUnderAnswersBackslashOtherwise(): void
    {
        self::assertSame(EscapeRule::Backslash, EscapeRule::under(new LexicalSettings('')));
        self::assertSame(EscapeRule::Backslash, EscapeRule::under(new LexicalSettings('ANSI_QUOTES')));
    }

    public function testUnderFollowsTheProfileOfAnAnalysis(): void
    {
        $verbatim = (new Semantics(Dialect::MySql, null, Mode::fromString('NO_BACKSLASH_ESCAPES')))->analyze("SELECT 'a\\nb'");
        $backslash = (new Semantics(Dialect::MySql))->analyze("SELECT 'a\\nb'");
        $verbatimSelect = $verbatim->statement;
        $backslashSelect = $backslash->statement;
        self::assertInstanceOf(Select::class, $verbatimSelect);
        self::assertInstanceOf(Select::class, $backslashSelect);
        $verbatimItem = $verbatimSelect->items[0];
        self::assertInstanceOf(SelectExpression::class, $verbatimItem);
        $backslashItem = $backslashSelect->items[0];
        self::assertInstanceOf(SelectExpression::class, $backslashItem);
        $verbatimLiteral = $verbatimItem->expression;
        $backslashLiteral = $backslashItem->expression;
        self::assertInstanceOf(StringLiteral::class, $verbatimLiteral);
        self::assertInstanceOf(StringLiteral::class, $backslashLiteral);

        self::assertSame(EscapeRule::Verbatim, $verbatimLiteral->escapes);
        self::assertSame('a\\nb', $verbatimLiteral->value());
        self::assertSame(EscapeRule::Backslash, $backslashLiteral->escapes);
        self::assertSame("a\nb", $backslashLiteral->value());
    }
}
