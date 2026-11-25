"""Replay native MySQL scalar foreign-key comparison receipts."""

from combine_server_join_evidence import main
import run_server_mysql_foreign_key_scalars

if __name__ == "__main__":
    main(run_server_mysql_foreign_key_scalars)
