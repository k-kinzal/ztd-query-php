<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Call\JsonRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind;
use SqlSemantics\Platform\MySql\Statement\Call\Json\NestedColumns;
use SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn;
use SqlSemantics\Platform\MySql\Statement\Call\Json\PathColumn;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

#[CoversClass(JsonRule::class)]
#[Small]
final class JsonRuleTest extends TestCase
{
    public function testValueLowersJsonValue(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse("SELECT JSON_VALUE(a, '$.b' RETURNING CHAR(3) DEFAULT 'x' ON EMPTY ERROR ON ERROR)")->find('function_call_keyword')[0];
        $rule = new JsonRule($lowering);
        $call = $rule->value($lowering->form($node));

        self::assertSame(CastKind::Char, $call->returning?->kind);
        self::assertSame(JsonResponseKind::Default, $call->onEmpty?->kind);
        self::assertSame(JsonResponseKind::Error, $call->onError?->kind);
    }

    public function testReturningLowersNoType(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse("SELECT JSON_VALUE(a, '$.b')")->find('opt_returning_type')[0];
        $rule = new JsonRule($lowering);
        self::assertNull($rule->returning($node));
    }

    public function testResponsesLowersOnError(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse("SELECT JSON_VALUE(a, '$.b' NULL ON ERROR)")->find('opt_on_empty_or_error')[0];
        $rule = new JsonRule($lowering);
        $responses = $rule->responses($node);

        self::assertNull($responses[0]);
        self::assertSame(JsonResponseKind::Null, $responses[1]?->kind);
    }

    public function testResponseLowersOneClause(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse("SELECT JSON_VALUE(a, '$.b' ERROR ON EMPTY)")->find('on_empty')[0];
        $rule = new JsonRule($lowering);
        self::assertSame(JsonResponseKind::Error, $rule->response($node, 'on_empty: json_on_response ON_SYM EMPTY_SYM')->kind);
    }

    public function testColumnsLowersEveryColumnForm(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse("SELECT * FROM JSON_TABLE('[]', '$[*]' COLUMNS (n FOR ORDINALITY, a INT EXISTS PATH '$.a', NESTED PATH '$.b[*]' COLUMNS (b TEXT PATH '$')))")->find('columns_clause')[0];
        $rule = new JsonRule($lowering);
        $columns = $rule->columns($node);

        self::assertInstanceOf(OrdinalityColumn::class, $columns[0]);
        self::assertInstanceOf(PathColumn::class, $columns[1]);
        self::assertTrue($columns[1]->exists);
        self::assertInstanceOf(NestedColumns::class, $columns[2]);
    }

    public function testColumnLowersAnOrdinalityColumn(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse("SELECT * FROM JSON_TABLE('[]', '$' COLUMNS (n FOR ORDINALITY))")->find('jt_column')[0];
        $rule = new JsonRule($lowering);
        self::assertInstanceOf(OrdinalityColumn::class, $rule->column($node));
    }

    public function testPathKeepsTheReversedOrder(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse("SELECT * FROM JSON_TABLE('[]', '$' COLUMNS (a INT PATH '$' NULL ON ERROR DEFAULT '1' ON EMPTY))")->find('jt_column')[0];
        $rule = new JsonRule($lowering);
        $column = $rule->path($lowering->form($node));

        self::assertTrue($column->errorFirst);
        self::assertSame(JsonResponseKind::Default, $column->onEmpty?->kind);
    }
}
