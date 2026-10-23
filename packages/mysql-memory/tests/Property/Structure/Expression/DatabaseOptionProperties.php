<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Expression;

use JsonException;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseReadOnly;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Metadata;

/**
 * Constructor-generated database options execute without reading SQL text.
 */
#[CoversNothing]
#[Medium]
final class DatabaseOptionProperties extends TestCase
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
     * Generated encryption settings execute directly and retain their rendering correspondence.
     *
     * @throws JsonException When the reviewed inventory is invalid
     */
    #[DataProvider('providerInputs')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesGeneratedEncryptionOptionsExecuteAndCorrespondToTheProfile(string $release, string $seed): void
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
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $execution = new Execution();
        $execution->runIn($session, new CreateDatabase(false, new Name('d'), [$option]));
        self::assertNotNull($session->instance->dictionary->schema('d'));
        self::assertSame($option->encryption, $session->instance->dictionary->schema('d')->encryption);
        $opposite = $option->encryption === \SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption::Enabled ? \SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption::Disabled : \SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption::Enabled;
        $execution->runIn($session, new AlterDatabase(new Name('d'), [new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption($opposite)]));
        self::assertSame($opposite, $session->instance->dictionary->schema('d')->encryption);
        $result = $execution->runIn($session, $statement);

        self::assertInstanceOf(Completion::class, $result);
        self::assertSame(1, $result->affectedRows);
        self::assertSame([], $session->diagnostics->conditions);
        self::assertSame($option->encryption, $session->instance->dictionary->schema('d')->encryption);
        $shown = $execution->runIn($session, new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateDatabase(new Name('d')));
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $shown);
        self::assertSame("CREATE DATABASE `d` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='" . $option->encryption->value . "' */", $shown->rows[0][1]);
    }

    /**
     * The option's public constructor supplies its invariant before execution.
     *
     * @throws JsonException When the reviewed inventory is invalid
     */
    #[DataProvider('providerInputs')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesGeneratedReadOnlyOptionsHaveExecutableOperands(string $release, string $seed): void
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
