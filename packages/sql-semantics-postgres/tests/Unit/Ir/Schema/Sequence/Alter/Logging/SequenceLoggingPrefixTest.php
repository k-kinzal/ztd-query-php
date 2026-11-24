<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter\Logging;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\Logging\SequenceLoggingPrefix;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging;

#[CoversClass(SequenceLoggingPrefix::class)]
final class SequenceLoggingPrefixTest extends TestCase
{
    public function testCheckRequiresTheProperPrefixToAgree(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A sequence logging prefix must request one consistent mode.');

        SequenceLoggingPrefix::check([SequenceLogging::Logged, SequenceLogging::Unlogged, SequenceLogging::Unlogged]);
    }
}
