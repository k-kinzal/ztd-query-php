<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlSchemaFetcher;

#[CoversClass(MySqlSchemaFetcher::class)]
final class MySqlSchemaFetcherTest extends TestCase
{
}
