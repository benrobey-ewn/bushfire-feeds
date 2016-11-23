<?php
	require 'cron.helper.php';
	if (($pid = cronHelper::lock()) !== FALSE) {

		shell_exec('php -f index_http.php');
		shell_exec('php -f api_bushfires.php');
		shell_exec('php -f api_traffic.php');

		cronHelper::unlock();
	}
?>