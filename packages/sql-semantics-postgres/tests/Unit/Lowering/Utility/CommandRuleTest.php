<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Lowering\Utility\CommandRule;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Call;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\DoBlock;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\DoLanguage;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Notify;

#[CoversClass(CommandRule::class)]
#[Medium]
final class CommandRuleTest extends TestCase
{
    public function testStatementLowersEachCommand(): void
    {
        $semantics = new Semantics();
        self::assertSame(
            ['NOTIFY c', 'LISTEN c', 'UNLISTEN c', 'UNLISTEN *', "LOAD 'x'"],
            [$semantics->inspect('NOTIFY c')->toString(), $semantics->inspect('LISTEN c')->toString(), $semantics->inspect('UNLISTEN c')->toString(), $semantics->inspect('UNLISTEN *')->toString(), $semantics->inspect("LOAD 'x'")->toString()],
        );
    }

    public function testCallLowersTheProcedureCall(): void
    {
        $statement = (new Semantics())->inspect('CALL s.p(VARIADIC ARRAY[1])')->statement;
        self::assertInstanceOf(Call::class, $statement);
        self::assertSame('p', $statement->call->name->last()->value);
    }

    public function testItemsKeepsTheOrderWritten(): void
    {
        $statement = (new Semantics())->inspect("DO LANGUAGE sql 'a' LANGUAGE plpgsql")->statement;
        self::assertInstanceOf(DoBlock::class, $statement);
        self::assertSame([DoLanguage::class, \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant::class, DoLanguage::class], array_map(static fn (object $item): string => $item::class, $statement->items));
    }

    public function testPayloadIsNullWithoutAPayload(): void
    {
        $with = (new Semantics())->inspect("NOTIFY c, 'p'")->statement;
        $without = (new Semantics())->inspect('NOTIFY c')->statement;
        self::assertInstanceOf(Notify::class, $with);
        self::assertInstanceOf(Notify::class, $without);
        self::assertSame(['p', null], [$with->payload?->value, $without->payload]);
    }
}
