<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;

#[CoversClass(RestrictedSelectOption::class)]
#[Small]
final class RestrictedSelectOptionTest extends TestCase
{
    public function testMessageNamesTheMisplacedOption(): void
    {
        self::assertSame("Incorrect usage/placement of 'SQL_BUFFER_RESULT'", (new RestrictedSelectOption(SelectOption::BufferResult))->message());
    }
}
