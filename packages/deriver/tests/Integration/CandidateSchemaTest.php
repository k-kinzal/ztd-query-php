<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Analyzer;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ParameterQuery;
use Deriver\Query\ReturnQuery;
use Deriver\Query\ValueQuery;
use JsonException;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversNothing]
#[Large]
final class CandidateSchemaTest extends TestCase
{
    /**
     * @return list<array{string}> Equivalent statement and expression forms
     */
    public static function providerThrows(): array
    {
        return [['throw new RuntimeException();'], ['return throw new RuntimeException();']];
    }

    /**
     * @throws JsonException If fixture or captured metadata cannot be encoded
     */
    public function testJsonIsACandidateArrayAndPartialExpressionsAreStructured(): void
    {
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('schema.php', '<?php function f(int $foo){return 1+$foo;}')]));
        $result = $session->derive(new ReturnQuery('f'));
        $json = $result->toJson();
        $records = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($records);
        self::assertInstanceOf(stdClass::class, $records[0]);
        self::assertSame(['type', 'type_name', 'result', 'evidence'], array_keys(get_object_vars($records[0])));
        self::assertSame('partials', $records[0]->type);
        self::assertIsObject($records[0]->result);
        $bytes = file_get_contents(__DIR__ . '/../../resources/schema/candidates-v2.json');
        self::assertIsString($bytes);
        $schema = json_decode($bytes, false, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $schema);
        self::assertTrue((new Validator())->validate($records, $schema)->isValid());
        self::assertStringContainsString(base64_encode('$foo'), $json);
    }

    /**
     * @throws JsonException If fixture or captured metadata cannot be encoded
     */
    public function testT06UncalledParameterDoesNotTakeItsDefault(): void
    {
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('defaults.php', '<?php function f($x=30){return $x;}')]));
        $result = $session->derive(new ParameterQuery('f', 'x'));
        self::assertSame('partials', $result->candidates[0]->type);
        self::assertSame('$x', $result->candidates[0]->term->literal);
        $called = (new Analyzer())->open(new ProjectInput([new SourceFile('defaults.php', '<?php function f($x=30){return $x;}function caller(){return f();}')]));
        self::assertSame(30, $called->derive(new ReturnQuery('caller'))->candidates[0]->result);
    }

    /**
     * @throws JsonException If fixture or captured metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerThrows')]
    public function testThrowOnlyDoesNotManufactureNull(string $body): void
    {

        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('throw.php', '<?php function f(){' . $body . '}')]));
        $result = $session->derive(new ReturnQuery('f'));
        self::assertCount(1, $result);
        self::assertSame('partials', $result->candidates[0]->type);
        self::assertSame('never', $result->candidates[0]->type_name);
        self::assertSame('NO_VALUE_DEFINITION', $result->candidates[0]->term->attributes['reason']);
    }

    /**
     * @throws JsonException If fixture or captured metadata cannot be encoded
     */
    public function testT12SqlExampleKeepsDefaultAndUnresolvedBranches(): void
    {
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('sql.php', '<?php function select($table){if($table===null){$table="default_table";}$sql="SELECT * FROM $table";observe($sql);}')]));
        $result = $session->derive(new ValueQuery($session->callsTo('observe')[0]->argument(0)));
        self::assertCount(2, $result);
        self::assertSame('SELECT * FROM default_table', $result->candidates[0]->result);
        self::assertSame('partials', $result->candidates[1]->type);
        $typo = (new Analyzer())->open(new ProjectInput([new SourceFile('sql.php', '<?php function select($table){$table="SELECT * FROM $table";observe($sql);}')]));
        $unknown = $typo->derive(new ValueQuery($typo->callsTo('observe')[0]->argument(0)));
        self::assertSame('$sql', $unknown->candidates[0]->term->literal);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testEvidenceRejectsUnknownAttributesAndScalarPartials(): void
    {
        $result = \Tests\Fake\CandidateApi::returns('function target($x){return $x;}');
        $bytes = file_get_contents(__DIR__ . '/../../resources/schema/candidates-v2.json');
        self::assertIsString($bytes);
        $schema = json_decode($bytes, false, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $schema);
        $badAttributes = str_replace('"attributes":{"query":', '"attributes":{"unrecognized":true,"query":', $result->toJson());
        self::assertFalse((new Validator())->validate(json_decode($badAttributes, false, 512, JSON_THROW_ON_ERROR), $schema)->isValid());
        $records = $result->jsonSerialize();
        $records[0]->result = 42;
        self::assertFalse((new Validator())->validate($records, $schema)->isValid());
    }

}
