<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\CatalogColumn as Subject;

#[CoversClass(Subject::class)]
final class CatalogColumnTest extends TestCase
{
    public function testMapDataTypeFormatsNumericAndLengthParameters(): void
    {
        $column = ['data_type' => 'numeric', 'character_maximum_length' => null, 'numeric_precision' => '8', 'numeric_scale' => '2', 'udt_name' => 'numeric'];
        self::assertSame('NUMERIC(8, 2)', (new Subject())->mapDataType($column));
        $column['data_type'] = 'character varying';
        $column['character_maximum_length'] = '40';
        self::assertSame('VARCHAR(40)', (new Subject())->mapDataType($column));
    }

    public function testResolveTypeNormalizesArrayElements(): void
    {
        $column = ['data_type' => 'ARRAY', 'character_maximum_length' => null, 'numeric_precision' => null, 'numeric_scale' => null, 'udt_name' => '_int4'];
        self::assertSame('INT4_ARRAY', (new Subject())->resolveType($column));
    }

    public function testParseDefaultRemovesCastsAndRecognizesSequences(): void
    {
        $parser = new Subject();
        self::assertSame('open', $parser->parseDefault("'open'::text"));
        self::assertNull($parser->parseDefault("nextval('users_id_seq'::regclass)"));
        self::assertSame(12.5, $parser->parseDefault('12.5'));
        self::assertTrue($parser->parseDefault('true'));
    }
}
