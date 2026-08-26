<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Exception\SimulationException;

#[CoversClass(InvalidDefinitionException::class)]
final class InvalidDefinitionExceptionTest extends TestCase
{
    public function testIsSomethingACallerSimulatingAStatementCanCatch(): void
    {
        self::assertContains(
            SimulationException::class,
            class_parents(new InvalidDefinitionException('bad')),
        );
    }

    public function testCarriesWhatWasWrongWithTheDefinition(): void
    {
        self::assertSame('A lexical delimiter must not be empty.', (new InvalidDefinitionException(
            'A lexical delimiter must not be empty.',
        ))->getMessage());
    }
}
