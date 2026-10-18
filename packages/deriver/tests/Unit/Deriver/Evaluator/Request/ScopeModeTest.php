<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Request;

use Deriver\Deriver\Evaluator\Request\ScopeMode as Subject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ScopeModeTest extends TestCase
{
    public function testScopesKeepDistinctCacheIdentities(): void
    {
        $symbolic = new \Deriver\Deriver\Evaluator\Request\ReturnQuery('App\\run');
        $entries = new \Deriver\Deriver\Evaluator\Request\ReturnQuery('App\\run', \Deriver\Deriver\Evaluator\Request\QueryScope::fromEntrypoints([new \Deriver\Deriver\Evaluator\Request\EntryPoint('App\\main')]));
        $encoding = new \Deriver\Deriver\Identity\QueryEncoding();
        self::assertSame(Subject::Symbolic, $symbolic->scope()->mode);
        self::assertSame(Subject::Entrypoint, $entries->scope()->mode);
        self::assertNotSame($encoding->key($symbolic), $encoding->key($entries));
    }
}
