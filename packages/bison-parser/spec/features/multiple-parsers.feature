Feature: Multiple Parsers in the Same Program
  GNU Bison 3.8.2 manual, chapter "Bison Grammar Files", section "Multiple
  Parsers in the Same Program".

  Scenario: api.prefix renames the interface, and %code provides may declare the scanner accordingly
    Given the grammar file:
      """
      %define api.prefix {c}
      // Emitted in the header file, after the definition of YYSTYPE.
      %code provides
      {
        // Tell Flex the expected prototype of yylex.
        #define YY_DECL                             \
          int clex (CSTYPE *yylval, CLTYPE *yylloc)

        // Declare the scanner.
        YY_DECL;
      }
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Define api.prefix = {c}
      Code provides {\n  // Tell Flex the expected prototype of yylex.\n  #define YY_DECL                             \\\n    int clex (CSTYPE *yylval, CLTYPE *yylloc)\n\n  // Declare the scanner.\n  YY_DECL;\n}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario: The obsolete %name-prefix takes the prefix as a string
    Given the grammar file:
      """
      %name-prefix "c"
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Option name-prefix "c"
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """
