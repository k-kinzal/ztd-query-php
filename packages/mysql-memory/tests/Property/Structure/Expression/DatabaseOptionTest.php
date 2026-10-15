<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Expression;

use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Execution;
use Fuzz\Structure\Metadata;
use JsonException;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseReadOnly;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Constructor-generated database options execute without reading SQL text.
 */
#[CoversNothing]
#[Medium]
final class DatabaseOptionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerInputs(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $release . ':' . $seed => [$release, (string) $seed];
            }
        }
    }

    /**
     * Generated encryption operands must have a spelling admitted by the language profile.
     *
     * @throws JsonException When the reviewed inventory is invalid
     * @throws ReflectionException When a constructor cannot be inspected
     */
    #[DataProvider('providerInputs')]
    public function testGeneratedEncryptionOptionsCorrespondToTheProfile(string $release, string $seed): void
    {
        $option = (new Construction(new Choices('database-encryption:' . $seed), new Catalogue(), new Metadata()))->object(\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption::class, 6);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption::class, $option);
        $statement = new AlterDatabase(new Name('d'), [$option]);
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::from('mysql-' . $release));
        $platform = \SqlSemantics\Contract\Platforms::of('mysql');
        $out = new \SqlSemantics\Rendering\Output($platform->codec($profile));
        $statement->render($out);
        $sql = (new \SqlSemantics\Rendering\Lexical())->join($out->pieces());
        $lowered = $platform->lower($platform->parser($profile)->parse($sql), $profile, new \SqlSemantics\Lowering\Leaves());

        self::assertNull((new \SqlSemantics\Validation\Equivalence())->difference($statement, (new \SqlSemantics\Validation\Publication())->root($lowered)));
    }

    /**
     * The option's public constructor supplies its invariant before execution.
     *
     * @throws JsonException When the reviewed inventory is invalid
     * @throws ReflectionException When a constructor cannot be inspected
     */
    #[DataProvider('providerInputs')]
    public function testGeneratedReadOnlyOptionsHaveExecutableOperands(string $release, string $seed): void
    {
        $option = (new Construction(new Choices('database-option:' . $seed), new Catalogue(), new Metadata()))->object(DatabaseReadOnly::class, 6);
        self::assertInstanceOf(DatabaseReadOnly::class, $option);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $execution = new Execution();
        $execution->runIn($session, new CreateDatabase(false, new Name('d')));
        $result = $execution->runIn($session, new AlterDatabase(new Name('d'), [$option]));

        self::assertInstanceOf(Completion::class, $result);
        self::assertSame(1, $result->affectedRows);
        self::assertSame([], $session->diagnostics->conditions);
    }
}
