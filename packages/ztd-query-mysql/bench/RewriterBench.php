<?php

declare(strict_types=1);

namespace Bench;

use LogicException;
use ZtdQuery\Platform\MySql\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\MySqlRewriter;
use ZtdQuery\Platform\MySql\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

final class RewriterBench
{
    private MySqlRewriter $rewriter;

    private string $selectSql = 'SELECT id, name, email FROM users WHERE id = 1';

    private string $insertSql = "INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@example.com')";

    public function setUp(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $definition = $schemaParser->parse('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
        if ($definition === null) {
            throw new LogicException('Failed to parse benchmark schema.');
        }

        $registry = new TableDefinitionRegistry();
        $registry->register('users', $definition);

        $store = new ShadowStore();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer(
            $parser,
            $selectTransformer,
            $insertTransformer,
            $updateTransformer,
            $deleteTransformer,
            $replaceTransformer,
        );
        $mutationResolver = new MySqlMutationResolver(
            $store,
            $registry,
            $schemaParser,
            $updateTransformer,
            $deleteTransformer,
        );

        $this->rewriter = new MySqlRewriter(
            new MySqlQueryGuard($parser),
            $store,
            $registry,
            $transformer,
            $mutationResolver,
            $parser,
        );
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(50)
     * @Iterations(5)
     */
    public function benchRewriteSelect(): void
    {
        $this->rewriter->rewrite($this->selectSql);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(50)
     * @Iterations(5)
     */
    public function benchRewriteInsert(): void
    {
        $this->rewriter->rewrite($this->insertSql);
    }
}
