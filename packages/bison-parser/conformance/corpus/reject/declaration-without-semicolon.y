%token A
%%
start: A ;
%token LATE
%type <tag> late
late: LATE ;
