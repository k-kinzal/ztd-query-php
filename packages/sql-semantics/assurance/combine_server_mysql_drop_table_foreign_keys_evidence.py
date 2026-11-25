"""Replay the fixed native MySQL foreign-key table-removal ledgers."""

from combine_server_join_evidence import main
import run_server_mysql_drop_table_foreign_keys

if __name__ == "__main__":
    main(run_server_mysql_drop_table_foreign_keys)
