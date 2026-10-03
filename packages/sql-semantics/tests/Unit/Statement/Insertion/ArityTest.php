<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Insertion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Insertion\Arity;

#[CoversClass(Arity::class)]
#[Small]
final class ArityTest extends TestCase
{
    public function testCasesDistinguishMissingInformationFromKnownContradictions(): void
    {
        self::assertNotSame(Arity::MissingDeclaration, Arity::MissingTable);
        self::assertNotSame(Arity::Mismatch, Arity::ConflictingDeclarations);
        self::assertSame('matching', Arity::Matching->value);
    }
}
