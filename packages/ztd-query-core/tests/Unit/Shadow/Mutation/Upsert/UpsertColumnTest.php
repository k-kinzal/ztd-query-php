<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertColumn;

#[CoversClass(UpsertColumn::class)]
#[UsesClass(UnsupportedSqlException::class)]
final class UpsertColumnTest extends TestCase
{
    public function testOfAnswersWhatTheRowCarriesUnderTheName(): void
    {
        self::assertSame(7, (new UpsertColumn())->of(['id' => 7], 'id'));
    }

    public function testOfDoesNotMindHowTheNameWasCased(): void
    {
        self::assertSame(7, (new UpsertColumn())->of(['ID' => 7], 'id'));
    }

    public function testOfAnswersANullTheRowActuallyCarries(): void
    {
        self::assertNull((new UpsertColumn())->of(['note' => null], 'note'));
    }

    public function testOfRefusesANameTheRowAnswersToInNoCasing(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new UpsertColumn())->of(['id' => 7], 'total');
    }

    public function testOfNamesTheColumnItRefused(): void
    {
        try {
            (new UpsertColumn())->of(['id' => 7], 'total');
        } catch (UnsupportedSqlException $refusal) {
            self::assertSame('unknown UPSERT column total', $refusal->getSql());

            return;
        }

        self::fail('Reading a column the row does not carry was expected to be refused.');
    }
}
