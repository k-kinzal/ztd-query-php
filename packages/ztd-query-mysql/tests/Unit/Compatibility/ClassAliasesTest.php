<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer;
use ZtdQuery\Rewrite\QueryKind;

#[CoversNothing]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ClassAliasesTest extends TestCase
{
    public function testFormerParserAndGuardNamesStillClassifySql(): void
    {
        $parser = new MySqlParser();
        $guard = new MySqlQueryGuard($parser);

        self::assertSame(QueryKind::READ, $guard->classify('SELECT id FROM users'));
    }

    public function testFormerParameterTypeAcceptsTheNewTransformerBeforeTheFormerNameIsUsed(): void
    {
        $transformer = new SelectTransformer();
        $accept = static fn (\ZtdQuery\Platform\MySql\Transformer\SelectTransformer $value): \ZtdQuery\Platform\MySql\Transformer\SelectTransformer => $value;

        self::assertSame($transformer, $accept($transformer));
        self::assertSame('SELECT 1', $accept($transformer)->transform('SELECT 1', []));
    }

    public function testNewParameterTypeAcceptsTheFormerTransformerBeforeTheNewNameIsUsed(): void
    {
        $transformer = new \ZtdQuery\Platform\MySql\Transformer\SelectTransformer();
        $accept = static fn (SelectTransformer $value): SelectTransformer => $value;

        self::assertSame($transformer, $accept($transformer));
        self::assertSame('SELECT 1', $accept($transformer)->transform('SELECT 1', []));
    }
}
