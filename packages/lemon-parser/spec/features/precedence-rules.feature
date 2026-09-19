@manual
Feature: Precedence Rules
  Source: "The Lemon Parser Generator", doc/lemon.html of SQLite version 3.47.2
  (https://sqlite.org/src/raw/doc/lemon.html?ci=version-3.47.2, published at
  https://sqlite.org/lemon.html). Every scenario is tagged with the section of
  the manual it states, as "manual:" followed by the section number.
  The sections stated in this feature:
    4.3    Precedence Rules                             manual:4.3
    4.4.10 The %left directive                          manual:4.4.10
    4.4.12 The %nonassoc directive                      manual:4.4.12
    4.4.15 The %right directive                         manual:4.4.15

  How Lemon resolves conflicts with these precedences is the generator's
  work; the reader keeps the directives in file order, so that their
  relative order, which gives the precedence levels, is known.

  @manual:4.3
  Scenario: Precedence is assigned to terminals with %left, %right and %nonassoc, in ascending order of the directives
    Given the grammar file:
      """
      %left AND.
      %left OR.
      %nonassoc EQ NE GT GE LT LE.
      %left PLUS MINUS.
      %left TIMES DIVIDE MOD.
      %right EXP NOT.
      """
    When the file is parsed
    Then the tree is:
      """
      PrecedenceDeclaration left AND
      PrecedenceDeclaration left OR
      PrecedenceDeclaration nonassoc EQ NE GT GE LT LE
      PrecedenceDeclaration left PLUS MINUS
      PrecedenceDeclaration left TIMES DIVIDE MOD
      PrecedenceDeclaration right EXP NOT
      """

  @manual:4.3
  Scenario: A rule takes another precedence from a terminal in square brackets after the period and before any C code
    Given the grammar file:
      """
      expr ::= MINUS expr.  [NOT]
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item MINUS
        Item expr
        Precedence NOT
      """

  @manual:4.3
  Scenario: The precedence mark comes before the C code of the rule
    Given the grammar file:
      """
      expr(A) ::= MINUS expr(B).  [NOT] { A = -B; }
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr(A)
        Item MINUS
        Item expr(B)
        Precedence NOT
        Code { A = -B; }
      """

  @manual:4.3
  Scenario: The symbol of a precedence mark must be a terminal
    Given the grammar file:
      """
      expr ::= MINUS expr.  [expr]
      """
    When the file is parsed
    Then parsing fails at line 1 column 24

  @manual:4.4.10
  Scenario: Every terminal named after %left up to the next period gets the same left-associative precedence
    Given the grammar file:
      """
      %left PLUS MINUS
        TIMES.
      """
    When the file is parsed
    Then the tree is:
      """
      PrecedenceDeclaration left PLUS MINUS TIMES
      """

  @manual:4.4.10
  Scenario: A precedence directive is terminated by a period, so without one it runs into the next line
    Given the grammar file:
      """
      %left PLUS MINUS
      %left TIMES.
      """
    When the file is parsed
    Then parsing fails at line 2 column 1

  @manual:4.4.10
  Scenario: A precedence directive may name no terminal at all
    Given the grammar file:
      """
      %left .
      expr ::= VALUE.
      """
    When the file is parsed
    Then the tree is:
      """
      PrecedenceDeclaration left
      Rule expr
        Item VALUE
      """

  @manual:4.4.10
  Scenario: Only terminals can be given a precedence
    Given the grammar file:
      """
      %left expr.
      """
    When the file is parsed
    Then parsing fails at line 1 column 7

  @manual:4.4.12
  Scenario: %nonassoc assigns non-associative precedence to one or more terminals
    Given the grammar file:
      """
      %nonassoc EQ NE.
      """
    When the file is parsed
    Then the tree is:
      """
      PrecedenceDeclaration nonassoc EQ NE
      """

  @manual:4.4.15
  Scenario: %right assigns right-associative precedence to one or more terminals
    Given the grammar file:
      """
      %right EXP NOT.
      """
    When the file is parsed
    Then the tree is:
      """
      PrecedenceDeclaration right EXP NOT
      """
