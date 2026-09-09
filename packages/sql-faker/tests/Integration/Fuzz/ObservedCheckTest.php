<?php

declare(strict_types=1);

namespace Tests\Integration\SqlFaker\Fuzz;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\Verification\VerificationCoverage;
use SqlFaker\Coverage\Verification\VerificationResult;
use SqlFaker\Fuzz\Target\InfrastructureFailure;
use SqlFaker\Fuzz\Target\ObservedCheck;
use SqlFaker\Fuzz\Target\SyntaxCheck;
use SqlFaker\Fuzz\Target\SyntaxFailure;
use Tests\Fixtures\SqlFaker\VerificationFixture;

#[CoversClass(ObservedCheck::class)]
final class ObservedCheckTest extends TestCase
{
    public function testVerifyRecordsAcceptanceAndPassesTheOriginalInputAsHex(): void
    {
        $coverage = new VerificationCoverage(VerificationFixture::coverage(), 'oracle', []);
        $check = $this->createMock(SyntaxCheck::class);
        $check->expects(self::once())->method('verify')->with('SELECT 1', '00ff')->willReturn(new VerificationResult('accepted'));
        self::assertSame('accepted', (new ObservedCheck($check, $coverage))->verify('SELECT 1', "\0\xff")->status);
        self::assertSame(0.2, $coverage->snapshot()['acceptedRate']);
    }

    public function testVerifyRecordsFindingsBeforeRethrowingThem(): void
    {
        $coverage = new VerificationCoverage(VerificationFixture::coverage(), 'oracle', []);
        $check = $this->createMock(SyntaxCheck::class);
        $check->method('verify')->willThrowException(new SyntaxFailure('grammar rejection'));
        $failure = VerificationFixture::failure(static fn () => (new ObservedCheck($check, $coverage))->verify('SELECT 1', 'input'));
        self::assertInstanceOf(SyntaxFailure::class, $failure);
        self::assertSame('grammar rejection', $failure->getMessage());
        self::assertSame(0.0, $coverage->snapshot()['acceptedRate']);
        self::assertArrayHasKey('finding', $coverage->snapshot()['coverage']);
    }

    public function testVerifyRecordsInfrastructureSeparatelyAndPropagatesTheFailure(): void
    {
        $coverage = new VerificationCoverage(VerificationFixture::coverage(), 'oracle', []);
        $check = $this->createMock(SyntaxCheck::class);
        $check->method('verify')->willThrowException(new InfrastructureFailure('disconnected'));
        $failure = VerificationFixture::failure(static fn () => (new ObservedCheck($check, $coverage))->verify('SELECT 1', 'input'));
        self::assertInstanceOf(InfrastructureFailure::class, $failure);
        self::assertSame('disconnected', $failure->getMessage());
        self::assertSame(0.0, $coverage->snapshot()['acceptedRate']);
        self::assertArrayHasKey('infrastructure-failure', $coverage->snapshot()['coverage']);
    }
}
