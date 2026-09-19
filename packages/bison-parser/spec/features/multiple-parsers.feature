@manual
Feature: Multiple Parsers in the Same Program
  Source: GNU Bison Manual, version 3.8.2, as doc/bison.texi of the bison-3.8.2
  release (https://ftp.gnu.org/gnu/bison/bison-3.8.2.tar.xz, also at
  https://cgit.git.savannah.gnu.org/cgit/bison.git/tree/doc/bison.texi?h=v3.8.2).
  Every scenario is tagged with the node of the manual it states, as
  "manual:" followed by the node name with dashes for spaces; online, that
  node is https://www.gnu.org/software/bison/manual/html_node/<node>.html in
  the edition the GNU project publishes (Bison 3.8.1 at the time of writing).
  The nodes stated in this feature:
    Chapter 3, Bison Grammar Files
    3.8      Multiple Parsers in the Same Program         manual:Multiple-Parsers

  @manual:Multiple-Parsers
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

  @manual:Multiple-Parsers
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
