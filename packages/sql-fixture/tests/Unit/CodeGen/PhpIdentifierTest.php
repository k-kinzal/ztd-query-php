<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\CodeGen\PhpIdentifier;

#[CoversClass(PhpIdentifier::class)]
final class PhpIdentifierTest extends TestCase
{
    #[Test]
    #[DataProvider('providerClassNames')]
    public function classNames(string $table, string $expected): void
    {
        self::assertSame($expected, (new PhpIdentifier())->className($table));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerClassNames(): array
    {
        return [
            'single word' => ['order', 'Order'],
            'snake case' => ['order_detail', 'OrderDetail'],
            'prefixed' => ['m_customer', 'MCustomer'],
            'already camel' => ['orderDetail', 'OrderDetail'],
            'plural is left alone' => ['statuses', 'Statuses'],
            'reserved word' => ['class', 'ClassTable'],
            'reserved type name' => ['string', 'StringTable'],
            'leading digit' => ['2fa_token', '_2faToken'],
        ];
    }

    #[Test]
    #[DataProvider('providerParameterNames')]
    public function parameterNames(string $column, string $expected): void
    {
        self::assertSame($expected, (new PhpIdentifier())->parameterName($column));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerParameterNames(): array
    {
        return [
            'single word' => ['id', 'id'],
            'snake case' => ['order_id', 'orderId'],
            'several words' => ['created_at_utc', 'createdAtUtc'],
            'reserved word' => ['list', 'listValue'],
            'leading digit' => ['2fa', '_2fa'],
        ];
    }

    #[Test]
    #[DataProvider('providerConstantNames')]
    public function constantNames(string $column, string $expected): void
    {
        self::assertSame($expected, (new PhpIdentifier())->constantName($column));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerConstantNames(): array
    {
        return [
            'single word' => ['id', 'ID'],
            'snake case' => ['order_id', 'ORDER_ID'],
            'camel case' => ['orderId', 'ORDER_ID'],
            'leading digit' => ['2fa', '_2FA'],
        ];
    }

    #[Test]
    public function anEmptyNameStillYieldsAnIdentifier(): void
    {
        $identifier = new PhpIdentifier();

        self::assertSame('Column', $identifier->className('!!'));
        self::assertSame('column', $identifier->parameterName('!!'));
    }
}
