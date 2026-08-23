<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\ParameterBindingCompiler;

#[CoversNothing]
final class ParameterBindingCompilerTest extends TestCase
{
    public function testDeclaresDialectParameterCompilationContract(): void
    {
        $reflection = new \ReflectionClass(ParameterBindingCompiler::class);

        self::assertTrue($reflection->isInterface());
        self::assertTrue($reflection->hasMethod('compile'));
    }
}
