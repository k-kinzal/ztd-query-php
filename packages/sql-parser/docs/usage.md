# Usage

## Parsing

Each dialect has one entry point: `SqlParser\MySql\MySqlParser`, `SqlParser\PostgreSql\PostgreSqlParser` and `SqlParser\Sqlite\SqliteParser`. The constructor takes the grammar release tag and loads its parse table; `parse()` returns the tree and `tokenize()` the tokens.

```php
$parser = new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2');
$tree = $parser->parse('SELECT 1; SELECT 2');
```

MySQL parses one statement at a time, as the server does; a trailing semicolon is accepted. PostgreSQL and SQLite parse several statements separated by semicolons into one tree. SQLite supplies a missing final semicolon the way `sqlite3_prepare` does.

## The tree

`parse()` returns a `SqlParser\Parser\Node`. A node has the `name` of the grammar nonterminal that built it, the `ordinal` of the alternative that matched, counted from zero in the order the grammar lists them, and its `children`, each a `Node` or a `SqlParser\Lexer\Token`.

```php
$sql = 'SELECT a FROM t';
$tree = (new \SqlParser\Sqlite\SqliteParser())->parse($sql);

$tree->name;                        // 'input'
$select = $tree->find('select')[0]; // nodes named select, in text order
$select->text($sql);                // 'SELECT a FROM t'
$select->span();                    // [0, 15]
$select->tokens();                  // every Token under the node
```

`find()` searches the subtree, the node itself included. `span()` and `text()` cover the first through the last token under the node, so a node built by an empty alternative has no span. Tokens carry their terminal `name`, `text` and byte `offset`.

Tokens the server synthesises are in the tree with empty text: MySQL's `END_OF_INPUT` and the semicolon SQLite supplies at the end. MySQL's `WITH ROLLUP` is one token spanning both words, as it is in the server.

## Writing the text back

Nothing of the text is left out of the tree. A token carries in `leading` the whitespace and comments written before it, and the tree carries in `trailing` whatever follows its last token, so `toString()` answers the text the tree was parsed from without being given it.

```php
$sql = "SELECT a -- the column\nFROM t;  ";
$tree = (new \SqlParser\Sqlite\SqliteParser())->parse($sql);

$tree->toString() === $sql;         // true
$tree->find('select')[0]->toString();   // "SELECT a -- the column\nFROM t"
$tree->tokens()[1]->leading;        // ' '
```

A node under the root writes back from the trivia before its first token through its last token, so printing a subtree gives the text of that subtree with its comments, which is what `text()` answers when the source is at hand. Text that holds no token at all, such as a PostgreSQL input that is only a comment, is carried by the tree alone.

## Errors

`SqlParser\Parser\SyntaxException` carries the rejected `token`, the `expected` terminal names, the byte `offset` and the line and column `position`. `SqlParser\Lexer\LexicalException` reports text no token starts with, such as an unterminated string. Both extend `SqlParser\Lexer\SourceException`.

## MySQL modes

`SqlParser\MySql\SqlMode` holds the `sql_mode` flags that change what the lexer produces: `ansiQuotes`, `pipesAsConcat`, `highNotPrecedence`, `noBackslashEscapes` and `ignoreSpace`. The default is a fresh server's mode.

## Releases

`MySqlParser::versions()`, `PostgreSqlParser::versions()` and `SqliteParser::versions()` list the shipped release tags, oldest first. Constructing a parser with an unknown tag raises a `RuntimeException`.
