"""Replay native InnoDB declaration-width ledgers against the independent model."""

from combine_server_join_evidence import main
import run_server_mysql_innodb_declaration_width

if __name__ == "__main__":
    main(run_server_mysql_innodb_declaration_width)
