<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;

#[CoversClass(LegacyForms::class)]
#[Small]
final class LegacyFormsTest extends TestCase
{
    public function testRequiresDistinguishesRemovedOptionsFromRetainedOptions(): void
    {
        $forms = new LegacyForms();

        self::assertTrue($forms->requires(SelectOption::Cache));
        self::assertTrue($forms->requires(ExplainModifier::Partitions));
        self::assertFalse($forms->requires(SelectOption::NoCache));
    }
}
