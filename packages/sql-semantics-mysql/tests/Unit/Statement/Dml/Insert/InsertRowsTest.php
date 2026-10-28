<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\RowAlias;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(InsertRows::class)]
#[Medium]
final class InsertRowsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providerConditions(): iterable
    {
        foreach (\SqlSemantics\Contract\GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            yield $release->value . ' VALUES (1)' => [$release->value, 'INSERT INTO t VALUES (1)', false];
            yield $release->value . ' VALUE (1), (1, 2)' => [$release->value, 'INSERT INTO t VALUE (1), (1, 2)', true];
            yield $release->value . ' VALUES (ROW(1, 2))' => [$release->value, 'INSERT INTO t VALUES (ROW(1, 2))', true];
            yield $release->value . ' VALUES (SUM(a))' => [$release->value, 'INSERT INTO t VALUES (SUM(a))', true];
            yield $release->value . ' VALUE (1) ON DUPLICATE KEY UPDATE a = ROW(2, 3)' => [$release->value, 'INSERT INTO t VALUE (1) ON DUPLICATE KEY UPDATE a = ROW(2, 3)', true];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerConditions')]
    public function testHasExecutionConditionRetainsTheCompleteImmediateStatement(string $release, string $sql, bool $conditional): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        self::assertInstanceOf(InsertRows::class, $operation);
        self::assertSame($conditional, $operation->hasExecutionCondition());
        $information = $operation->statementInformation();
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\MySql\Rendering\Codec($information->profile->grammar, $information->profile->lexical, $information->client));
        $operation->render($out);
        self::assertSame($sql, (new \SqlSemantics\Rendering\Lexical())->join($out->canonical()));
        self::assertSame($sql, $operation->toString());
        self::assertSame('CREATE PROCEDURE p() ' . $sql, (new Semantics(Dialect::MySql, $release))->analyze('CREATE PROCEDURE p() ' . $sql)->toString());
    }

    public function testDeriveStatementChecksTheRows(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('INSERT INTO t (a, b, a) VALUES (1, 2, 3), (4, 5, 6)', [$t]);

        self::assertSame(["Column 'a' specified twice"], array_map(static fn ($diagnostic): string => $diagnostic->message(), $operation->diagnostics()));
    }

    public function testDeriveStatementAcceptsAnEmptyRowWithoutColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context([$t]), new InsertRows(new InsertInto(false, null, false, new WriteTarget(new QualifiedName(new Name('t')))), [new InsertedRow([]), new InsertedRow([])]), new \SqlSemantics\Statement\Source\SourceMap());

        self::assertSame([], $operation->diagnostics());
        self::assertNull($operation->output());
    }

    public function testRenderWritesValuesAndUpdates(): void
    {
        self::assertSame('INSERT INTO t VALUE (1), (2) ON DUPLICATE KEY UPDATE a = VALUES(a)', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('insert into t value (1), (2) on duplicate key update a = values(a)')->toString());
    }

    public function testRenderRejectsReplaceWithUpdates(): void
    {
        $this->expectExceptionMessage('REPLACE has neither a row alias nor ON DUPLICATE KEY UPDATE.');

        new InsertRows(new InsertInto(true, null, false, new WriteTarget(new QualifiedName(new Name('t')))), [new InsertedRow([])], new RowAlias(new Name('n')));
    }
}
