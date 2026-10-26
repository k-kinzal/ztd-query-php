<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Statement;

use MySqlMemory\Instance;
use MySqlMemory\Session\Statement\Binding;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Binding::class)]
#[Small]
final class BindingTest extends TestCase
{
    public function testOperationRetainsTheStructureAndUsesCurrentDeclarations(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $analyzed = $session->analyze('SELECT * FROM t');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $analyzed);
        $statement = $analyzed->statement;
        $session->query('ALTER TABLE t ADD b INT');
        $session->text = 'unrelated invalid source';

        $operation = (new Binding())->operation($session, $statement);

        self::assertSame($statement, $operation->statement);
        $shape = $operation->shape();
        self::assertInstanceOf(\SqlSemantics\Statement\Shape\RowShape::class, $shape);
        self::assertCount(2, $shape->slots);
        self::assertSame('unrelated invalid source', $session->text);
    }

    public function testParametersRetainLegacyNullAndModernDerivedTypes(): void
    {
        $legacy = (new Instance('5.6.51'))->connect();
        $legacy->replayed = true;
        $binding = new Binding();
        $null = $binding->parameters($legacy, [[null, Domain::integer()]])[0];
        $modern = (new Instance())->connect();
        $modern->preparation->domains = [0 => Domain::decimal(10, 2)];
        $numeric = $binding->parameters($modern, [[1, Domain::integer()]])[0];

        self::assertSame([Kind::Null, Field::VarString], [$null->kind, $null->field]);
        self::assertSame([Kind::Decimal, 2], [$numeric->kind, $numeric->decimals]);
    }

    public function testStatementPublishesConcreteInsertValuesAgainstCurrentDeclarations(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $parsed = (new \SqlSemantics\Platform\MySql\Platform())->lower($session->semantics()->parser()->parse("INSERT INTO t VALUES ('42')"), $session->semantics()->profile(), new \SqlSemantics\Lowering\Leaves());
        $bound = (new Binding())->statement($session, $parsed[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertStatement::class, $bound);
        self::assertSame('d', $bound->target->resolvedName?->schema?->value);
        $reply = (new \MySqlMemory\Session\Execution($session))->statement($bound);
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
        self::assertSame(1, $reply->affectedRows);
    }

    public function testContextRetainsTheCurrentDatabaseAndParameterDomains(): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $parameter = new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter();
        $statement = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($parameter)]));
        $context = (new Binding())->context($session, $statement, [[42, Domain::integer()]], null, null);
        self::assertSame('d', $context->declarationSchema->value);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::class, $context->session);
        self::assertSame(Kind::Integer, $context->session->parameters[0]->kind);
    }
}
