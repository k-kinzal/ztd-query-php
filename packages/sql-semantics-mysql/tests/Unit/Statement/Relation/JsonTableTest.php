<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(JsonTable::class)]
#[Medium]
final class JsonTableTest extends TestCase
{
    public function testDeriveRelationAnswersTheColumnsOfTheDefinitions(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT a, n FROM JSON_TABLE('[1]', '$[*]' COLUMNS (n FOR ORDINALITY, a INT PATH '$')) AS j", []);

        self::assertSame(['a', 'n'], array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []));
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveRelationReportsAMissingAlias(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT 1 FROM JSON_TABLE('[1]', '$[*]' COLUMNS (a INT PATH '$'))", []);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $operation->facts->diagnostics[0]);
        self::assertSame(MisuseRule::TableFunctionWithoutAlias, $operation->facts->diagnostics[0]->rule);
    }

    public function testRenderWritesTheTableFunction(): void
    {
        self::assertSame("SELECT a FROM t, JSON_TABLE(t.d, '$' COLUMNS (a INT PATH '$')) j", (new Semantics(Dialect::MySql))->analyze("select a from t, json_table(t.d, '$' columns (a int path '$')) j")->toString());
    }

    public function testATableWithoutColumnsIsRejected(): void
    {
        $this->expectExceptionMessage('JSON_TABLE defines at least one column.');

        new JsonTable(new NullLiteral(), new StringLiteral(['$']), []);
    }
}
