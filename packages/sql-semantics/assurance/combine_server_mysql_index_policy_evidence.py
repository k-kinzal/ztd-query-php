"""Independently replay the native MySQL index policy and capacity evidence."""

from combine_server_join_evidence import main
import run_server_mysql_index_policy

if __name__ == "__main__":
    main(run_server_mysql_index_policy)
