<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\TableCreation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableCreation::class)]
#[Small]
final class TableCreationTest extends TestCase
{
    public function testCheckRefusesStatementsUntilTheTransactionEnds(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT) START TRANSACTION');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3977);
        $session->query('SELECT 1');
    }

    public function testCheckLeavesParseTimeErrorsAheadOfTheRestriction(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT) START TRANSACTION');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1582);
        $session->query('SELECT RAND(1,2)');
    }

    public function testRequestedRecognizesTheTableOption(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT) START TRANSACTION START TRANSACTION');
        self::assertTrue($session->transaction->creation->active);
    }

    public function testValidateRejectsNonAtomicEnginesBeforeDuplicateColumns(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3979);
        $this->expectExceptionMessage('START TRANSACTION clause cannot be used with engine that does not support atomic DDL.');
        $session->query('CREATE TABLE d.t(a INT,a INT) ENGINE=MEMORY START TRANSACTION');
    }

    public function testValidateRejectsForeignKeysBeforeLookingUpReferencedTables(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3978);
        $session->query('CREATE TABLE d.t(a INT, FOREIGN KEY(a) REFERENCES d.missing(a)) START TRANSACTION');
    }
}
