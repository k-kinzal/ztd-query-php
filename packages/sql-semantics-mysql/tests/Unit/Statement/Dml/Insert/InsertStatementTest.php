<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\DestinationColumn;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertStatement;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertTarget;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClause;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(InsertStatement::class)]
#[Medium]
final class InsertStatementTest extends TestCase
{
    public function testToStringReconstructsGeneratedValuesWithoutSqlOrAnAnalysisContext(): void
    {
        $name = new QualifiedName(new Name('t'));
        $target = new InsertTarget($name, new UndeclaredTable(new UndeclaredRelation($name)));
        $statement = new InsertStatement(new LanguageProfile(GrammarRelease::MySql847), $target, new ValuesClause([[new StringLiteral(['42'])]], keyword: ValuesKeyword::Value), intoWritten: false);
        self::assertSame("INSERT t VALUE ('42')", $statement->toString());
        self::assertNull($statement->source);
    }

    public function testRenderWritesItsSemanticValues(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze("INSERT INTO t VALUES ('42')");
        self::assertInstanceOf(InsertStatement::class, $statement);
        $out = new Output(new Codec(GrammarRelease::MySql847));
        $statement->render($out);
        self::assertSame("INSERT INTO t VALUES ('42')", (new Lexical())->join($out->pieces()));
    }

    public function testProfileRetainsTheSelectedRelease(): void
    {
        $statement = (new Semantics(Dialect::MySql, '5.6.51'))->analyze("INSERT t VALUE ('42')");
        self::assertSame(GrammarRelease::MySql5651, $statement->profile()->grammar);
    }

    public function testDeclarationsIntroducesNoTables(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze("INSERT t VALUE ('42')");
        self::assertSame([], $statement->declarations());
    }

    public function testRejectsAWidthDifferentFromItsExplicitDestinations(): void
    {
        $name = new QualifiedName(new Name('t'));
        $resolution = new UndeclaredTable(new UndeclaredRelation($name));
        $target = new InsertTarget($name, $resolution, [new DestinationColumn(new Name('a'), $resolution)]);
        $this->expectExceptionMessage('Each VALUES row matches the explicit destination count.');
        new InsertStatement(new LanguageProfile(GrammarRelease::MySql847), $target, new ValuesClause([[]]));
    }
}
