<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\SqlWitness;

#[CoversClass(SqlWitness::class)]
final class SqlWitnessTest extends TestCase
{
    public function testConstructsReadonlySqlWitness(): void
    {
        $witness = new SqlWitness(
            'family.id',
            7,
            'SELECT 1',
            ['arity' => 3],
            ['row_arity' => 3],
        );

        self::assertSame('family.id', $witness->familyId);
        self::assertSame(7, $witness->seed);
        self::assertSame('SELECT 1', $witness->sql);
        self::assertSame(['arity' => 3], $witness->parameters);
        self::assertSame(['row_arity' => 3], $witness->properties);
    }
}
