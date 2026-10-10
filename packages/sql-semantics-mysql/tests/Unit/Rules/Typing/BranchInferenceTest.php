<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\BranchInference;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(BranchInference::class)]
#[Small]
final class BranchInferenceTest extends TestCase
{
    public function testResolveRetainsTheFirstBranchAndDoesNotAllocateAVariable(): void
    {
        $settings = new Settings(Collation::known('utf8mb4_0900_ai_ci'), userVariables: []);
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([], session: $settings));
        $variable = new UserVariable(new Name('v'));
        $derivation->scalar($variable, $derivation->environment());
        $domains = [Domain::string(65532, Collation::binary()), Domain::null(), Domain::integer()];
        $result = (new BranchInference())->resolve($domains, [new Grouped($variable), null, null], $derivation);

        self::assertEquals(Domain::string(65535, Collation::binary()), $result[0]);
        self::assertSame([$domains[1], $domains[2]], array_slice($result, 1));
        self::assertSame([], $derivation->introducedVariables);
        self::assertSame([], $settings->userVariables);
    }
}
