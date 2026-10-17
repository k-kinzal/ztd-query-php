<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Catalogue;
use JsonException;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Statement\Statement;

/**
 * Every statement constructor checks composition before publishing its fields.
 * The inventory includes new statement variants automatically.
 */
#[CoversNothing]
#[Medium]
final class CompositionTest extends TestCase
{
    /**
     * @return iterable<class-string, array{class-string}>
     * @throws JsonException When a semantic inventory is invalid
     */
    public static function providerStatements(): iterable
    {
        foreach ((new Catalogue())->implementations(Statement::class) as $class) {
            if (method_exists($class, '__construct')) {
                yield $class => [$class];
            }
        }
    }

    /**
     * @param class-string $class The inventoried statement class
     */
    #[DataProvider('providerStatements')]
    public function testStatementConstructionEndsWithItsCompositionInvariant(string $class): void
    {
        $file = dirname(__DIR__, 3) . '/vendor/k-kinzal/sql-semantics-mysql/src/' . str_replace('\\', '/', substr($class, strlen('SqlSemantics\\Platform\\MySql\\'))) . '.php';
        $source = file_get_contents($file);
        self::assertIsString($source);
        $nodes = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($nodes);
        $finder = new NodeFinder();
        $method = $finder->findFirst($nodes, static fn (Node $node): bool => $node instanceof Node\Stmt\ClassMethod && $node->name->name === '__construct');
        self::assertInstanceOf(Node\Stmt\ClassMethod::class, $method);
        self::assertNotNull($method->stmts);
        self::assertNotEmpty($method->stmts);
        self::assertSame([], $finder->findInstanceOf($method->stmts, Node\Stmt\Return_::class), 'A constructor cannot return before checking composition.');
        $last = $method->stmts[array_key_last($method->stmts)];
        self::assertInstanceOf(Node\Stmt\Expression::class, $last);
        self::assertInstanceOf(Node\Expr\MethodCall::class, $last->expr);
        $call = $last->expr;
        self::assertInstanceOf(Node\Expr\New_::class, $call->var);
        self::assertInstanceOf(Node\Name\FullyQualified::class, $call->var->class);
        self::assertSame(Generations::class, $call->var->class->toString());
        self::assertInstanceOf(Node\Identifier::class, $call->name);
        self::assertSame('check', $call->name->name);
        self::assertCount(1, $call->args);
        self::assertInstanceOf(Node\Arg::class, $call->args[0]);
        self::assertInstanceOf(Node\Expr\Variable::class, $call->args[0]->value);
        self::assertSame('this', $call->args[0]->value->name);
    }
}
