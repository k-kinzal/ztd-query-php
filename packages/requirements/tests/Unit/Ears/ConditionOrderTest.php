<?php

declare(strict_types=1);

namespace Tests\Unit\Ears;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Ears\ConditionOrder;
use Requirements\Ears\Wording;
use Requirements\Input\InvalidInputException;

#[CoversClass(ConditionOrder::class)]
#[UsesClass(Wording::class)]
#[Small]
final class ConditionOrderTest extends TestCase
{
    /**
     * @param list<string> $clauses
     */
    #[DataProvider('providerAcceptTakesClausesInOrder')]
    public function testAcceptTakesClausesInOrder(array $clauses, ?string $trigger): void
    {
        $order = new ConditionOrder();
        array_map($order->accept(...), $clauses);
        self::assertSame($trigger, $order->trigger());
    }

    /**
     * @return array<string, array{list<string>, ?string}>
     */
    public static function providerAcceptTakesClausesInOrder(): array
    {
        return [
            'none' => [[], null],
            'where' => [['Where tracing is enabled'], null],
            'while' => [['While recording is enabled'], null],
            'two whiles' => [['While recording is enabled', 'while input is available'], null],
            'when' => [['When a token is read'], 'when'],
            'if' => [['If input is invalid'], 'if'],
            'uppercase keyword' => [['IF input is invalid'], 'if'],
            'mixed case keyword' => [['wHeN input ends'], 'when'],
            'surrounding space' => [["  When input ends \n"], 'when'],
            'full order' => [['Where tracing is enabled', 'while recording is enabled', 'when a token is read'], 'when'],
            'where then if' => [['Where tracing is enabled', 'If input is invalid'], 'if'],
            'multiline text' => [["When a token\nis read"], 'when'],
            'word containing shall' => [['When the shallow copy ends'], 'when'],
            'word containing then' => [['When the thenar is read'], 'when'],
        ];
    }

    public function testTriggerIsNullBeforeAnyClause(): void
    {
        self::assertNull((new ConditionOrder())->trigger());
    }

    public function testTriggerIsKeptAfterPrecedingClauses(): void
    {
        $order = new ConditionOrder();
        $order->accept('While recording is enabled');
        self::assertNull($order->trigger());
        $order->accept('If input is invalid');
        self::assertSame('if', $order->trigger());
    }

    #[DataProvider('providerAcceptRejectsMalformedClauses')]
    public function testAcceptRejectsMalformedClauses(string $clause): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS: each condition needs Where, While, When or If and nonempty text.');
        (new ConditionOrder())->accept($clause);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerAcceptRejectsMalformedClauses(): array
    {
        return [
            'unknown keyword' => ['Unless input ends'],
            'keyword prefix' => ['Whenever input ends'],
            'keyword only' => ['When'],
            'no space after keyword' => ['When,input ends'],
            'keyword not first' => ['Then when input ends'],
            'punctuation only' => ['When ...'],
            'empty' => [''],
        ];
    }

    /**
     * @param list<string> $accepted
     */
    #[DataProvider('providerAcceptRejectsClausesOutOfOrder')]
    public function testAcceptRejectsClausesOutOfOrder(array $accepted, string $clause): void
    {
        $order = new ConditionOrder();
        array_map($order->accept(...), $accepted);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS: use optional feature, preconditions, then at most one trigger (When or If), in that order.');
        $order->accept($clause);
    }

    /**
     * @return array<string, array{list<string>, string}>
     */
    public static function providerAcceptRejectsClausesOutOfOrder(): array
    {
        return [
            'where after while' => [['While recording is enabled'], 'Where tracing is enabled'],
            'second where' => [['Where tracing is enabled'], 'Where logging is enabled'],
            'while after when' => [['When input ends'], 'While recording is enabled'],
            'where after if' => [['If input is invalid'], 'Where tracing is enabled'],
            'second when' => [['When input ends'], 'When a token is read'],
            'if after when' => [['When input ends'], 'If input is invalid'],
            'second if' => [['If input ends'], 'If input is invalid'],
        ];
    }

    #[DataProvider('providerAcceptRejectsShallOrThenInConditions')]
    public function testAcceptRejectsShallOrThenInConditions(string $clause): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS: shall belongs in the response; then must introduce the system clause after If.');
        (new ConditionOrder())->accept($clause);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerAcceptRejectsShallOrThenInConditions(): array
    {
        return [
            'shall' => ['When the reader shall stop'],
            'then' => ['If input is invalid then the reader'],
            'uppercase then' => ['If input is invalid THEN the reader'],
            'where with shall' => ['Where the tracer SHALL run'],
        ];
    }
}
