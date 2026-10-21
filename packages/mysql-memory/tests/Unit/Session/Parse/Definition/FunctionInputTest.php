<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Definition\FunctionInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(FunctionInput::class)]
#[Small]
final class FunctionInputTest extends TestCase
{
    #[TestWith(['5.6.51', 1305])]
    #[TestWith(['5.7.44', 1046])]
    public function testBeforeKeepsTheImplicitFunctionDatabaseAheadOfLaterSyntax(string $release, int $code): void
    {
        $session = (new Instance($release))->connect();
        $this->expectExceptionCode($code);
        $session->query('SHOW TABLE STATUS WHERE missing_fn() OVER w');
    }

    public function testBeforeLeavesQualifiedCallsAndSelectedDatabasesToTheirOwnStages(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $input = new FunctionInput();
        self::assertNull($input->before('SELECT d.missing_fn() OVER w', 99, $session));
        $session->query('CREATE DATABASE d; USE d');
        self::assertNull($input->before('SELECT missing_fn() OVER w', 99, $session));
    }
    public function testBeforeResolvesAnEnclosingStoredFunctionBeforeItsNestedSignature(): void
    {
        $this->expectExceptionCode(1046);
        (new Instance('5.7.44'))->connect()->query('SELECT ST_SWAPXY(ST_SRID(POINT(1,2),-1))');
    }

    public function testCompletedRequiresACompleteStatementBeforeTheRefusedTail(): void
    {
        $parser = new \SqlParser\MySql\MySqlParser('mysql-5.7.44');
        $input = new FunctionInput();
        self::assertSame(0, $input->completed($parser, 'SELECT missing_fn() +', 99));
        self::assertSame(0, $input->completed($parser, 'SELECT ABS(missing_fn(),1)', 99));
        self::assertGreaterThan(0, $input->completed($parser, 'SHOW TABLE STATUS WHERE missing_fn() OVER w', 99));
    }

    #[TestWith(['SELECT ASCII(missing_fn(),1)', 1064])]
    #[TestWith(['SELECT ABS(missing_fn(),1)', 1582])]
    #[TestWith(['SELECT missing_fn(),', 1064])]
    public function testBeforeKeepsUnfinishedAndEnclosingRefusalsAheadOfRoutineResolution(string $sql, int $code): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $this->expectExceptionCode($code);
        $session->query($sql);
    }

}
