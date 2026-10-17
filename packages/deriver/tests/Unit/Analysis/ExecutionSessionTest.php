<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use Deriver\Analysis\ExecutionSession;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ExecutionSessionTest extends TestCase
{
    /**
     * @throws JsonException If query metadata cannot be encoded
     */
    public function testDeriveStillAnswersThroughTheFormerName(): void
    {
        $session = new ExecutionSession(new ProjectInput([new SourceFile('a.php', '<?php function f(){return 42;}')]));
        self::assertSame(42, $session->derive(new ReturnQuery('f'))->normalOutcomes[0]->values['return']->native());
    }
}
