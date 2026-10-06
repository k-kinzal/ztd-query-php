<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\SelectFacts;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(SelectFacts::class)]
#[Medium]
final class SelectFactsTest extends TestCase
{
    public function testDeriveScopesEveryClause(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('SELECT a AS x FROM t WHERE b = 1 GROUP BY a HAVING x > 1 ORDER BY x', [$t]);

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(AliasTarget::class, $operation->facts->scalar($operation->statement->orderBy[0]->expression)->resolution);
        self::assertSame($t->columns[0], $operation->field('x')->column());
    }

    public function testDeriveReportsARowWhereTheServerTakesOneValue(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $messages = array_map(static fn ($diagnostic): string => $diagnostic->message(), [
            ...$semantics->analyze('SELECT (1, 2)')->facts->diagnostics,
            ...$semantics->analyze('SELECT 1 FROM DUAL WHERE (1, 2)')->facts->diagnostics,
            ...$semantics->analyze('SELECT 1 HAVING (SELECT 1, 2)')->facts->diagnostics,
            ...$semantics->analyze('SELECT 1 ORDER BY (1, 2)')->facts->diagnostics,
            ...$semantics->analyze('SELECT 1 GROUP BY ROW(1, 2, 3)')->facts->diagnostics,
        ]);

        self::assertSame(['Operand should contain 1 column(s), not 2.', 'Operand should contain 1 column(s), not 2.', 'Operand should contain 1 column(s), not 2.', 'Operand should contain 1 column(s), not 2.', 'Operand should contain 1 column(s), not 3.'], $messages);
    }

    public function testWindowsReportsANameDefinedTwice(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 FROM t WINDOW w AS (), w AS ()');

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $operation->facts->diagnostics[0]);
    }

    public function testAliasesAnswersTheFieldsNamedByAnAliasOrAfterTheirText(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 AS x, 2, a, 1+1, 3 AS y FROM t');
        $select = $operation->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertSame(['x', '2', '1+1', 'y'], array_map(static fn (Field $field): ?string => $field->name?->value, (new SelectFacts())->aliases($select, $operation->fields()->items ?? [], $operation->context->profile)));
    }

    public function testDeriveResolvesAnOrderingByTheTextAnItemIsNamedAfter(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1+1 FROM t ORDER BY `1+1`', []);
        $select = $operation->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(AliasTarget::class, $operation->facts->scalar($select->orderBy[0]->expression)->resolution);
    }
}
