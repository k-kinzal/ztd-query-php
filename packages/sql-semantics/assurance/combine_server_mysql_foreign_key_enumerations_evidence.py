"""Replay native MySQL enum and set foreign-key comparison receipts."""

from combine_server_join_evidence import main
import run_server_mysql_foreign_key_enumerations

if __name__ == "__main__":
    main(run_server_mysql_foreign_key_enumerations)
