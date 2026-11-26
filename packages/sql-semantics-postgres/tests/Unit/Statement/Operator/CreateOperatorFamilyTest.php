<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\CreateOperatorFamily;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(CreateOperatorFamily::class)]
#[Medium]
final class CreateOperatorFamilyTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Inspection())->inspect('CREATE OPERATOR FAMILY f USING btree', []);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheMethod(): void
    {
        $operation = (new Inspection())->inspect('create operator family s.f using "Hash"');
        self::assertSame('CREATE OPERATOR FAMILY s.f USING "Hash"', $operation->toString());
    }
}
