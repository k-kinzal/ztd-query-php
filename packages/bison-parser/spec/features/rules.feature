Feature: Grammar Rules
  GNU Bison 3.8.2 manual, chapter "Bison Grammar Files", section "Grammar
  Rules" with its subsections "Syntax of Grammar Rules", "Empty Rules" and
  "Recursive Rules".

  Scenario: A rule is a result, a colon, its components, and a semicolon
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

  Scenario: White space in rules only separates symbols, so any amount may be added
    Given the grammar file:
      """
      %%
      exp:exp   '+'
          exp
        ;
      """
    When the file is parsed
    Then the tree is the same as for:
      """
      %%
      exp: exp '+' exp;
      """

  Scenario: Actions are braced code and may be scattered among the components
    Given the grammar file:
      """
      %%
      exp: { first (); } exp { second (); } '+' exp { third (); };
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          Action { first (); }
          SymbolItem exp
          Action { second (); }
          SymbolItem '+'
          SymbolItem exp
          Action { third (); }
      """

  Scenario: Braced code may contain any sequence of C tokens as long as its braces are balanced
    Given the grammar file:
      """
      %%
      exp: 'a' { if ($1) { $$ = $1; } else { $$ = 0; } };
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
          Action { if ($1) { $$ = $1; } else { $$ = 0; } }
      """

  Scenario: Bison does not check braced code for correctness, it merely copies it
    Given the grammar file:
      """
      %%
      exp: 'a' { this is not C at all !!! };
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
          Action { this is not C at all !!! }
      """

  Scenario: Braces within comments do not affect the balance
    Given the grammar file:
      """
      %%
      exp: 'a' { /* } */ x (); // }
      };
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
          Action { /* } */ x (); // }\n}
      """

  Scenario: Braces within string literals and character constants do not affect the balance
    Given the grammar file:
      """
      %%
      exp: 'a' { s = "}"; c = '}'; };
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
          Action { s = "}"; c = '}'; }
      """

  Scenario: The C digraphs <% and %> count as braces
    Given the grammar file:
      """
      %%
      exp: 'a' { <% x (); %> };
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
          Action { <% x (); %> }
      """

  Scenario: At the top level, braced code must be terminated by } and not by a digraph
    Given the grammar file:
      """
      %%
      exp: 'a' { x (); %>
      ;
      """
    When the file is parsed
    Then parsing fails at line 2 column 10

  Scenario: Several rules for the same result may be joined with the vertical bar
    Given the grammar file:
      """
      %%
      exp:
        NUM
      | exp '+' exp
      | exp '*' exp
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem NUM
        Alternative
          SymbolItem exp
          SymbolItem '+'
          SymbolItem exp
        Alternative
          SymbolItem exp
          SymbolItem '*'
          SymbolItem exp
      """

  Scenario: The same rules may be written separately
    Given the grammar file:
      """
      %%
      exp: NUM;
      exp: exp '+' exp;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem NUM
      Rule exp
        Alternative
          SymbolItem exp
          SymbolItem '+'
          SymbolItem exp
      """

  Scenario: A rule whose right-hand side is empty is an empty rule
    Given the grammar file:
      """
      %%
      semicolon.opt: | ";";
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule semicolon.opt
        Alternative
        Alternative
          SymbolItem ";"
      """

  Scenario: %empty makes it explicit that a rule is empty on purpose
    Given the grammar file:
      """
      %%
      semicolon.opt:
        %empty
      | ";"
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule semicolon.opt
        Alternative
          EmptyItem
        Alternative
          SymbolItem ";"
      """

  Scenario: For POSIX Yacc compatibility, a comment /* empty */ is customary instead of %empty
    Given the grammar file:
      """
      %%
      semicolon.opt:
        /* empty */
      | ";"
      ;
      """
    When the file is parsed
    Then the tree is the same as for:
      """
      %%
      semicolon.opt: | ";";
      """

  Scenario: Flagging a non-empty rule with %empty is an error
    Given the grammar file:
      """
      %%
      a: %empty 'b';
      """
    When the file is parsed
    Then parsing fails at line 2 column 4

  Scenario: A midrule action followed by a component also makes the rule non-empty
    Given the grammar file:
      """
      %%
      a: %empty { x (); } 'b';
      """
    When the file is parsed
    Then parsing fails at line 2 column 4

  Scenario: An empty rule flagged with %empty may still have its action
    Given the grammar file:
      """
      %%
      a: %empty { $$ = 0; };
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule a
        Alternative
          EmptyItem
          Action { $$ = 0; }
      """

  Scenario: A rule is left recursive when its result is the leftmost component
    Given the grammar file:
      """
      %%
      expseq1:
        exp
      | expseq1 ',' exp
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule expseq1
        Alternative
          SymbolItem exp
        Alternative
          SymbolItem expseq1
          SymbolItem ','
          SymbolItem exp
      """

  Scenario: A rule is right recursive when its result is the rightmost component
    Given the grammar file:
      """
      %%
      expseq1:
        exp
      | exp ',' expseq1
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule expseq1
        Alternative
          SymbolItem exp
        Alternative
          SymbolItem exp
          SymbolItem ','
          SymbolItem expseq1
      """

  Scenario: Rules may be mutually recursive through other nonterminals
    Given the grammar file:
      """
      %%
      expr:
        primary
      | primary '+' primary
      ;

      primary:
        constant
      | '(' expr ')'
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule expr
        Alternative
          SymbolItem primary
        Alternative
          SymbolItem primary
          SymbolItem '+'
          SymbolItem primary
      Rule primary
        Alternative
          SymbolItem constant
        Alternative
          SymbolItem '('
          SymbolItem expr
          SymbolItem ')'
      """
