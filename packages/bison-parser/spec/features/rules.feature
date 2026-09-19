@manual
Feature: Grammar Rules
  Source: GNU Bison Manual, version 3.8.2, as doc/bison.texi of the bison-3.8.2
  release (https://ftp.gnu.org/gnu/bison/bison-3.8.2.tar.xz, also at
  https://cgit.git.savannah.gnu.org/cgit/bison.git/tree/doc/bison.texi?h=v3.8.2).
  Every scenario is tagged with the node of the manual it states, as
  "manual:" followed by the node name with dashes for spaces; online, that
  node is https://www.gnu.org/software/bison/manual/html_node/<node>.html in
  the edition the GNU project publishes (Bison 3.8.1 at the time of writing).
  The nodes stated in this feature:
    Chapter 3, Bison Grammar Files
    3.3.1    Syntax of Grammar Rules                      manual:Rules-Syntax
    3.3.2    Empty Rules                                  manual:Empty-Rules
    3.3.3    Recursive Rules                              manual:Recursion

  @manual:Rules-Syntax
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

  @manual:Rules-Syntax
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

  @manual:Rules-Syntax
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

  @manual:Rules-Syntax
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

  @manual:Rules-Syntax
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

  @manual:Rules-Syntax
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

  @manual:Rules-Syntax
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

  @manual:Rules-Syntax
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

  @manual:Rules-Syntax
  Scenario: At the top level, braced code must be terminated by } and not by a digraph
    Given the grammar file:
      """
      %%
      exp: 'a' { x (); %>
      ;
      """
    When the file is parsed
    Then parsing fails at line 2 column 10

  @manual:Rules-Syntax
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

  @manual:Rules-Syntax
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

  @manual:Empty-Rules
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

  @manual:Empty-Rules
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

  @manual:Empty-Rules
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

  @manual:Empty-Rules
  Scenario: Flagging a non-empty rule with %empty is an error
    Given the grammar file:
      """
      %%
      a: %empty 'b';
      """
    When the file is parsed
    Then parsing fails at line 2 column 4

  @manual:Empty-Rules
  Scenario: A midrule action followed by a component also makes the rule non-empty
    Given the grammar file:
      """
      %%
      a: %empty { x (); } 'b';
      """
    When the file is parsed
    Then parsing fails at line 2 column 4

  @manual:Empty-Rules
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

  @manual:Recursion
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

  @manual:Recursion
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

  @manual:Recursion
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
