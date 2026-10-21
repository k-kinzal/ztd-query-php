<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Notice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Notice\OperandCompletion;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

#[CoversClass(OperandCompletion::class)]
#[Medium]
final class OperandCompletionTest extends TestCase
{
    public function testStartingKeepsACompletedViewClauseBeforeItsBody(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql910);
        $parser = new \SqlParser\MySql\MySqlParser('mysql-9.1.0');
        $notices = (new SyntaxWarnings())->completed($parser, 'ALTER VIEW IF NOT EXISTS v AS SELECT (', 100, $profile);
        self::assertSame(26, $notices[0][0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $notices[0][1]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedExistenceClause::class, $notices[0][1]->problem);
        self::assertSame([], (new SyntaxWarnings())->completed($parser, 'ALTER VIEW IF NOT EXISTS', 100, $profile));
    }

    public function testCompletedKeepsTheCharsetNoticeBeforeAnIncompleteCollation(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $parser = new \SqlParser\MySql\MySqlParser($semantics->profile()->grammar->value);
        $notices = (new SyntaxWarnings())->completed($parser, 'SET NAMES utf8 COLLATE ?', 100, $semantics->profile());
        self::assertCount(1, $notices);
        self::assertSame(strlen('SET NAMES utf8'), $notices[0][0]);
        self::assertSame(Deprecated::Utf8Alias, $notices[0][1]);
    }
}
