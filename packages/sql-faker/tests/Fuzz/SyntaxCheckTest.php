<?php

declare(strict_types=1);

namespace Tests\Integration\SqlFaker;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Fuzz\Target\PgSyntaxCheck;
use SqlFaker\Fuzz\Target\SqliteSyntaxCheck;
use SqlFaker\Fuzz\Target\SyntaxFailure;
use SqlFaker\Fuzz\Target\VerificationResult;

#[CoversNothing]
final class SyntaxCheckTest extends TestCase
{
    public function testIncompleteInputIsAFinding(): void
    {
        $check = new SqliteSyntaxCheck();
        $this->expectException(SyntaxFailure::class);
        $this->expectExceptionMessage('incomplete input');
        $check->verify('SELECT (', '00');
    }

    public function testSyntaxErrorsAreNotHiddenByAnAllowlist(): void
    {
        $check = new SqliteSyntaxCheck();
        $this->expectException(SyntaxFailure::class);
        $check->verify('CREATE VIEW name(name ASC) AS VALUES(1)', '01');
    }

    public function testPreparationLeavesNoSchemaStateForSubsequentInputs(): void
    {
        $check = new SqliteSyntaxCheck();
        self::assertSame(VerificationResult::Accepted, $check->verify('CREATE TABLE t(id INTEGER)', '00'));
        self::assertSame(VerificationResult::Rejected, $check->verify('SELECT * FROM t', '01'));
        self::assertSame(VerificationResult::Accepted, $check->verify('CREATE TABLE t(id INTEGER)', '00'));
    }

    public function testPostgreSqlParenthesisSyntaxErrorIsAFinding(): void
    {
        $this->expectException(SyntaxFailure::class);
        $this->expectExceptionMessage('42601');
        PgSyntaxCheck::rejection('42601', 'syntax error at or near ")"', 'SELECT ()', '00');
    }

    public function testPostgreSqlStateAndUnsupportedVerificationRemainDistinct(): void
    {
        self::assertSame(VerificationResult::Rejected, PgSyntaxCheck::rejection('42P01', 'missing table', 'SELECT * FROM missing', '00'));
        self::assertSame(VerificationResult::Incomplete, PgSyntaxCheck::rejection('0A000', 'unsupported', 'SELECT 1', '01'));
    }
}
