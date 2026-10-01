<?php

declare(strict_types=1);

namespace Tests\Scenario;

use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Semantic\Name;
use SqlSemantics\Semantic\QualifiedName;
use SqlSemantics\Semantic\Schema\Column;
use SqlSemantics\Semantic\Schema\Table;
use SqlSemantics\Semantic\Statement\Select;

/**
 * Semantic declarations shared by relationship specifications.
 */
final class SemanticCases
{
    /**
     * Builds a real declaration with two distinguishable columns.
     */
    public static function table(Dialect $dialect, string $name = 'bar'): Table
    {
        return new Table(
            $dialect,
            new QualifiedName(new Name($name)),
            new Column(new Name('foo'), new TypeDescriptor($dialect, 'integer'), Nullability::NotNull),
            new Column(new Name('label'), new TypeDescriptor($dialect, 'text')),
        );
    }

    /**
     * Analyzes a SELECT and establishes its concrete type for callers.
     */
    public static function select(Dialect $dialect, string $sql = 'SELECT foo FROM bar', bool $catalog = true): Select
    {
        $statement = (new Semantics($dialect))->analyze($sql, $catalog ? [self::table($dialect)] : null);
        \PHPUnit\Framework\Assert::assertInstanceOf(Select::class, $statement);
        return $statement;
    }
}
