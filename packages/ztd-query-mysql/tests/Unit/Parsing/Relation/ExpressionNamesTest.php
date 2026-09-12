<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Parsing\Relation\ExpressionNames;

#[CoversClass(ExpressionNames::class)]
final class ExpressionNamesTest extends TestCase
{
    public function testTablePrefersParsedNamesAndFallsBackToExpressions(): void
    {
        $expression = new \PhpMyAdmin\SqlParser\Components\Expression();
        $expression->table = 'users';
        $expression->expr = 'app.users';
        self::assertSame('users', ExpressionNames::table($expression));
        $expression->table = '';
        self::assertSame('app.users', ExpressionNames::table($expression));
    }

    public function testAliasPrefersExplicitAliases(): void
    {
        $expression = new \PhpMyAdmin\SqlParser\Components\Expression();
        $expression->table = 'users';
        $expression->alias = 'u';
        self::assertSame('u', ExpressionNames::alias($expression));
        $expression->alias = '';
        self::assertSame('users', ExpressionNames::alias($expression));
    }

}
