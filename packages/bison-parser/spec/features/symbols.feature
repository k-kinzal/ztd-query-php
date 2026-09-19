Feature: Symbols, Terminal and Nonterminal
  GNU Bison 3.8.2 manual, chapter "Bison Grammar Files", section "Symbols,
  Terminal and Nonterminal".

  Terminal symbols are written as identifiers, as C character constants, or as
  C string constants; nonterminal symbols are identifiers.

  Scenario: Symbol names may contain letters, underscores, periods, and non-initial digits and dashes
    Given the grammar file:
      """
      %token WITH-DASH WITH.PERIOD with_underscore d1g1t5
      %%
      .start: WITH-DASH WITH.PERIOD with_underscore d1g1t5;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry WITH-DASH
        SymbolEntry WITH.PERIOD
        SymbolEntry with_underscore
        SymbolEntry d1g1t5
      %%
      Rule .start
        Alternative
          SymbolItem WITH-DASH
          SymbolItem WITH.PERIOD
          SymbolItem with_underscore
          SymbolItem d1g1t5
      """

  Scenario: A dash cannot start a symbol name
    Given the grammar file:
      """
      %token -DASH
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 1 column 8

  Scenario: A digit cannot start a symbol name
    Given the grammar file:
      """
      %token 1NV4L1D
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 1 column 8

  Scenario: A named token kind is written as an identifier and declared with %token
    Given the grammar file:
      """
      %token NUM
      %%
      exp: NUM;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry NUM
      %%
      Rule exp
        Alternative
          SymbolItem NUM
      """

  Scenario: A character token kind is written like a C character constant and needs no declaration
    Given the grammar file:
      """
      %%
      exp: exp '+' exp;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem exp
          SymbolItem '+'
          SymbolItem exp
      """

  Scenario: The usual C escape sequences can be used in character literals
    Given the grammar file:
      """
      %%
      exp: '\n' '\t' '\\' '\'' '"' '\101' '\x41' '\a' '\?';
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem '\n'
          SymbolItem '\t'
          SymbolItem '\\'
          SymbolItem '\''
          SymbolItem '"'
          SymbolItem 'A' spelled '\101'
          SymbolItem 'A' spelled '\x41'
          SymbolItem '\x07' spelled '\a'
          SymbolItem '?' spelled '\?'
      """

  Scenario: The null character must not be used as a character literal
    Given the grammar file:
      """
      %%
      exp: '\0';
      """
    When the file is parsed
    Then parsing fails at line 2 column 6

  Scenario: Trigraphs have no special meaning in character literals, so '??=' is not one character
    Given the grammar file:
      """
      %%
      exp: '??=';
      """
    When the file is parsed
    Then parsing fails at line 2 column 6

  Scenario: Backslash-newline is not allowed in a character literal
    Given the grammar file:
      """
      %%
      exp: '\
      ';
      """
    When the file is parsed
    Then parsing fails at line 2 column 6

  Scenario: A literal string token is written like a C string constant and needs no declaration
    Given the grammar file:
      """
      %%
      exp: exp "<=" exp;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem exp
          SymbolItem "<="
          SymbolItem exp
      """

  Scenario: The usual C escape sequences can be used in string literals
    Given the grammar file:
      """
      %%
      exp: "\x3c=" "a\tb" "\"q\"";
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem "<=" spelled "\x3c="
          SymbolItem "a\tb"
          SymbolItem "\"q\""
      """

  Scenario: A null character must not be used within a string literal
    Given the grammar file:
      """
      %%
      exp: "a\0b";
      """
    When the file is parsed
    Then parsing fails at line 2 column 6

  Scenario: Trigraphs have no special meaning in string literals
    Given the grammar file:
      """
      %%
      exp: "??=";
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem "??="
      """

  Scenario: Backslash-newline is not allowed in a string literal
    Given the grammar file:
      """
      %%
      exp: "a\
      b";
      """
    When the file is parsed
    Then parsing fails at line 2 column 6

  Scenario: A literal string token may be given a symbolic name as an alias with %token
    Given the grammar file:
      """
      %token LE "<="
      %%
      exp: exp LE exp | exp "<=" exp;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry LE "<="
      %%
      Rule exp
        Alternative
          SymbolItem exp
          SymbolItem LE
          SymbolItem exp
        Alternative
          SymbolItem exp
          SymbolItem "<="
          SymbolItem exp
      """

  Scenario: error is a terminal symbol reserved for error recovery and may be used in rules
    Given the grammar file:
      """
      %%
      stmt: error ';';
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule stmt
        Alternative
          SymbolItem error
          SymbolItem ';'
      """
