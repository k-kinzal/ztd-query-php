@manual
Feature: Grammar Rules
  Source: "The Lemon Parser Generator", doc/lemon.html of SQLite version 3.47.2
  (https://sqlite.org/src/raw/doc/lemon.html?ci=version-3.47.2, published at
  https://sqlite.org/lemon.html). Every scenario is tagged with the section of
  the manual it states, as "manual:" followed by the section number.
  The sections stated in this feature:
    4.2    Grammar Rules                                manual:4.2

  The parser keeps the C code of an action verbatim: whether an alias is used
  in the action, which the manual says Lemon reports, is checked by Lemon when
  it translates the code, not by the reader of the grammar file.

  @manual:4.2
  Scenario: A rule is a nonterminal, the symbol ::=, a list of terminals and nonterminals, and a period
    Given the grammar file:
      """
      expr ::= expr PLUS expr.
      expr ::= expr TIMES expr.
      expr ::= LPAREN expr RPAREN.
      expr ::= VALUE.
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item expr
        Item PLUS
        Item expr
      Rule expr
        Item expr
        Item TIMES
        Item expr
      Rule expr
        Item LPAREN
        Item expr
        Item RPAREN
      Rule expr
        Item VALUE
      """

  @manual:4.2
  Scenario: The first letter of each symbol tells the one nonterminal of the example, expr, from its five terminals
    Given the grammar file:
      """
      expr ::= expr PLUS expr.
      expr ::= expr TIMES expr.
      expr ::= LPAREN expr RPAREN.
      expr ::= VALUE.
      """
    When the file is parsed
    Then the nonterminals are: expr
    And the terminals are: PLUS TIMES LPAREN RPAREN VALUE

  @manual:4.2
  Scenario: The right-hand side of a rule may be empty
    Given the grammar file:
      """
      list ::= list element.
      list ::= .
      """
    When the file is parsed
    Then the tree is:
      """
      Rule list
        Item list
        Item element
      Rule list
      """

  @manual:4.2
  Scenario: Rules may occur in any order; the first rule names the start symbol, which the tree keeps as its first rule
    Given the grammar file:
      """
      term ::= VALUE.
      expr ::= term.
      prog ::= expr.
      """
    When the file is parsed
    Then the tree is:
      """
      Rule term
        Item VALUE
      Rule expr
        Item term
      Rule prog
        Item expr
      """

  @manual:4.2
  Scenario: A rule is terminated by a period, so without one it runs into the next rule, whose ::= cannot stand on a right-hand side
    Given the grammar file:
      """
      expr ::= VALUE
      expr ::= NUM.
      """
    When the file is parsed
    Then parsing fails at line 2 column 6

  @manual:4.2
  Scenario: A rule needs the symbol ::= after its left-hand side
    Given the grammar file:
      """
      expr = VALUE.
      """
    When the file is parsed
    Then parsing fails at line 1 column 6

  @manual:4.2
  Scenario: The C code of an action is written in braces immediately after the period that closes the rule
    Given the grammar file:
      """
      expr ::= expr PLUS expr.   { printf("Doing an addition...\n"); }
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item expr
        Item PLUS
        Item expr
        Code { printf("Doing an addition...\\n"); }
      """

  @manual:4.2
  Scenario: An action may span several lines and is kept as written
    Given the grammar file:
      """
      expr ::= VALUE. {
        if (x) {
          y();
        }
      }
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item VALUE
        Code {\n  if (x) {\n    y();\n  }\n}
      """

  @manual:4.2
  Scenario: The yacc form of a rule, with -> and the positions $$, $1 and $3, is not a Lemon rule
    Given the grammar file:
      """
      expr -> expr PLUS expr  { $$ = $1 + $3; };
      """
    When the file is parsed
    Then parsing fails at line 1 column 6

  @manual:4.2
  Scenario: A $-numbered position in an action is C text to the reader and stands for no symbol
    Given the grammar file:
      """
      expr ::= expr PLUS expr.  { $$ = $1 + $3; }
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item expr
        Item PLUS
        Item expr
        Code { $$ = $1 + $3; }
      """

  @manual:4.2
  Scenario: A symbol in parentheses after a grammar symbol is a place holder for its value in the action
    Given the grammar file:
      """
      expr(A) ::= expr(B) PLUS expr(C).  { A = B+C; }
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr(A)
        Item expr(B)
        Item PLUS
        Item expr(C)
        Code { A = B+C; }
      """

  @manual:4.2
  Scenario: Place holders may be given to some symbols and not others
    Given the grammar file:
      """
      expr(A) ::= LPAREN expr(B) RPAREN.  { A = B; }
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr(A)
        Item LPAREN
        Item expr(B)
        Item RPAREN
        Code { A = B; }
      """

  @manual:4.2
  Scenario: A place holder may also be given to a terminal
    Given the grammar file:
      """
      expr(A) ::= VALUE(V).  { A = value(V); }
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr(A)
        Item VALUE(V)
        Code { A = value(V); }
      """

  @manual:4.2
  Scenario: A rule may have an action without any place holder
    Given the grammar file:
      """
      stmt ::= expr SEMI.  { count++; }
      """
    When the file is parsed
    Then the tree is:
      """
      Rule stmt
        Item expr
        Item SEMI
        Code { count++; }
      """
