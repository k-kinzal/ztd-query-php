<?php

declare(strict_types=1);

namespace Tests\Unit\Driver\Placeholder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Driver\Placeholder\QuotedInput::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Driver\Placeholder\EscapeCursor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Driver\Placeholder\TokenBoundary::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\Lexing\CommentSpan::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\Lexing\QuotedSpan::class)]
final class QuotedInputTest extends TestCase
{
    public function testConsumeIgnoredPreservesNestedCommentsAndContext(): void
    {
        $input = new \ZtdQuery\Platform\Postgres\Driver\Placeholder\QuotedInput();
        $cursor = new \ZtdQuery\Platform\Postgres\Driver\Placeholder\EscapeCursor('/* ? /* nested */ ? */');
        self::assertTrue($input->consumeIgnored($cursor));
        self::assertSame($cursor->sql, $cursor->result);
        self::assertSame(strlen($cursor->sql), $cursor->position);
        self::assertTrue($cursor->expectsOperand);
        self::assertFalse($input->consumeIgnored(new \ZtdQuery\Platform\Postgres\Driver\Placeholder\EscapeCursor('x')));
    }

    public function testConsumeQuotedPreservesLiteralQuestionMarks(): void
    {
        $input = new \ZtdQuery\Platform\Postgres\Driver\Placeholder\QuotedInput();
        $cursor = new \ZtdQuery\Platform\Postgres\Driver\Placeholder\EscapeCursor("'a''?b' tail");
        self::assertTrue($input->consumeQuoted($cursor));
        self::assertSame("'a''?b'", $cursor->result);
        self::assertFalse($cursor->expectsOperand);
        $dollar = new \ZtdQuery\Platform\Postgres\Driver\Placeholder\EscapeCursor('$tag$?$tag$');
        self::assertTrue($input->consumeQuoted($dollar));
        self::assertSame('$tag$?$tag$', $dollar->result);
        self::assertFalse($input->consumeQuoted(new \ZtdQuery\Platform\Postgres\Driver\Placeholder\EscapeCursor('$1')));
    }
}
