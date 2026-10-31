<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Statement\StatementSemantics::class)]
#[Medium]
final class StatementSemanticsTest extends TestCase
{
    public function testStatementConditionsPreserveOrderAndNoticeOwnership(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::Sqlite3472);
        $warning = self::createStub(\SqlSemantics\Statement\Fact\Warning::class);
        $other = self::createStub(\SqlSemantics\Statement\Fact\Warning::class);
        $condition = self::createStub(\SqlSemantics\Statement\Fact\Diagnostic::class);
        $subject = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1');
        $notice = new \SqlSemantics\Statement\Fact\Notice($warning, $subject);
        $information = new \SqlSemantics\Statement\StatementSemantics($profile, diagnostics: [$condition], warnings: [$notice, $other]);
        self::assertSame($profile, $information->profile);
        self::assertSame([$condition], $information->diagnostics);
        self::assertSame([$warning, $other], $information->warnings);
        self::assertSame($notice, $information->notices[0]);
        self::assertSame($other, $information->notices[1]->warning);
        self::assertNull($information->output);
        self::assertSame([], $information->declarations);
    }
}
