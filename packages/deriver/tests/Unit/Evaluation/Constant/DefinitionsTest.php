<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Constant;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class DefinitionsTest extends TestCase
{
    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testApplyRetainsFirstDefinitionAfterADuplicate(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){define("NAME","first");$ok=define("NAME","second");return [NAME,$ok];}');
        self::assertSame(['first', false], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame(['PHP_WARNING'], array_column($result->frontiers, 'code'));
    }
}
