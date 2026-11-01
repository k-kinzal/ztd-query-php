<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Routine;

use MySqlMemory\Instance;
use MySqlMemory\Program\Routine\Body;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Body::class)]
#[Small]
final class BodyTest extends TestCase
{
    public function testOfFindsTheParsedRoutineDeclaration(): void
    {
        $instance = new Instance(databases: ['d']);
        $instance->connect()->query('CREATE FUNCTION d.f(x INT) RETURNS INT DETERMINISTIC RETURN x + 1');
        $routine = $instance->dictionary->schemas['d']->functions['f'];

        self::assertSame($routine->statement, Body::of($routine));
        self::assertSame('f', Body::of($routine)->name->name->value);
    }
}
