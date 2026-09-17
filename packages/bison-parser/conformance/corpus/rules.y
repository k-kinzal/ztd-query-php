/* Everything a right-hand side may hold. */
%token <tag> A B C D 'x' "str"
%union { int tag; }
%token <tag> T
%type <tag> mid rule start
%left '+'
%right '*'
%glr-parser
%expect-rr 1
%%
start: rule ;
rule
  : A B { $$ = 1; }
  | A[left] B[right] { $$ = $left + $right; }
  | A { mid(); } B { two(); } C { last(); }
  | A <tag>{ typed(); } B
  | A { $<tag>$ = 0; }[named] B { use($<tag>named); }
  | %empty
  | A %prec '*'
  | A B %prec '+' C
  | A %dprec 2
  | B %dprec 1
  | A %merge <merger>
  | A %expect 1
  | A %expect-rr 1
  | A %?{ predicate($1) } B
  | 'x' "str" '\n' '\t' '\\' '\'' '\x41' '\101' "with \"quotes\" and \\ backslash"
  | rule ';' rule
  ;
mid: A
   | B
   ;
%token LATE;
%type <tag> late;
late: LATE ;
%%
static void mid(void) {}
