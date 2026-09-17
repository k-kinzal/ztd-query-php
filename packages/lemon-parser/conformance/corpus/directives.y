// Every declaration Lemon accepts, in the forms it accepts.
%name CalcParse
%include { #include "calc.h" }
%include { /* a second include is appended */ }
%code { static int helper(void) { return 0; } }
%token_prefix TK_
%token_type {Token}
%default_type {Node*}
%extra_argument {Parse *pParse}
%extra_context {Ctx *ctx}
%syntax_error { syntaxError(pParse); }
%parse_accept { accepted(); }
%parse_failure { failed(); }
%stack_overflow { overflow(); }
%stack_size 500
%start_symbol program
%realloc myRealloc
%free myFree
%token_destructor { freeToken($$); }
%default_destructor { freeNode($$); }
%destructor expr { freeExpr($$); }
%type expr {Expr*}
%type term "Term*"
%fallback ID ABORT AFTER ASC.
%fallback.
%wildcard ANY.
%token SEMI LP RP.
%token.
%left OR.
%left AND.
%right NOT.
%nonassoc EQ NE.
%left PLUS MINUS.
%left STAR SLASH REM.
%token_class id ID|INDEXED.
%token_class number INTEGER FLOAT.
%token_class op |PLUS /MINUS STAR.
% name SpacedName
program ::= stmts.
stmts ::= .
stmts ::= stmts stmt SEMI.
stmt(A) ::= expr(B). { A = B; }
expr(A) ::= expr(B) PLUS|MINUS expr(C). { A = bin(B, C); }
expr(A) ::= expr(B) STAR|SLASH|REM expr(C). [STAR] { A = bin(B, C); }
expr(A) ::= MINUS expr(B). [NOT] { A = neg(B); }
expr(A) ::= NOT expr(B). { A = not(B); }
expr(A) ::= expr(B) EQ|NE expr(C). { A = cmp(B, C); }
expr(A) ::= expr(B) AND|OR expr(C). { A = logic(B, C); }
expr(A) ::= term(B). { A = B; }
expr ::= LP expr RP. {NEVER-REDUCE}
term(A) ::= id(X). { A = ident(X); }
term(A) ::= number(N). { A = num(N); }
term ::= ANY.
