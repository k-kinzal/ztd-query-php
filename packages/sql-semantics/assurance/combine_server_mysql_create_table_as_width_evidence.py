"""Reconcile the exact native query-backed InnoDB width ledgers."""

from combine_server_join_evidence import main
import run_server_mysql_create_table_as_width

if __name__ == "__main__":
    main(run_server_mysql_create_table_as_width)
