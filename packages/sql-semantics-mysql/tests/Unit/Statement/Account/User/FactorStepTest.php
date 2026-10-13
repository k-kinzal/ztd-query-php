<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

#[CoversClass(FactorStep::class)]
#[Medium]
final class FactorStepTest extends TestCase
{
    /**
     * @return iterable<string, array{Numeral}>
     */
    public static function providerOtherNumberTokens(): iterable
    {
        yield 'fraction' => [new Numeral('1.5')];
        yield 'exponent' => [new Numeral('2e0')];
        yield 'hexadecimal' => [new Numeral('2', true)];
        yield 'long integer' => [new Numeral('2147483648')];
    }

    #[DataProvider('providerOtherNumberTokens')]
    public function testRejectsANumberOutsideTheFactorTokenDomain(Numeral $factor): void
    {
        $this->expectExceptionMessage('An authentication factor is a decimal integer token no greater than 2147483647.');

        new FactorStep($factor);
    }

    public function testRetainsAnIntegerWhoseValueTheStatementWillReject(): void
    {
        $factor = new Numeral('0');

        self::assertSame($factor, (new FactorStep($factor))->factor);
    }

    public function testRenderWritesTheFactorAndItsMethod(): void
    {
        self::assertSame('ALTER USER u ADD 2 FACTOR IDENTIFIED WITH authentication_fido', (new Semantics(Dialect::MySql))->analyze('alter user u add 2 factor identified with authentication_fido')->toString());
    }
}
