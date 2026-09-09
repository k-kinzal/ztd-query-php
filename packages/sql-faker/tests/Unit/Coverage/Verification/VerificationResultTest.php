<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage\Verification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\Verification\VerificationResult;

#[CoversClass(VerificationResult::class)]
final class VerificationResultTest extends TestCase
{
    public function testRetainsAnInconclusiveDiagnosticWithoutCallingItAcceptance(): void
    {
        $result = new VerificationResult('semantic-inconclusive', '42P01', 'missing relation', 'parse_relation.c');
        self::assertSame('semantic-inconclusive', $result->status);
        self::assertSame('42P01', $result->code);
        self::assertSame('missing relation', $result->message);
        self::assertSame('parse_relation.c', $result->source);
    }
}
