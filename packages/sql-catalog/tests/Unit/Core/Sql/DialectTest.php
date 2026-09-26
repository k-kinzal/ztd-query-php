<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Sql;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Extension\Laravel\Grammar::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Extension\Laravel\WriteCompiler::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Extension\Laravel\QueryState::class)]
final class DialectTest extends TestCase
{
    public function testIdentifierQuoteCanBeSuppliedByAnApplication(): void
    {
        $policy = self::createStub(\SqlCatalog\Core\Sql\Dialect::class);
        $policy->method('identifierQuote')->willReturn('!');
        $grammar = new \SqlCatalog\Extension\Laravel\Grammar($policy);
        self::assertSame('!a!.!b!', $grammar->wrap(\SqlCatalog\Core\Evaluation\Domain::literal('a.b'))->soleLiteral()?->value);
    }

    public function testInsertPrefixComesFromThePolicy(): void
    {
        $policy = self::createStub(\SqlCatalog\Core\Sql\Dialect::class);
        $policy->method('identifierQuote')->willReturn('"');
        $policy->method('insertPrefix')->willReturn('custom insert into ');
        $state = new \SqlCatalog\Extension\Laravel\QueryState(['table' => \SqlCatalog\Core\Evaluation\Domain::literal('items')]);
        $values = new \SqlCatalog\Core\Evaluation\ArrayTerm([new \SqlCatalog\Core\Evaluation\ArrayEntry(\SqlCatalog\Core\Evaluation\Domain::literal('id'), \SqlCatalog\Core\Evaluation\Domain::literal(1))]);
        [$sql] = (new \SqlCatalog\Extension\Laravel\WriteCompiler(new \SqlCatalog\Extension\Laravel\Grammar($policy)))->insert($state, $values, true);
        self::assertSame('custom insert into "items" ("id") values (?)', $sql->soleLiteral()?->value);
    }

    public function testInsertSuffixComesFromThePolicy(): void
    {
        $policy = self::createStub(\SqlCatalog\Core\Sql\Dialect::class);
        $policy->method('identifierQuote')->willReturn('"');
        $policy->method('insertPrefix')->willReturn('insert into ');
        $policy->method('insertSuffix')->willReturn(' custom conflict policy');
        $state = new \SqlCatalog\Extension\Laravel\QueryState(['table' => \SqlCatalog\Core\Evaluation\Domain::literal('items')]);
        $values = new \SqlCatalog\Core\Evaluation\ArrayTerm([new \SqlCatalog\Core\Evaluation\ArrayEntry(\SqlCatalog\Core\Evaluation\Domain::literal('id'), \SqlCatalog\Core\Evaluation\Domain::literal(1))]);
        [$sql] = (new \SqlCatalog\Extension\Laravel\WriteCompiler(new \SqlCatalog\Extension\Laravel\Grammar($policy)))->insert($state, $values, true);
        self::assertSame('insert into "items" ("id") values (?) custom conflict policy', $sql->soleLiteral()?->value);
    }


    public function testReturningSuffixComesFromThePolicy(): void
    {
        $policy = self::createStub(\SqlCatalog\Core\Sql\Dialect::class);
        $policy->method('identifierQuote')->willReturn('"');
        $policy->method('insertPrefix')->willReturn('insert into ');
        $policy->method('insertSuffix')->willReturn('');
        $policy->method('returningSuffix')->willReturn(' custom returning ');
        $state = new \SqlCatalog\Extension\Laravel\QueryState(['table' => \SqlCatalog\Core\Evaluation\Domain::literal('items')]);
        $values = new \SqlCatalog\Core\Evaluation\ArrayTerm([new \SqlCatalog\Core\Evaluation\ArrayEntry(\SqlCatalog\Core\Evaluation\Domain::literal('id'), \SqlCatalog\Core\Evaluation\Domain::literal(1))]);
        [$sql] = (new \SqlCatalog\Extension\Laravel\WriteCompiler(new \SqlCatalog\Extension\Laravel\Grammar($policy)))->insertGetId($state, $values, \SqlCatalog\Core\Evaluation\Domain::literal(null));
        self::assertSame('insert into "items" ("id") values (?) custom returning "id"', $sql->soleLiteral()?->value);
    }
}
