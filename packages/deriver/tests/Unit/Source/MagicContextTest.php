<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use Deriver\Source\MagicContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\MagicContext
 */
#[CoversClass(MagicContext::class)]
#[Small]
final class MagicContextTest extends TestCase
{
    public function testEnterNodeRecordsNamespaceAndOriginalMethodNames(): void
    {
        $visitor = new MagicContext();
        $visitor->enterNode(new \PhpParser\Node\Stmt\Namespace_(new \PhpParser\Node\Name('Acme')));
        $class = new \PhpParser\Node\Stmt\Class_('Box');
        $class->namespacedName = new \PhpParser\Node\Name('Acme\\Box');
        $visitor->enterNode($class);
        $visitor->enterNode(new \PhpParser\Node\Stmt\ClassMethod('run'));
        $constant = new \PhpParser\Node\Scalar\MagicConst\Method();
        $visitor->enterNode($constant);
        self::assertSame(['namespace' => 'Acme','class' => 'Acme\\Box','function' => 'run','method' => 'Acme\\Box::run'], $constant->getAttribute('deriver-lexical'));
    }
    public function testLeaveNodeRestoresNamesAfterNestedClosures(): void
    {
        $visitor = new MagicContext();
        $visitor->enterNode(new \PhpParser\Node\Stmt\Function_('run'));
        $closure = new \PhpParser\Node\Expr\Closure();
        $visitor->enterNode($closure);
        self::assertSame('{closure}', $visitor->scope['function']);
        $visitor->leaveNode($closure);
        self::assertSame('run', $visitor->scope['function']);
    }
}
