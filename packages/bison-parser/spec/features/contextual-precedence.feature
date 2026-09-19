Feature: Context-Dependent Precedence
  GNU Bison 3.8.2 manual, chapter "The Bison Parser Algorithm", section
  "Operator Precedence", subsection "Context-Dependent Precedence".

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

  Scenario: %prec requires a terminal symbol
    Given the grammar file:
      """
      %%
      exp: '-' exp %prec;
      """
    When the file is parsed
    Then parsing fails at line 2 column 19

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
