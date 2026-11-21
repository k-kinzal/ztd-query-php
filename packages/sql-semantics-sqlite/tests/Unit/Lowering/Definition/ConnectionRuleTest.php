<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\ConnectionRule;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Connection\Attach;
use SqlSemantics\Platform\Sqlite\Statement\Connection\Detach;
use SqlSemantics\Platform\Sqlite\Statement\Connection\NameOperand;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;

#[CoversClass(ConnectionRule::class)]
#[Medium]
final class ConnectionRuleTest extends TestCase
{
    public function testCommandLowersAttachAndDetach(): void
    {
        $semantics = new Semantics();

        self::assertInstanceOf(Attach::class, $semantics->inspect("ATTACH 'f' AS a")->statement);
        self::assertInstanceOf(Detach::class, $semantics->inspect('DETACH a')->statement);
    }

    public function testKeywordIsOptionalBeforeTheOperand(): void
    {
        $semantics = new Semantics();
        $short = $semantics->inspect('DETACH a')->statement;
        $long = $semantics->inspect('DETACH DATABASE a')->statement;

        self::assertInstanceOf(Detach::class, $short);
        self::assertInstanceOf(Detach::class, $long);
        self::assertInstanceOf(NameOperand::class, $short->schema);
        self::assertInstanceOf(NameOperand::class, $long->schema);
    }

    public function testKeyIsNullUnlessAKeyIsWritten(): void
    {
        $semantics = new Semantics();
        $plain = $semantics->inspect("ATTACH 'f' AS a")->statement;
        $keyed = $semantics->inspect("ATTACH 'f' AS a KEY secret")->statement;

        self::assertInstanceOf(Attach::class, $plain);
        self::assertInstanceOf(Attach::class, $keyed);
        self::assertNull($plain->key);
        self::assertInstanceOf(NameOperand::class, $keyed->key);
    }

    public function testOperandReadsAnIdentifierInsideParenthesesAsItsName(): void
    {
        $operation = (new Semantics())->inspect('DETACH ((aux))');
        $statement = $operation->statement;

        self::assertInstanceOf(Detach::class, $statement);
        self::assertInstanceOf(Grouped::class, $statement->schema);
        self::assertInstanceOf(Grouped::class, $statement->schema->operand);
        self::assertInstanceOf(NameOperand::class, $statement->schema->operand->operand);
        self::assertSame('DETACH ((aux))', $operation->toString());
    }

    public function testOperandLowersAnyOtherExpressionAsAnExpression(): void
    {
        $statement = (new Semantics())->inspect("ATTACH 'f' AS a")->statement;

        self::assertInstanceOf(Attach::class, $statement);
        self::assertInstanceOf(TextLiteral::class, $statement->file);
    }
}
