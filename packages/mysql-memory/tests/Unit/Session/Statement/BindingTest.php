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
        $statement = $session->analyze('SELECT * FROM t')->statement;
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
}
