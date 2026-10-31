<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

#[CoversClass(Alternative::class)]
#[Small]
final class AlternativeTest extends TestCase
{
    public function testParameterIdentitiesRetainTheirWrittenOrder(): void
    {
        $first = new Parameter();
        $second = new Parameter();
        $statement = new QueryRoot(new Select([], [new SelectExpression($first), new SelectExpression($second)]));
        $profile = new Interpretation(new LanguageProfile(GrammarRelease::MySql847), Charset::known('utf8mb4'));
        $sources = new \SqlSemantics\Statement\Source\SourceMap([new \SqlSemantics\Statement\Source\Origin($first, 7, 1), new \SqlSemantics\Statement\Source\Origin($second, 10, 1)]);
        $alternative = new Alternative($profile, $statement, $sources);
        self::assertSame([$first, $second], $alternative->parameters);
        self::assertSame($statement, $alternative->statement);
        self::assertSame($sources, $alternative->sources);
    }
}
