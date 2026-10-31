<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\GenerationMetadata;
use MySqlMemory\Command\Prepared\PreparedStatement;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\Preparation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenerationMetadata::class)]
#[Medium]
final class GenerationMetadataTest extends TestCase
{
    public function testCaptureKeepsThePreparationDatabaseAndDestinationIdentity(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $operation = (new Preparation())->read('CREATE TABLE IF NOT EXISTS t(g INT AS (ROW(1,2)))', $session);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        (new Preparation())->check($operation, $session);
        $pending = PreparedStatement::capture($operation, $session);
        self::assertNotNull($pending->generation);
        self::assertSame('d', $pending->generation->database);
        self::assertSame($session->instance->dictionary->table('d', 't')?->definition, $pending->generation->destination);
    }

    public function testRefreshDoesNotAddADatabaseNoteBeforeAnyRepreparation(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $operation = (new Preparation())->read('CREATE TABLE missing.t(g INT AS (ROW(1,2)))', $session);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        (new Preparation())->check($operation, $session);
        $pending = PreparedStatement::capture($operation, $session);
        $reply = $pending->run($session, 'opaque request', [])[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply);
        self::assertSame(1049, $reply->getCode());
        self::assertSame([], array_values(array_filter($session->diagnostics->conditions, static fn (array $condition): bool => $condition[0] === 'Note')));
    }

    public function testCurrentFollowsTemporaryDestinationsInTheirPreparationDatabase(): void
    {
        $session = (new Instance('5.7.44', [], ['d', 'other']))->connect(database: 'd');
        $operation = (new Preparation())->read('CREATE TEMPORARY TABLE IF NOT EXISTS t(g INT AS (ROW(1,2)))', $session);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        (new Preparation())->check($operation, $session);
        $pending = PreparedStatement::capture($operation, $session);
        self::assertNotNull($pending->generation);
        self::assertNull($pending->generation->current($session));
        $session->query('CREATE TEMPORARY TABLE t(a INT); USE other');
        self::assertSame($session->temporaries->table('d', 't')?->definition, $pending->generation->current($session));
    }

    public function testRefreshClosesEverySessionOnlyAfterAnObservedRepreparation(): void
    {
        $instance = new Instance('5.7.44', [], ['d']);
        $session = $instance->connect(database: 'd');
        $other = $instance->connect(database: 'd');
        $operation = (new Preparation())->read('CREATE TABLE IF NOT EXISTS t(g INT AS (ROW(1,2)))', $session);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        (new Preparation())->check($operation, $session);
        $pending = PreparedStatement::capture($operation, $session);
        $header = $pending->statement;
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $pending->run($session, 'opaque request', [])[0]);
        self::assertFalse($session->released);
        $session->query('CREATE TABLE t(a INT)');
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $pending->run($session, 'opaque request', [])[0]);
        self::assertTrue($pending->generation?->refreshed);
        $session->query('DROP TABLE t');
        $reply = $pending->run($session, 'opaque request', [])[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply);
        self::assertSame(2006, $reply->getCode());
        self::assertTrue($session->released);
        self::assertTrue($other->released);
        self::assertSame($header, $pending->statement);
    }

    public function testRefreshRevalidatesBothCreatedAndRemovedModernDestinations(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = (new Preparation())->read('CREATE TABLE IF NOT EXISTS t(a INT,g INT AS (SUM(a)))', $session);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        (new Preparation())->check($operation, $session);
        $pending = PreparedStatement::capture($operation, $session);
        self::assertNotNull($pending->generation);
        $session->query('CREATE TABLE t(a INT)');
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $pending->run($session, 'opaque request', [])[0]);
        self::assertNotNull($pending->generation->destination);
        $session->query('DROP TABLE t');
        $reply = $pending->run($session, 'opaque request', [])[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply);
        self::assertSame(1111, $reply->getCode());
        self::assertNull($pending->generation->destination);
        self::assertFalse($session->released);
    }

    public function testDisconnectsKeepsGroupedGenerationErrorsLiveAfterDestinationInvalidation(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $operation = (new Preparation())->read('CREATE TABLE IF NOT EXISTS t(a INT,g INT AS (SUM(a)))', $session);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        (new Preparation())->check($operation, $session);
        $pending = PreparedStatement::capture($operation, $session);
        self::assertNotNull($pending->generation);
        self::assertFalse($pending->generation->disconnects());
        $pending->generation->sqlPrepared = true;
        self::assertTrue($pending->generation->disconnects());
        $pending->generation->sqlPrepared = false;
        $session->query('CREATE TABLE t(a INT)');
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $pending->run($session, 'opaque request', [])[0]);
        $session->query('DROP TABLE t');
        $reply = $pending->run($session, 'opaque request', [])[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply);
        self::assertSame(1111, $reply->getCode());
        self::assertFalse($session->released);
    }

    public function testDisconnectsLeavesBareSystemVariablesAsFileConditions(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $operation = (new Preparation())->read('CREATE TABLE IF NOT EXISTS t(g INT AS (@@version))', $session);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        (new Preparation())->check($operation, $session);
        $pending = PreparedStatement::capture($operation, $session);
        self::assertNotNull($pending->generation);
        self::assertFalse($pending->generation->disconnects());
    }
}
