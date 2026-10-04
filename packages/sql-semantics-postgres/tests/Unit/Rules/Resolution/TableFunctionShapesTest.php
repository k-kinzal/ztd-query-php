<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Resolution\TableFunctionShapes::class)]
#[Small]
final class TableFunctionShapesTest extends TestCase
{
    public function testXmlReportsASecondOrdinalityColumn(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM XMLTABLE ('/r' PASSING '<r/>' COLUMNS a text NULL NOT NULL, n FOR ORDINALITY, m FOR ORDINALITY)");
        self::assertSame('only one FOR ORDINALITY column is allowed', $query->facts->diagnostics[1]->message());
    }

    public function testOptionsReportsConflictingNullability(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM XMLTABLE ('/r' PASSING '<r/>' COLUMNS a text NULL NOT NULL, n FOR ORDINALITY, m FOR ORDINALITY)");
        self::assertSame('conflicting or redundant NULL / NOT NULL declarations for column "a"', $query->facts->diagnostics[0]->message());
    }

    public function testJsonHasTheColumnsInOrder(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (a text, NESTED '\$.b' COLUMNS (b text), c text))");
        self::assertSame(['a:Nullable', 'b:Nullable', 'c:Nullable'], array_map(static fn (\SqlSemantics\Statement\Shape\Field $field): string => ($field->name->value ?? '') . ':' . $field->nullability->name, $query->fields()->items ?? []));
    }

    public function testColumnsAnswersOneSlotPerColumn(): void
    {
        $context = (new \SqlSemantics\Platform\PostgreSql\Platform())->context(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172), null, [], true);
        $derivation = new \SqlSemantics\Construction\Derivation($context);
        self::assertCount(1, (new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\TableFunctionShapes())->columns([new \SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonOrdinalityColumn(new \SqlSemantics\Statement\Identifier\Name('n'))], $derivation, $derivation->environment()));
    }

    public function testTypedReportsABehaviorTheColumnRefuses(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (e boolean EXISTS EMPTY ON ERROR))");
        self::assertSame('invalid ON ERROR behavior for column "e"', $query->facts->diagnostics[0]->message());
    }

    public function testClauseIgnoresNoClause(): void
    {
        $context = (new \SqlSemantics\Platform\PostgreSql\Platform())->context(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172), null, [], true);
        $derivation = new \SqlSemantics\Construction\Derivation($context);
        (new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\TableFunctionShapes())->clause(null, $derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }
}
