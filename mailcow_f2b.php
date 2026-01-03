<?php
/**
 * Plugin to integrate Roundcube into Mailcow-Dockerizeds' Fail2Ban
 *
 * @version 1.0
 * @license MIT
 * 
 * Inspired by https://github.com/mattrude/rc-plugin-fail2ban
 * Get REDIS password from mailcow.conf and add it to roundcube
 * config as mailcow_f2b_redis_pass.
 */


class mailcow_f2b extends rcube_plugin {

        function init () {
                $this -> add_hook ('login_failed', [ $this, 'log_failed_attempt' ]);
        }

        function log_failed_attempt ($args) {
		// Try to get Redis connection data from Roundcube config
		$redis_host = rcmail::get_instance () -> config -> get ('mailcow_f2b_redis_host');
		$redis_port = rcmail::get_instance () -> config -> get ('mailcow_f2b_redis_port');
		$redis_pass = rcmail::get_instance () -> config -> get ('mailcow_f2b_redis_pass');

		// Use MailCow defaults for Redis connection if not set in Roundcube config
		if (is_null ($redis_host)) $redis_host = "redis-mailcow";
		if (is_null ($redis_port)) $redis_port = 6379;

		$log_entry = "roundcube: failed login from {$_SERVER['REMOTE_ADDR']} for user {$args['user']}";

		// Write log entry to PHP error log
		error_log ("{$log_entry}\n");

		// Open Redis connection
		$redis = new Redis ();
		try {
			$redis -> connect ($redis_host, $redis_port);
			$redis -> auth ($redis_pass);
		} catch (Exception $e) {
			error_log ("roundcube: failed to connect to redis database at {$redis_host}:{$redis_port} because " . $e -> getMessage () . "\n");
		}

		// Write log entry to Redis DB
		$redis -> publish ("F2B_CHANNEL", $log_entry);

		// Close Redis connection
		$redis -> close ();
        }

}
