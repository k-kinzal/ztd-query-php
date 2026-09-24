<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Module;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Module\ConstructorArgument;
use SqlSemantics\Model\Module\Invocation;
use SqlSemantics\Model\Statement\Definition\CreateVirtualTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Invocation::class)]
#[Medium]
final class InvocationTest extends TestCase
{
    public function testRetainsTheModuleAndOrderedArguments(): void
    {
        $invocation = new Invocation('fts5', [new ConstructorArgument('title'), new ConstructorArgument('tokenize = "porter"')]);
        self::assertSame('fts5', $invocation->module);
        self::assertSame(['title', 'tokenize = "porter"'], array_column($invocation->arguments, 'text'));
    }

    public function testDefaultsToAnArgumentlessConstructor(): void
    {
        self::assertSame([], (new Invocation('json_each'))->arguments);
    }

    public function testBindsFromAVirtualTableDeclaration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('CREATE VIRTUAL TABLE docs USING fts5(title, body)');
        self::assertInstanceOf(CreateVirtualTableStatement::class, $statement);
        self::assertSame('fts5', $statement->constructor->module);
        self::assertSame(['title', 'body'], array_column($statement->constructor->arguments, 'text'));
    }
}
