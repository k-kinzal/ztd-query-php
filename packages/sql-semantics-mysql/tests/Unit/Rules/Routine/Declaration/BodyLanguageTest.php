<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyLanguage;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineLanguage;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(BodyLanguage::class)]
#[Small]
final class BodyLanguageTest extends TestCase
{
    public function testCheckAcceptsTheBodyOfTheLastLanguage(): void
    {
        $body = new ExternalBody(new Text('return 1'));
        $languages = [new RoutineLanguage(), new RoutineLanguage(new Name('JAVASCRIPT'))];
        (new BodyLanguage())->check($body, $languages);
        self::assertTrue((new BodyLanguage())->compatible($body, $languages));
    }

    public function testCompatibleInterpretsSqlNamesAndDefaults(): void
    {
        $rule = new BodyLanguage();
        $external = new ExternalBody(new Text('return 1'));
        $sql = new Block();
        $language = new RoutineLanguage(new Name('PYTHON'));
        self::assertFalse($rule->compatible($external, []));
        self::assertFalse($rule->compatible($sql, [$language]));
        self::assertTrue($rule->compatible($sql, []));
        self::assertTrue($rule->compatible($sql, [$language, new RoutineLanguage(new Name('sQl'))]));
        self::assertFalse($rule->compatible($external, [$language, new RoutineLanguage()]));
    }
}
