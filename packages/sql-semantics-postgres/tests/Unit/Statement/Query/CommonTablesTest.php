<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Query\CommonTables;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;

#[CoversClass(CommonTables::class)]
#[Small]
final class CommonTablesTest extends TestCase
{
    public function testDeriveCommonTablesAnswersTheEnvironmentTheQueryBodySees(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $outer = $derivation->environment();
        $with = new class () implements CommonTables {
            public function deriveCommonTables(Derivation $derivation, Environment $outer): Environment
            {
                return new Environment($derivation->context, $outer);
            }

            public function render(Output $out): void
            {
            }
        };
        self::assertSame($outer, $with->deriveCommonTables($derivation, $outer)->outer);
    }
}
