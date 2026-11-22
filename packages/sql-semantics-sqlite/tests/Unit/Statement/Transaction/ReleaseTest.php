<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Release;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Release::class)]
#[Medium]
final class ReleaseTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        $operation = (new Inspection())->inspect('RELEASE s1', []);

        self::assertNull($operation->shape());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderDropsTheOptionalKeyword(): void
    {
        $operation = (new Inspection())->inspect('release savepoint s1');

        self::assertInstanceOf(Release::class, $operation->statement);
        self::assertSame('s1', $operation->statement->name->value);
        self::assertSame('RELEASE s1', $operation->toString());
    }
}
