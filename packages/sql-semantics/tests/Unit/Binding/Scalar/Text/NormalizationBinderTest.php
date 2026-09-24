<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Text\NormalizationBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Text\UnicodeNormalForm;
use SqlSemantics\SchemaBuilder;

#[CoversClass(NormalizationBinder::class)]
#[Medium]
final class NormalizationBinderTest extends TestCase
{
    #[TestWith(["SELECT NORMALIZE('a', NFD)", "SELECT NORMALIZE('a', NFD)"])]
    #[TestWith(['SELECT "normalize"(\'a\')', 'SELECT "normalize"(\'a\')'])]
    public function testBindKeepsTheNormalFormOfTheKeywordForm(string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)->toString());
    }

    public function testTestReadsNegationAndForm(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE ASSERTION a CHECK (((-(ROW(NULL, NULL) OVERLAPS ROW(NULL, NULL))) IS NOT NORMALIZED) IS NOT NULL)');
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
        self::assertStringContainsString('IS NOT NFC NORMALIZED', $statement->toString());
    }

    public function testFormDefaultsToNfc(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT NORMALIZE('a')");
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Text\Normalization::class, $query->outputs[0]->expression);
        self::assertSame(UnicodeNormalForm::Nfc, $query->outputs[0]->expression->form);
    }
}
