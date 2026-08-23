<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\ViewDefinition;

#[CoversClass(ViewDefinition::class)]
final class ViewDefinitionTest extends TestCase
{
    public function testCarriesAQueryAndItsSemanticDependencies(): void
    {
        $definition = new ViewDefinition('SELECT * FROM users', ['users']);

        self::assertSame('SELECT * FROM users', $definition->query);
        self::assertSame(['users'], $definition->dependencies);
    }
}
