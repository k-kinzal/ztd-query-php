<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterSource::class)]
#[Medium]
final class ParameterSourceTest extends TestCase
{
    public function testDefaultIsReadFromToDefault(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET a TO DEFAULT')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetParameterFrom::class, $statement);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterSource::Default, $statement->source);
    }

    public function testCurrentIsReadFromFromCurrent(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET a FROM CURRENT')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetParameterFrom::class, $statement);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterSource::Current, $statement->source);
    }
}
