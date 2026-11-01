<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Prepare;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Type\Dependent;

#[CoversClass(Prepare::class)]
#[Medium]
final class PrepareTest extends TestCase
{
    public function testDeriveStatementDerivesTheVariable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('PREPARE s FROM @q');
        self::assertInstanceOf(Prepare::class, $operation);
        self::assertInstanceOf(UserVariable::class, $operation->source);

        self::assertInstanceOf(Dependent::class, $operation->source->scalarInformation()->type);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerSourceConversions(): iterable
    {
        yield '5.6' => ['5.6.51', 1];
        yield '5.7' => ['5.7.44', 0];
        yield '8.4' => ['8.4.7', 0];
    }

    #[DataProvider('providerSourceConversions')]
    public function testDeriveStatementRetainsLiteralBytesAndVersionedSourceConversion(string $version, int $warnings): void
    {
        $sql = "PREPARE s FROM 'SELECT ''🦄'' AS value'";
        $statement = (new Semantics(Dialect::MySql, $version))->analyze($sql);
        self::assertSame($sql, $statement->toString());
        self::assertCount($warnings, $statement->warnings());
    }

    public function testRenderWritesTheSource(): void
    {
        self::assertSame("PREPARE s FROM 'SELECT ?'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("prepare s from 'SELECT ?'")->toString());
    }
    public function testRefusesRadixText(): void
    {
        $this->expectExceptionMessage('A PREPARE source is a quoted string or a user variable.');

        new Prepare(new \SqlSemantics\Statement\Identifier\Name('s'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('a', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }
}
