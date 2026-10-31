<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(JsonTable::class)]
#[Medium]
final class JsonTableTest extends TestCase
{
    public function testDeriveRelationAnswersTheColumnsOfTheDefinitions(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT a, n FROM JSON_TABLE('[1]', '$[*]' COLUMNS (n FOR ORDINALITY, a INT PATH '$')) AS j", []);

        self::assertSame(['a', 'n'], array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []));
        self::assertSame([], $operation->diagnostics());
    }

    public function testMissingAliasIsRefusedBeforePublishingAnInvalidRelation(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('Every table function must have an alias');
        (new Semantics(Dialect::MySql))->analyze("SELECT 1 FROM JSON_TABLE('[1]', '$[*]' COLUMNS (a INT PATH '$'))", []);
    }

    public function testEmptyAliasCannotBeConstructed(): void
    {
        $this->expectExceptionMessage('A JSON table requires a nonempty alias.');
        new JsonTable(new NullLiteral(), new StringLiteral(['$']), [new OrdinalityColumn(new Name('n'))], new Name(''));
    }

    public function testRenderWritesTheTableFunction(): void
    {
        self::assertSame("SELECT a FROM t, JSON_TABLE(t.d, '$' COLUMNS (a INT PATH '$')) j", (new Semantics(Dialect::MySql))->analyze("select a from t, json_table(t.d, '$' columns (a int path '$')) j")->toString());
    }

    public function testATableWithoutColumnsIsRejected(): void
    {
        $this->expectExceptionMessage('JSON_TABLE defines at least one column.');

        new JsonTable(new NullLiteral(), new StringLiteral(['$']), [], new Name('j'));
    }

    public function testEqualsIsNotAJsonTableAliasMark(): void
    {
        $this->expectExceptionMessage('A JSON table alias is written with or without AS.');

        new JsonTable(new NullLiteral(), new StringLiteral(['$']), [new OrdinalityColumn(new Name('n'))], new Name('j'), AliasMark::Equals);
    }
}
