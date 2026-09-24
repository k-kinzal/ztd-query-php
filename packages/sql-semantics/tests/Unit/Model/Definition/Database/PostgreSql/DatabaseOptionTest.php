<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Database\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseOption;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseParameter;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(DatabaseOption::class)]
final class DatabaseOptionTest extends TestCase
{
    public function testDefaultRequestIsRepresentedByNull(): void
    {
        $option = new DatabaseOption(DatabaseParameter::Template, null);
        self::assertSame(DatabaseParameter::Template, $option->parameter);
        self::assertNull($option->value);
    }

    public function testValueOutsideTheParameterDomainIsRejected(): void
    {
        $this->expectException(InvalidStructure::class);
        new DatabaseOption(DatabaseParameter::ConnectionLimit, 'many');
    }
}
