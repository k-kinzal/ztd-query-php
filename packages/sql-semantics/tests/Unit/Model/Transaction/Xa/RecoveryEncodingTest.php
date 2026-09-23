<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Transaction\Xa\RecoveryEncoding;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RecoveryEncoding::class)]
#[Medium]
final class RecoveryEncodingTest extends TestCase
{
    public function testBindsTheRequestToAnExplicitAlternative(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('XA RECOVER CONVERT XID');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\Xa\XaRecoverStatement::class, $statement);
        self::assertSame(RecoveryEncoding::Hexadecimal, $statement->encoding);
    }
}
