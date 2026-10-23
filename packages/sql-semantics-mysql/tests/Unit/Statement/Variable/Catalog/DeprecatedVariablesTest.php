<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\DeprecatedVariables;

#[CoversClass(DeprecatedVariables::class)]
#[Small]
final class DeprecatedVariablesTest extends TestCase
{
    public function testWarningNamesTheVariableAndTheOneToUseInstead(): void
    {
        self::assertSame("'@@tx_read_only' is deprecated and will be removed in a future release. Please use '@@transaction_read_only' instead", (new DeprecatedVariables())->warning('Tx_Read_Only', GrammarRelease::MySql5744));
        self::assertSame("'@@storage_engine' is deprecated and will be removed in a future release. Please use '@@default_storage_engine' instead", (new DeprecatedVariables())->warning('storage_engine', GrammarRelease::MySql5651));
        self::assertSame("'@@query_cache_type' is deprecated and will be removed in a future release.", (new DeprecatedVariables())->warning('query_cache_type', GrammarRelease::MySql5744));
    }

    public function testWarningAnswersNullForAVariableTheReleaseKeeps(): void
    {
        self::assertSame([null, null], [(new DeprecatedVariables())->warning('tx_isolation', GrammarRelease::MySql5651), (new DeprecatedVariables())->warning('tx_isolation', GrammarRelease::MySql847)]);
    }

    public function testWarningIncludesTheModernFunctionCreatorSetting(): void
    {
        self::assertSame("'@@profiling' is deprecated and will be removed in a future release.", (new DeprecatedVariables())->warning('PROFILING', GrammarRelease::MySql847));
        self::assertSame("'@@log_bin_trust_function_creators' is deprecated and will be removed in a future release.", (new DeprecatedVariables())->warning('LOG_BIN_TRUST_FUNCTION_CREATORS', GrammarRelease::MySql8044));
        self::assertNull((new DeprecatedVariables())->warning('log_bin_trust_function_creators', GrammarRelease::MySql5744));
    }
}
