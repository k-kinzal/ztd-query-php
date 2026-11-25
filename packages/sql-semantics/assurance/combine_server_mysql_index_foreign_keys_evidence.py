"""Replay native MySQL index dependency evidence under the fixed observer contract."""

from combine_server_join_evidence import main
import run_server_mysql_index_foreign_keys

if __name__ == "__main__":
    main(run_server_mysql_index_foreign_keys)
