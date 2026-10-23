<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Json;

use MySqlMemory\Evaluation\Function\Json\Schemas;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Schemas::class)]
#[Small]
final class SchemasTest extends TestCase
{
    public function testRoutinesValidateAndReport(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_SCHEMA_VALID('{\"type\":\"object\"}', '1'), JSON_SCHEMA_VALID('{}', NULL), JSON_SCHEMA_VALIDATION_REPORT('{\"required\":[\"a\"]}', '{}'), JSON_SCHEMA_VALIDATION_REPORT('{}', '1')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', null, '{"valid": false, "reason": "The JSON document location \'#\' failed requirement \'required\' at JSON Schema location \'#\'", "schema-location": "#", "document-location": "#", "schema-failed-keyword": "required"}', '{"valid": true}']], $result->rows);
    }

    public function testValidateRefusesASchemaThatIsNoObject(): void
    {
        $this->expectExceptionMessage('Invalid JSON type in argument 1 to function json_schema_valid; an object is required.');

        (new Instance())->connect()->query("SELECT JSON_SCHEMA_VALID('[1]', '1')");
    }

    public function testValidateRefusesARemoteReference(): void
    {
        $this->expectExceptionMessage("This version of MySQL doesn't yet support 'references in JSON Schema'");

        (new Instance())->connect()->query('SELECT JSON_SCHEMA_VALID(\'{"$ref":"http://x/y"}\', \'1\')');
    }
}
