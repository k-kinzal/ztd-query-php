<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Notice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

#[CoversClass(ParseFailure::class)]
#[Medium]
final class ParseFailureTest extends TestCase
{
    public function testAParseFailureCannotClaimToBeAResolutionNotice(): void
    {
        $this->expectExceptionMessage('A parse failure belongs to grammar interpretation or contextualization, not name resolution.');

        new ParseFailure(new UnknownCollation('nope'), phase: \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Resolution);
    }

    public function testMessageIsTheTextOfTheProblem(): void
    {
        self::assertSame("Unknown collation: 'nope'", (new ParseFailure(new UnknownCollation('nope')))->message());
    }

    public function testPhaseDefaultsToTheSyntaxPhase(): void
    {
        $failure = new ParseFailure(new UnknownCollation('nope'), false);

        self::assertFalse($failure->aborts);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax, $failure->phase);
        self::assertSame("Unknown collation: 'nope'", $failure->message());
    }
}
