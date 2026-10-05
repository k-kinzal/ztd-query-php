<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Catalog\DomainRule::class)]
#[Medium]
final class DomainRuleTest extends TestCase
{
    public function testStatementLowersADomain(): void
    {
        self::assertSame('CREATE DOMAIN d text COLLATE "C"', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DOMAIN d AS text COLLATE "C"')->toString());
    }

    public function testConstraintKeepsTheName(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER DOMAIN d ADD CONSTRAINT c NOT NULL');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\DomainRule($lowering);
        self::assertSame('c', $rule->constraint($tree->find('DomainConstraint')[0])->name?->value);
    }

    public function testElementLowersACheck(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER DOMAIN d ADD CHECK (VALUE > 0)');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\DomainRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Domain\DomainCheck::class, $rule->element($tree->find('DomainConstraintElem')[0], null));
    }
}
