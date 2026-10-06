<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\DoBlock;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(DoBlock::class)]
#[Medium]
final class DoBlockTest extends TestCase
{
    public function testCodeAnswersTheOnlyCodeString(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze("DO LANGUAGE plpgsql 'BEGIN END'")->statement;
        self::assertInstanceOf(DoBlock::class, $statement);
        self::assertSame('BEGIN END', $statement->code()?->value);
    }

    public function testCodeAnswersNullForTwoCodeStrings(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze("DO 'a' 'b'")->statement;
        self::assertInstanceOf(DoBlock::class, $statement);
        self::assertNull($statement->code());
    }

    public function testLanguageAnswersTheOnlyLanguage(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze("DO 'x' LANGUAGE 'sql'")->statement;
        self::assertInstanceOf(DoBlock::class, $statement);
        self::assertSame('sql', $statement->language()?->name());
    }

    public function testLanguageAnswersNullWithoutALanguage(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze("DO 'x'")->statement;
        self::assertInstanceOf(DoBlock::class, $statement);
        self::assertNull($statement->language());
    }

    public function testDeriveStatementReportsMissingCodeAndRepeatedItems(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            [['no inline code specified'], ['conflicting or redundant options'], ['conflicting or redundant options'], []],
            [
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('DO LANGUAGE plpgsql')->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze("DO 'a' 'b'")->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze("DO 'a' LANGUAGE sql LANGUAGE plpgsql")->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze("DO 'a' LANGUAGE sql")->facts->diagnostics),
            ],
        );
    }

    public function testRenderWritesTheItemsInOrder(): void
    {
        self::assertSame("DO LANGUAGE plpgsql 'BEGIN END'", (new Semantics(Dialect::PostgreSql))->analyze('DO LANGUAGE plpgsql $$BEGIN END$$')->toString());
    }
}
