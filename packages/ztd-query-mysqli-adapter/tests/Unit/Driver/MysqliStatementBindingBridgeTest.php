<?php

declare(strict_types=1);

namespace Tests\Unit\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliStatementBindingBridge;

#[CoversClass(MysqliStatementBindingBridge::class)]
final class MysqliStatementBindingBridgeTest extends TestCase
{
    public function testExcludedBridgeContainsOnlyTheReviewedDelegationBodies(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../src/Driver/MysqliStatementBindingBridge.php');

        self::assertSame(<<<'PHP'
<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli\Driver;

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
