<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeOption::class)]
#[Medium]
final class LikeOptionTest extends TestCase
{
    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('CREATE TABLE n (LIKE t INCLUDING IDENTITY EXCLUDING GENERATED)', []);
        self::assertSame('CREATE TABLE n (LIKE t INCLUDING IDENTITY EXCLUDING GENERATED)', $statement->toString());
    }
}
