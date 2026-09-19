@manual
Feature: Bison Symbols
  Source: GNU Bison Manual, version 3.8.2, as doc/bison.texi of the bison-3.8.2
  release (https://ftp.gnu.org/gnu/bison/bison-3.8.2.tar.xz, also at
  https://cgit.git.savannah.gnu.org/cgit/bison.git/tree/doc/bison.texi?h=v3.8.2).
  Every scenario is tagged with the node of the manual it states, as
  "manual:" followed by the node name with dashes for spaces; online, that
  node is https://www.gnu.org/software/bison/manual/html_node/<node>.html in
  the edition the GNU project publishes (Bison 3.8.1 at the time of writing).
  The nodes stated in this feature:
    Appendix A, Bison Symbols
    Appendix ABison Symbols                                manual:Table-of-Symbols

  The entries of the appendix that belong to the grammar-file language. Each
  directive of the Bison Declaration Summary has its own scenario in that
  feature; this one covers the punctuation, the comment forms, the in-rule
  directives and the reserved symbol.

  @manual:Table-of-Symbols
  Scenario: %% separates the declarations from the rules, and the rules from the epilogue
    Given the grammar file:
      """
      %token NUM
      %%
      exp: NUM;
      %%
      int yylex (void);
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
      %%
      Epilogue {\nint yylex (void);\n}
      """

  @manual:Table-of-Symbols
  Scenario: %{ code %} is the prologue
    Given the grammar file:
      """
      %{ int counter; %}
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Prologue { int counter; }
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:Table-of-Symbols
  Scenario: %?{ expression } is a predicate action inside a rule
    Given the grammar file:
      """
      %%
      exp: %?{ allowed () } 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          Predicate { allowed () }
          SymbolItem 'a'
      """

  @manual:Table-of-Symbols
  Scenario: A colon separates the result of a rule from its components
    Given the grammar file:
      """
      %%
      exp
        : 'a'
        ;
      """
    When the file is parsed
    Then the tree is the same as for:
      """
      %%
      exp: 'a';
      """

  @manual:Table-of-Symbols
  Scenario: A semicolon terminates a rule
    Given the grammar file:
      """
      %%
      exp: 'a'; other: 'b';
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      Rule other
        Alternative
          SymbolItem 'b'
      """

  @manual:Table-of-Symbols
  Scenario: A vertical bar separates alternate rules for the same result
    Given the grammar file:
      """
      %%
      exp: 'a' | 'b' | 'c';
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
        Alternative
          SymbolItem 'b'
        Alternative
          SymbolItem 'c'
      """

  @manual:Table-of-Symbols
  Scenario: <*> and <> in %destructor and %printer stand for all typed and all untyped symbols
    Given the grammar file:
      """
      %destructor { free ($$); } <*>
      %printer { print ($$); } <>
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      CodeProps destructor { free ($$); } <*>
      CodeProps printer { print ($$); } <>
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:Table-of-Symbols
  Scenario: /* ... */ and // ... are comments
    Given the grammar file:
      """
      /* a comment */ %token NUM // another one
      %%
      exp: NUM; /* and one more */
      """
    When the file is parsed
    Then the tree is the same as for:
      """
      %token NUM
      %%
      exp: NUM;
      """

  @manual:Table-of-Symbols
  Scenario: %empty, %prec, %dprec and %merge are directives written among the components
    Given the grammar file:
      """
      %glr-parser
      %%
      exp:
        %empty
      | '-' exp %prec NEG
      | exp '+' exp %dprec 1 %merge <merge>
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      Flag glr-parser
      %%
      Rule exp
        Alternative
          EmptyItem
        Alternative
          SymbolItem '-'
          SymbolItem exp
          PrecItem NEG
        Alternative
          SymbolItem exp
          SymbolItem '+'
          SymbolItem exp
          DprecItem 1
          MergeItem <merge>
      """

  @manual:Table-of-Symbols
  Scenario: A rule directive cannot appear among the declarations
    Given the grammar file:
      """
      %prec NEG
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 1 column 1

  @manual:Table-of-Symbols
  Scenario: error is the reserved token for error recovery
    Given the grammar file:
      """
      %%
      stmts: %empty | stmts stmt | error ';';
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule stmts
        Alternative
          EmptyItem
        Alternative
          SymbolItem stmts
          SymbolItem stmt
        Alternative
          SymbolItem error
          SymbolItem ';'
      """

  @manual:Table-of-Symbols
  Scenario Outline: <declaration> is a declaration listed in the appendix
    Given the grammar file:
      """
      <declaration>
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      <node>
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

    Examples:
      | declaration                               | node                                                |
      | %error-verbose                            | Flag error-verbose                                  |
      | %glr-parser                               | Flag glr-parser                                     |
      | %initial-action { @$.begin.line = 1; }    | InitialAction { @$.begin.line = 1; }                |
      | %lex-param { int *nastiness }             | Param lex-param { int *nastiness }                  |
      | %parse-param { int *nastiness }           | Param parse-param { int *nastiness }                |
      | %param { int *nastiness }                 | Param param { int *nastiness }                      |
      | %printer { print ($$); } <*>              | CodeProps printer { print ($$); } <*>               |

  @manual:Table-of-Symbols
  Scenario: %precedence declares symbols with precedence only
    Given the grammar file:
      """
      %precedence THEN
      %precedence ELSE
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      PrecedenceDeclaration precedence
        SymbolEntry THEN
      PrecedenceDeclaration precedence
        SymbolEntry ELSE
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:Table-of-Symbols
  Scenario: %lex-param, %parse-param and %param take one or more braced argument declarations
    Given the grammar file:
      """
      %param { int *nastiness } { int *randomness }
      %parse-param {int *nastiness} {int *randomness}
      %lex-param {int *nastiness}
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Param param { int *nastiness } { int *randomness }
      Param parse-param {int *nastiness} {int *randomness}
      Param lex-param {int *nastiness}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:Table-of-Symbols
  Scenario: %parse-param requires braced code
    Given the grammar file:
      """
      %parse-param
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 2 column 1

  @manual:Table-of-Symbols
  Scenario: Positional and named references in the action code are kept verbatim
    Given the grammar file:
      """
      %%
      exp[res]: exp[l] '+' exp[r] { $res = $l + $r; $$ = $1 + $3; @$ = @1; @res = @[r]; $<int>$ = $<int>1; };
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp [res]
        Alternative
          SymbolItem exp [l]
          SymbolItem '+'
          SymbolItem exp [r]
          Action { $res = $l + $r; $$ = $1 + $3; @$ = @1; @res = @[r]; $<int>$ = $<int>1; }
      """
