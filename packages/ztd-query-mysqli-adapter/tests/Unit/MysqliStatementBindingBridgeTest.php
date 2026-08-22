<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ZtdQuery\Adapter\Mysqli\MysqliStatementBindingBridge;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliStatement;

#[CoversClass(MysqliStatementBindingBridge::class)]
final class MysqliStatementBindingBridgeTest extends TestCase
{
    public function testLimitsThePhpStanExcludedBridgeToNativeBindingMethods(): void
    {
        $bridge = new ReflectionClass(MysqliStatementBindingBridge::class);
        $statement = new ReflectionClass(ZtdMysqliStatement::class);
        $declaredMethods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            array_filter(
                $bridge->getMethods(),
                static fn (\ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === MysqliStatementBindingBridge::class,
            ),
        );
        sort($declaredMethods);
        $parent = $statement->getParentClass();

        self::assertSame(['__construct', 'bind_param', 'bind_result'], $declaredMethods);
        self::assertInstanceOf(ReflectionClass::class, $parent);
        self::assertSame(MysqliStatementBindingBridge::class, $parent->getName());
        self::assertTrue($bridge->getMethod('bind_param')->isFinal());
        self::assertTrue($bridge->getMethod('bind_result')->isFinal());
        self::assertTrue($bridge->getMethod('bind_param')->getParameters()[1]->isPassedByReference());
        self::assertTrue($bridge->getMethod('bind_result')->getParameters()[0]->isPassedByReference());
    }

    public function testExcludedBridgeContainsOnlyTheReviewedDelegationBodies(): void
    {
        $bridge = new ReflectionClass(MysqliStatementBindingBridge::class);
        $fileName = $bridge->getFileName();
        self::assertIsString($fileName);
        $source = file_get_contents($fileName);

        self::assertSame(<<<'PHP'
<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use mysqli_stmt;

/**
 * Isolates native mysqli by-reference signatures that PHPStan models incorrectly.
 */
abstract class MysqliStatementBindingBridge extends mysqli_stmt
{
    private mysqli_stmt $bindingDelegate;

    public function __construct(mysqli_stmt $bindingDelegate)
    {
        $this->bindingDelegate = $bindingDelegate;
    }

    /** @param mixed ...$vars */
    final public function bind_param(string $types, mixed &...$vars): bool
    {
        return $this->bindingDelegate->bind_param($types, ...$vars);
    }

    final public function bind_result(mixed &...$vars): bool
    {
        return $this->bindingDelegate->bind_result(...$vars);
    }
}

PHP, $source);
    }
}
