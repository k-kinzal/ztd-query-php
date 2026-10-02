<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\ConnectionRule;
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
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertInstanceOf(Attach::class, $semantics->analyze("ATTACH 'f' AS a")->statement);
        self::assertInstanceOf(Detach::class, $semantics->analyze('DETACH a')->statement);
    }

    public function testKeywordIsOptionalBeforeTheOperand(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $short = $semantics->analyze('DETACH a')->statement;
        $long = $semantics->analyze('DETACH DATABASE a')->statement;

        self::assertInstanceOf(Detach::class, $short);
        self::assertInstanceOf(Detach::class, $long);
        self::assertInstanceOf(NameOperand::class, $short->schema);
        self::assertInstanceOf(NameOperand::class, $long->schema);
    }

    public function testKeyIsNullUnlessAKeyIsWritten(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze("ATTACH 'f' AS a")->statement;
        $keyed = $semantics->analyze("ATTACH 'f' AS a KEY secret")->statement;

        self::assertInstanceOf(Attach::class, $plain);
        self::assertInstanceOf(Attach::class, $keyed);
        self::assertNull($plain->key);
        self::assertInstanceOf(NameOperand::class, $keyed->key);
    }

    public function testOperandReadsAnIdentifierInsideParenthesesAsItsName(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('DETACH ((aux))');
        $statement = $operation->statement;

        self::assertInstanceOf(Detach::class, $statement);
        self::assertInstanceOf(Grouped::class, $statement->schema);
        self::assertInstanceOf(Grouped::class, $statement->schema->operand);
        self::assertInstanceOf(NameOperand::class, $statement->schema->operand->operand);
        self::assertSame('DETACH ((aux))', $operation->toString());
    }

    public function testOperandLowersAnyOtherExpressionAsAnExpression(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze("ATTACH 'f' AS a")->statement;

        self::assertInstanceOf(Attach::class, $statement);
        self::assertInstanceOf(TextLiteral::class, $statement->file);
    }
}
