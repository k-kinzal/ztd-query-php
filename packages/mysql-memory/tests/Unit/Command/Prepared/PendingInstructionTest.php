<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\PendingInstruction;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\Preparation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(PendingInstruction::class)]
#[Medium]
final class PendingInstructionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerInstructions(): iterable
    {
        yield 'routine revocation' => ['REVOKE ALL ON PROCEDURE * FROM u', 1144];
    }

    #[DataProvider('providerInstructions')]
    public function testParametersExposesThePreparedProtocolBinding(string $sql, int $code): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $definition = (new Preparation())->read($sql, $session);
        self::assertInstanceOf(PendingInstruction::class, $definition);
        self::assertSame([], $definition->parameters()->types);
    }

    #[DataProvider('providerInstructions')]
    public function testExecuteReachesTheRetainedConditionWithAnOpaqueLabel(string $sql, int $code): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $definition = (new Preparation())->read($sql, $session);
        self::assertInstanceOf(PendingInstruction::class, $definition);
        $this->expectExceptionCode($code);
        $definition->execute($session, 'not SQL', []);
    }

    #[DataProvider('providerInstructions')]
    public function testRunReturnsTheSameConditionThroughTheDiagnosticBoundary(string $sql, int $code): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $definition = (new Preparation())->read($sql, $session);
        self::assertInstanceOf(PendingInstruction::class, $definition);
        $replies = $definition->run($session, 'not SQL', []);
        self::assertInstanceOf(SqlError::class, $replies[0]);
        self::assertSame($code, $replies[0]->getCode());
        self::assertSame('not SQL', $session->text);
    }
}
