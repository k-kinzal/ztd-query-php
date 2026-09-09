<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage\Verification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\Verification\VerificationEvent;

#[CoversClass(VerificationEvent::class)]
#[UsesClass(CoverageException::class)]
final class VerificationEventTest extends TestCase
{
    public function testKeySeparatesVerdictsAndFeatureKinds(): void
    {
        $event = new VerificationEvent('accepted', 'lexeme', 'shared', hash('sha256', 'input'), hash('sha256', 'SQL'), '');
        self::assertNotSame($event->key(), (new VerificationEvent('semantic-inconclusive', 'lexeme', 'shared', $event->inputHash, $event->sqlHash, ''))->key());
        self::assertNotSame($event->key(), (new VerificationEvent('accepted', 'compound', 'shared', $event->inputHash, $event->sqlHash, ''))->key());
        self::assertSame($event->key(), (new VerificationEvent('accepted', 'lexeme', 'shared', hash('sha256', 'other'), $event->sqlHash, ''))->key());
    }

    public function testToArrayRetainsTheFirstWitnessHashes(): void
    {
        $event = new VerificationEvent('accepted', 'lexeme', 'id', hash('sha256', 'input'), hash('sha256', 'SQL'), '');
        self::assertSame(['status' => 'accepted', 'kind' => 'lexeme', 'id' => 'id', 'inputHash' => $event->inputHash, 'sqlHash' => $event->sqlHash, 'code' => ''], $event->toArray());
    }

    public function testFromArrayRoundTripsACompleteWitness(): void
    {
        $event = new VerificationEvent('unsupported', 'production', 'id', hash('sha256', 'input'), hash('sha256', 'SQL'), '0A000');
        self::assertEquals($event, VerificationEvent::fromArray($event->toArray()));
    }

    #[DataProvider('providerCorruptWitnesses')]
    public function testFromArrayRejectsCorruptWitnesses(mixed $row): void
    {
        $this->expectException(CoverageException::class);
        VerificationEvent::fromArray($row);
    }

    /**
     * @return iterable<array{mixed}>
     */
    public static function providerCorruptWitnesses(): iterable
    {
        yield [null];
        yield [[]];
        $valid = (new VerificationEvent('accepted', 'lexeme', 'id', hash('sha256', 'input'), hash('sha256', 'SQL'), ''))->toArray();
        foreach (['status' => 'unknown', 'kind' => 'unknown', 'inputHash' => 'invalid', 'sqlHash' => 'invalid', 'id' => 1, 'code' => null] as $field => $value) {
            yield [[...$valid, $field => $value]];
        }
    }
}
