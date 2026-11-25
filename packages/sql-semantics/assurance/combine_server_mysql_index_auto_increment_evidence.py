"""Independently replay the native auto-increment backing key comparisons."""

from combine_server_join_evidence import main
import run_server_mysql_index_auto_increment

if __name__ == "__main__":
    main(run_server_mysql_index_auto_increment)
