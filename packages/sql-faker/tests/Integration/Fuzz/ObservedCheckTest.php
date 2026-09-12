<?php

declare (strict_types=1);

namespace Tests\Integration\SqlFaker\Fuzz;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Coverage\GrammarCoverageInventory;
use SqlFaker\Coverage\Verification\VerificationCoverage;
use SqlFaker\Coverage\Verification\VerificationResult;
use SqlFaker\Fuzz\Target\InfrastructureFailure;
use SqlFaker\Fuzz\Target\ObservedCheck;
use SqlFaker\Fuzz\Target\SyntaxCheck;
use SqlFaker\Fuzz\Target\SyntaxFailure;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;

#[CoversClass(ObservedCheck::class)]
final class ObservedCheckTest extends TestCase
{
    public function testVerifyRecordsAcceptanceAndPassesTheOriginalInputAsHex(): void
    {
        $grammarCoverage = new GrammarCoverage(null);
        $grammarCoverage->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        $grammarCoverage->beginGeneration('stmt', []);
        $grammarCoverage->beginAttempt(0);
        $grammarCoverage->record(0, null, null, 'stmt', $grammarCoverage->inventory()->denominator[0], 'selected');
        $grammarCoverage->commitAttempt(hash('sha256', 'SELECT 1'));
        $grammarCoverage->endGeneration();
        $coverage = new VerificationCoverage($grammarCoverage, 'oracle', []);
        $check = $this->createMock(SyntaxCheck::class);
        $check->expects(self::once())->method('verify')->with('SELECT 1', '00ff')->willReturn(new VerificationResult('accepted'));
        self::assertSame('accepted', (new ObservedCheck($check, $coverage))->verify('SELECT 1', "\x00\xff")->status);
        self::assertSame(0.2, $coverage->snapshot()['acceptedRate']);
    }

    public function testVerifyRecordsFindingsBeforeRethrowingThem(): void
    {
        $grammarCoverage = new GrammarCoverage(null);
        $grammarCoverage->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        $grammarCoverage->beginGeneration('stmt', []);
        $grammarCoverage->beginAttempt(0);
        $grammarCoverage->record(0, null, null, 'stmt', $grammarCoverage->inventory()->denominator[0], 'selected');
        $grammarCoverage->commitAttempt(hash('sha256', 'SELECT 1'));
        $grammarCoverage->endGeneration();
        $coverage = new VerificationCoverage($grammarCoverage, 'oracle', []);
        $check = $this->createMock(SyntaxCheck::class);
        $check->method('verify')->willThrowException(new SyntaxFailure('grammar rejection'));
        $this->expectException(SyntaxFailure::class);
        $this->expectExceptionMessage('grammar rejection');
        try {
            (new ObservedCheck($check, $coverage))->verify('SELECT 1', 'input');
        } finally {
            self::assertSame(0.0, $coverage->snapshot()['acceptedRate']);
            self::assertArrayHasKey('finding', $coverage->snapshot()['coverage']);
        }
    }

    public function testVerifyRecordsInfrastructureSeparatelyAndPropagatesTheFailure(): void
    {
        $grammarCoverage = new GrammarCoverage(null);
        $grammarCoverage->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        $grammarCoverage->beginGeneration('stmt', []);
        $grammarCoverage->beginAttempt(0);
        $grammarCoverage->record(0, null, null, 'stmt', $grammarCoverage->inventory()->denominator[0], 'selected');
        $grammarCoverage->commitAttempt(hash('sha256', 'SELECT 1'));
        $grammarCoverage->endGeneration();
        $coverage = new VerificationCoverage($grammarCoverage, 'oracle', []);
        $check = $this->createMock(SyntaxCheck::class);
        $check->method('verify')->willThrowException(new InfrastructureFailure('disconnected'));
        $this->expectException(InfrastructureFailure::class);
        $this->expectExceptionMessage('disconnected');
        try {
            (new ObservedCheck($check, $coverage))->verify('SELECT 1', 'input');
        } finally {
            self::assertSame(0.0, $coverage->snapshot()['acceptedRate']);
            self::assertArrayHasKey('infrastructure-failure', $coverage->snapshot()['coverage']);
        }
    }
}
