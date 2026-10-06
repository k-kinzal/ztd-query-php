<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Lowering\Utility\CommandRule;
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
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['NOTIFY c', 'LISTEN c', 'UNLISTEN c', 'UNLISTEN *', "LOAD 'x'"],
            [$semantics->analyze('NOTIFY c')->toString(), $semantics->analyze('LISTEN c')->toString(), $semantics->analyze('UNLISTEN c')->toString(), $semantics->analyze('UNLISTEN *')->toString(), $semantics->analyze("LOAD 'x'")->toString()],
        );
    }

    public function testCallLowersTheProcedureCall(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze('CALL s.p(VARIADIC ARRAY[1])')->statement;
        self::assertInstanceOf(Call::class, $statement);
        self::assertSame('p', $statement->call->name->last()->value);
    }

    public function testItemsKeepsTheOrderWritten(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze("DO LANGUAGE sql 'a' LANGUAGE plpgsql")->statement;
        self::assertInstanceOf(DoBlock::class, $statement);
        self::assertSame([DoLanguage::class, \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant::class, DoLanguage::class], array_map(static fn (object $item): string => $item::class, $statement->items));
    }

    public function testPayloadIsNullWithoutAPayload(): void
    {
        $with = (new Semantics(Dialect::PostgreSql))->analyze("NOTIFY c, 'p'")->statement;
        $without = (new Semantics(Dialect::PostgreSql))->analyze('NOTIFY c')->statement;
        self::assertInstanceOf(Notify::class, $with);
        self::assertInstanceOf(Notify::class, $without);
        self::assertSame(['p', null], [$with->payload?->value, $without->payload]);
    }
}
