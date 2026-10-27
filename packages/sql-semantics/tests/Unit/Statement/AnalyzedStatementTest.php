<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\AnalyzedStatement;

#[CoversClass(AnalyzedStatement::class)]
#[Medium]
final class AnalyzedStatementTest extends TestCase
{
    public function testToStringReconstructsTheConcreteStatement(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze("INSERT t VALUE ('42')");
        self::assertSame("INSERT t VALUE ('42')", $statement->toString());
    }

    public function testProfileIdentifiesTheSelectedLanguage(): void
    {
        $statement = (new Semantics(Dialect::MySql, '5.6.51'))->analyze("INSERT t VALUE ('42')");
        self::assertSame(GrammarRelease::MySql5651, $statement->profile()->grammar);
    }

    public function testDeclarationsIsEmptyForAWrite(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze("INSERT t VALUE ('42')");
        self::assertSame([], $statement->declarations());
    }
    public function testDiagnosticsPreserveAnUnresolvedColumn(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT missing FROM absent', []);
        self::assertNotEmpty($statement->diagnostics());
        self::assertSame('Relation absent does not exist.', $statement->diagnostics()[0]->message());
    }

    public function testWarningsRetainTheSpellingCondition(): void
    {
        $statement = (new Semantics(Dialect::MySql, '8.4.7'))->analyze('SELECT 1 AS full');
        self::assertCount(1, $statement->warnings());
        self::assertStringContainsString('FULL', strtoupper($statement->warnings()[0]->message()));
    }

    public function testNoticesAssociateTheWarningWithItsOccurrence(): void
    {
        $statement = (new Semantics(Dialect::MySql, '8.4.7'))->analyze('SELECT 1 AS full');
        self::assertCount(1, $statement->notices());
        self::assertSame($statement->warnings()[0], $statement->notices()[0]->warning);
        self::assertNotNull($statement->notices()[0]->subject);
    }

    public function testOutputDescribesTheResultColumns(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT 7 AS value');
        self::assertNotNull($statement->output());
        self::assertSame('value', $statement->output()->projection[0]->name?->value);
    }

    public function testShapeKeepsAnUnexpandedStar(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT * FROM t');
        self::assertNotNull($statement->shape());
        self::assertFalse($statement->shape()->complete());
    }

    public function testFieldsPreserveTheProjectionOrder(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT 1 AS a, 2 AS b');
        self::assertNotNull($statement->fields());
        self::assertSame(['a', 'b'], array_map(static fn ($field) => $field->name?->value, $statement->fields()->items));
    }

    public function testFieldFindsANameAndItsPosition(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT 1 AS a, 2 AS b');
        self::assertSame($statement->field(1), $statement->field('b'));
    }

    public function testLookupFieldRetainsAmbiguity(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT 1 AS a, 2 AS a');
        $lookup = $statement->lookupField('a');
        self::assertInstanceOf(\SqlSemantics\Statement\Shape\AmbiguousFields::class, $lookup);
        self::assertCount(2, $lookup->fields);
    }

    public function testInputRelationKeepsTheAlias(): void
    {
        $statement = (new Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t AS x');
        $input = $statement->inputRelation();
        self::assertInstanceOf(\SqlSemantics\Statement\NamedRelation::class, $input);
        self::assertSame('x', $input->alias()?->value);
    }

    public function testSingleNamedInputReturnsTheReferencedName(): void
    {
        $statement = (new Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT a FROM public.t');
        self::assertSame('t', $statement->singleNamedInput()->name()->name->value);
        self::assertSame('public', $statement->singleNamedInput()->name()->schema?->value);
    }

    public function testHasStatementInformationMarksCompletedConstruction(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT 1');
        self::assertTrue($statement->hasStatementInformation());
    }

}
