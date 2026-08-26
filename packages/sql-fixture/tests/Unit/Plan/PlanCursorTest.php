<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\PlanCursor;
use SqlFixture\Plan\PlanSyntaxException;

#[CoversClass(PlanCursor::class)]
#[UsesClass(PlanSyntaxException::class)]
final class PlanCursorTest extends TestCase
{
    public function testSourceAnswersTheStatementItWasGiven(): void
    {
        self::assertSame('order.id', (new PlanCursor('order.id'))->source());
    }

    public function testOffsetStartsAtTheBeginningAndMovesAsTheWalkDoes(): void
    {
        $cursor = new PlanCursor('order.id');
        self::assertSame(0, $cursor->offset());

        $cursor->takeIdentifier('a table name');

        self::assertSame(5, $cursor->offset());
    }

    public function testPeekAnswersTheNextCharacterWithoutMovingPastIt(): void
    {
        $cursor = new PlanCursor('order');

        self::assertSame('o', $cursor->peek());
        self::assertSame(0, $cursor->offset());
    }

    public function testPeekAnswersNothingAtTheEndOfTheStatement(): void
    {
        self::assertNull((new PlanCursor(''))->peek());
    }

    public function testAcceptMovesPastTheCharacterItWasLookingFor(): void
    {
        $cursor = new PlanCursor('.id');

        self::assertTrue($cursor->accept('.'));
        self::assertSame(1, $cursor->offset());
    }

    public function testAcceptLeavesTheWalkWhereItIsWhenTheCharacterIsSomethingElse(): void
    {
        $cursor = new PlanCursor('.id');

        self::assertFalse($cursor->accept(','));
        self::assertSame(0, $cursor->offset());
    }

    public function testSkipWhitespaceMovesPastARunOfIt(): void
    {
        $cursor = new PlanCursor("  \t id");
        $cursor->skipWhitespace();

        self::assertSame('i', $cursor->peek());
    }

    #[DataProvider('providerIdentifier')]
    public function testTakeIdentifierReadsAnIdentifierWithoutItsQuotes(string $written, string $expected): void
    {
        self::assertSame($expected, (new PlanCursor($written))->takeIdentifier('a table name'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerIdentifier(): iterable
    {
        yield 'bare' => ['order', 'order'];
        yield 'backquoted' => ['`order`', 'order'];
        yield 'double quoted' => ['"order"', 'order'];
        yield 'with a dollar' => ['order$2', 'order$2'];
    }

    public function testTakeIdentifierReportsTextThatIsNotOne(): void
    {
        $this->expectException(PlanSyntaxException::class);

        (new PlanCursor('.id'))->takeIdentifier('a table name');
    }

    public function testExpectEndAcceptsAStatementThatHasBeenReadToItsEnd(): void
    {
        $cursor = new PlanCursor('order');
        $cursor->takeIdentifier('a table name');
        $cursor->expectEnd();

        $this->expectNotToPerformAssertions();
    }

    public function testExpectEndRefusesAStatementCarryingMoreThanWasRead(): void
    {
        $cursor = new PlanCursor('order rest');
        $cursor->takeIdentifier('a table name');

        $this->expectException(PlanSyntaxException::class);

        $cursor->expectEnd();
    }

    public function testUnexpectedNamesThePlaceAndWhatWasWanted(): void
    {
        self::assertStringContainsString(
            "'.' after the table name",
            (new PlanCursor('order'))->unexpected("'.' after the table name")->getMessage(),
        );
    }
}
