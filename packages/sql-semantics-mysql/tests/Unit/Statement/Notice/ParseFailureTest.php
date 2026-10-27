<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Notice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
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

    public function testMessageKeepsThePlaceOfTheProblemAmongTheWarnings(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT BINARY ('a' COLLATE nope)");

        self::assertInstanceOf(ParseFailure::class, $operation->warnings()[0]);
        self::assertFalse($operation->warnings()[0]->aborts);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax, $operation->warnings()[0]->phase);
        self::assertInstanceOf(Deprecation::class, $operation->warnings()[1]);
    }
}
