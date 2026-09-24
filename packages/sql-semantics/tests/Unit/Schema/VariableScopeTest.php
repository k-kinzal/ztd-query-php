<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\VariableDefinition;
use SqlSemantics\Schema\VariableScope;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(VariableScope::class)]
#[Medium]
final class VariableScopeTest extends TestCase
{
    public function testRepresentsEveryStorageNamespace(): void
    {
        self::assertSame(['user', 'session', 'global', 'local'], array_column(VariableScope::cases(), 'value'));
    }

    public function testScopesKeepSameNamedDeclarationsDistinct(): void
    {
        $integer = TypeDescriptor::builtin(Dialect::MySql, 'integer');
        $text = TypeDescriptor::builtin(Dialect::MySql, 'text');
        $schema = (new SchemaBuilder(Dialect::MySql))->build()->withVariables(new VariableDefinition('x', VariableScope::User, $integer), new VariableDefinition('x', VariableScope::Session, $text));
        $binder = new Binder($schema);
        $user = $binder->bind('SELECT @x');
        $session = $binder->bind('SELECT @@session.x');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $user);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $session);
        self::assertSame('integer', $user->outputs[0]->expression->type->name);
        self::assertSame('text', $session->outputs[0]->expression->type->name);
    }
}
