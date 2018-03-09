#!/usr/bin/php7.1
<?php
$color = 0;
$program_name = `basename -z $argv[0]`;

function color() {
	global $color;
	if (++$color > 7) $color = 1;
	return $color;
}

function show_help() {
	global $program_name;
	echo "Usage: $program_name DB_NAME [OPTIONS]

Arguments:
  -s                  Don't display strings starting with #
  -b, --nocolor       Disable colored output.
  -u, --user=NAME     Set database name to grep.
  -i, --stdin         Get log from stdin (use with tail -n500 + pipeline).
  -t, --time          Display date and time of a query.
      --help          Show this help and exit.

GitHub: https://github.com/exelestor/mysql-slow-log-parser
";
	exit();
}

$shortopts = 'su:bti';

$longopts = [
	'user:',
	'nocolor',
	'help',
	'time',
	'stdin'
];

$options = getopt($shortopts, $longopts);

$info = !isset($options['s']);

if (isset($options['help'])) show_help();

$color_mode = !(isset($options['nocolor']) || isset($options['b']));

$user = @$options['user'] ?? @$options['u'] ?? show_help();

$handle = (isset($options['i']) || isset($options['stdin'])) ?
	STDIN :
	fopen('/var/log/mysql/mysqld-slow.log', 'r');

$date_set = (isset($options['time']) || isset($options['t']));

$str = fgets($handle);

$i = true;
while (!feof($handle)) {

	$buffer = '';
	if (strpos($str, "# User@Host: ") !== false) {

		if (preg_match("/\[$user\]/", $str)) {
			if ($i) $i = !$i;

			if (strpos($str, '# ') === 0) {
				if ($info) $buffer .= $str;
			} else 
				$buffer .= $str;

			$str = fgets($handle);

			while (strpos($str, "# User@Host: ") === false &&
				strpos($str, "# Time: ") === false) {

				if ($date_set && strrpos($str, 'SET timestamp=') !== false)
					$buffer = date('Y-m-d H:i:s', substr($str, 14, -2)) . PHP_EOL . $buffer;

				if (strpos($str, '# ') === 0) {
					if ($info) $buffer .= $str;
				} else 
					$buffer .= $str;

				$str = fgets($handle) or die();
			}

			if ($color_mode) $buffer = "\033[3" . color() . "m" . $buffer . "\033[0m";
			$buffer .= PHP_EOL;
			echo $buffer;
		} else $str = fgets($handle);

	} else $str = fgets($handle);

}

if ($i) echo "No matches.\n";

fclose($handle);
