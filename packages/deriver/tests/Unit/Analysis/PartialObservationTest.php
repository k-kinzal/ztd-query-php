<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class PartialObservationTest extends TestCase
{
    public function testRecoverDoesNotInventMutableState(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $partial = new \Deriver\Analysis\PartialObservation($context->program, 'TIME_LIMIT');
        self::assertNull($partial->recover(new \Deriver\Query\ReturnQuery('target')));
    }

    public function testValueBoundsUnknownDependencies(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $partial = new \Deriver\Analysis\PartialObservation($context->program, 'TIME_LIMIT');
        self::assertSame('TIME_LIMIT', $partial->value('absent')->literal);
        self::assertFalse($partial->value('absent')->isConcrete());
    }
    public function testReadKeepsUnknownStorageUntyped(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $partial = new \Deriver\Analysis\PartialObservation($context->program, 'TIME_LIMIT');
        $instruction = new \Deriver\ControlFlow\Instruction('read', 'read', new \Deriver\Reference\SourceRef('test', 'a.php', 0, 1), 'value', ['missing']);
        self::assertSame('mixed', $partial->read($instruction)->attributes['type']);
        self::assertSame('TIME_LIMIT', $partial->read($instruction)->literal);
    }

}
