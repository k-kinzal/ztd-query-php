@manual
Feature: Context-Dependent Precedence
  Source: GNU Bison Manual, version 3.8.2, as doc/bison.texi of the bison-3.8.2
  release (https://ftp.gnu.org/gnu/bison/bison-3.8.2.tar.xz, also at
  https://cgit.git.savannah.gnu.org/cgit/bison.git/tree/doc/bison.texi?h=v3.8.2).
  Every scenario is tagged with the node of the manual it states, as
  "manual:" followed by the node name with dashes for spaces; online, that
  node is https://www.gnu.org/software/bison/manual/html_node/<node>.html in
  the edition the GNU project publishes (Bison 3.8.1 at the time of writing).
  The nodes stated in this feature:
    Chapter 5, The Bison Parser Algorithm
    5.4      Context-Dependent Precedence                 manual:Contextual-Precedence

  @manual:Contextual-Precedence
  Scenario: %prec after the components gives the rule the precedence of a terminal symbol
    Given the grammar file:
      """
      %left '+' '-'
      %left '*'
      %left UMINUS
      %%
      exp:
        exp '-' exp
      | '-' exp %prec UMINUS
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      PrecedenceDeclaration left
        SymbolEntry '+'
        SymbolEntry '-'
      PrecedenceDeclaration left
        SymbolEntry '*'
      PrecedenceDeclaration left
        SymbolEntry UMINUS
      %%
      Rule exp
        Alternative
          SymbolItem exp
          SymbolItem '-'
          SymbolItem exp
        Alternative
          SymbolItem '-'
          SymbolItem exp
          PrecItem UMINUS
      """

  @manual:Contextual-Precedence
  Scenario: The symbol of %prec may be a character or a string
    Given the grammar file:
      """
      %%
      exp: '-' exp %prec '*' | exp "**" exp %prec "^";
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem '-'
          SymbolItem exp
          PrecItem '*'
        Alternative
          SymbolItem exp
          SymbolItem "**"
          SymbolItem exp
          PrecItem "^"
      """

  @manual:Contextual-Precedence
  Scenario: %prec requires a terminal symbol
    Given the grammar file:
      """
      %%
      exp: '-' exp %prec;
      """
    When the file is parsed
    Then parsing fails at line 2 column 19

  @manual:Contextual-Precedence
  Scenario: %no-default-prec, written with a semicolon as in the manual, makes rules without %prec have no precedence
    Given the grammar file:
      """
      %no-default-prec;
      %%
      exp: '-' exp %prec UMINUS;
      """
    When the file is parsed
    Then the tree is:
      """
      Flag no-default-prec
      %%
      Rule exp
        Alternative
          SymbolItem '-'
          SymbolItem exp
          PrecItem UMINUS
      """

  @manual:Contextual-Precedence
  Scenario: %default-prec restores the default
    Given the grammar file:
      """
      %no-default-prec;
      %default-prec;
      %%
      exp: '-' exp;
      """
    When the file is parsed
    Then the tree is:
      """
      Flag no-default-prec
      Flag default-prec
      %%
      Rule exp
        Alternative
          SymbolItem '-'
          SymbolItem exp
      """
