<?php
/**
 * WP-CLI stubs for Psalm static analysis.
 *
 * @package JAT_Search_Intel
 */

namespace {
    /**
     * Base class for WP-CLI commands.
     */
    class WP_CLI_Command {}

    /**
     * WP-CLI runner.
     */
    class WP_CLI {
        /**
         * @param string $name
         * @param string|object $callable
         * @param array $args
         */
        public static function add_command( string $name, $callable, array $args = [] ): void {}

        /**
         * @param string $message
         */
        public static function log( string $message ): void {}

        /**
         * @param string $message
         */
        public static function success( string $message ): void {}

        /**
         * @param string $message
         * @return never
         */
        public static function error( string $message ): void {}

        /**
         * @param string $message
         */
        public static function warning( string $message ): void {}

        /**
         * @param string $message
         */
        public static function line( string $message = '' ): void {}
    }
}

namespace WP_CLI\Utils {
    /**
     * @param array $assoc_args
     * @param string $flag
     * @param mixed $default
     * @return mixed
     */
    function get_flag_value( array $assoc_args, string $flag, $default = null ) {
        return $assoc_args[ $flag ] ?? $default;
    }

    /**
     * @param string $message
     * @param int $count
     * @return object
     */
    function make_progress_bar( string $message, int $count ) {
        return new class {
            public function tick(): void {}
            public function finish(): void {}
        };
    }

    /**
     * @param string $format
     * @param array $items
     * @param array|string $fields
     */
    function format_items( string $format, array $items, $fields ): void {}
}
