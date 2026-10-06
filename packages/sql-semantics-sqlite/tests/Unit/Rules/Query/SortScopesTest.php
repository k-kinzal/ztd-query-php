<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableShapes;
use SqlSemantics\Platform\Sqlite\Rules\Query\SelectFacts;
use SqlSemantics\Platform\Sqlite\Rules\Query\SortScopes;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\RowExpression;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\OutputOrdinal;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(SortScopes::class)]
#[Medium]
final class SortScopesTest extends TestCase
{
    public function testDeriveLetsABareAliasWinOnlyWhenAskedTo(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT a AS b, b AS a FROM t ORDER BY a', [$create]);
        $select = $query->statement;
        self::assertInstanceOf(Select::class, $select);
        $from = $select->from;
        $output = $query->facts->output;
        self::assertNotNull($from);
        self::assertNotNull($output);
        $projection = $output->projection;
        $relation = new VisibleRelation($from, (new TableShapes())->shape($create->declarations()[0]), null, new QualifiedName(new Name('t')));
        $derivation = new Derivation($semantics->context([$create]));
        $environment = new Environment($derivation->context, null, [$relation], [], (new SelectFacts())->aliases($select, $projection));
        $ordering = new ColumnUse(new Name('a'));
        $grouping = new ColumnUse(new Name('a'));

        (new SortScopes())->derive([$ordering], $derivation, $environment, $projection, true);
        (new SortScopes())->derive([$grouping], $derivation, $environment, $projection, false);
        $alias = $derivation->facts()->scalar($ordering)->resolution;
        $column = $derivation->facts()->scalar($grouping)->resolution;
        self::assertInstanceOf(AliasTarget::class, $alias);
        self::assertSame(1, $alias->field->position);
        self::assertInstanceOf(ResolvedColumn::class, $column);
        self::assertSame($create->declarations()[0]->columns[0], $column->declaration());
    }

    public function testDeriveResolvesAnOrdinalAgainstTheProjectionAndReportsARowValue(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze("SELECT 1 AS a, 'x' AS b");
        $select = $query->statement;
        $output = $query->facts->output;
        self::assertInstanceOf(Select::class, $select);
        self::assertNotNull($output);
        $projection = $output->projection;
        $derivation = new Derivation($semantics->context([]));
        $ordinal = new OutputOrdinal(new IntegerLiteral('2'));
        $row = new RowExpression([new IntegerLiteral('1'), new IntegerLiteral('2')]);
        $sum = new Binary(BinaryOperator::Add, new IntegerLiteral('1'), new IntegerLiteral('2'));

        (new SortScopes())->derive([$ordinal, $row, $sum], $derivation, new Environment($derivation->context, null, [], [], (new SelectFacts())->aliases($select, $projection)), $projection, true);
        $resolution = $derivation->facts()->scalar($ordinal)->resolution;
        self::assertInstanceOf(AliasTarget::class, $resolution);
        self::assertSame('b', $resolution->field->name?->value);
        self::assertCount(1, $derivation->facts()->diagnostics);
        self::assertSame(MisuseRule::TooManyValueColumns->value, $derivation->facts()->diagnostics[0]->message());
        self::assertTrue($derivation->facts()->covers($sum));
    }

    public function testWordAnswersAnUnqualifiedWordOnly(): void
    {
        $scopes = new SortScopes();

        self::assertSame('a', $scopes->word(new ColumnUse(new Name('a')))?->value);
        self::assertSame('a', $scopes->word(new Grouped(new Collate(new ColumnUse(new Name('a')), new Name('nocase'))))?->value);
        self::assertNull($scopes->word(new ColumnUse(new Name('a'), new QualifiedName(new Name('t')))));
        self::assertSame('zz', $scopes->word(new DoubleQuotedWord(new Name('zz')))?->value);
        self::assertSame('true', $scopes->word(new TruthWord(true))?->value);
        self::assertNull($scopes->word(new IntegerLiteral('1')));
        self::assertNull($scopes->word(new Binary(BinaryOperator::Add, new ColumnUse(new Name('a')), new IntegerLiteral('1'))));
    }
}
