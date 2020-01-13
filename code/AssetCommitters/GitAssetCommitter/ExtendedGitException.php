<?php


use Cz\Git\GitException;

/**
 * Class ExtendedGitException
 *
 * TODO: Remove this class if [czproject/git-php pull request #50](https://github.com/czproject/git-php/pull/50) gets merged.
 *
 * The content of this class is copied from the above mentioned pull request.
 */
class ExtendedGitException extends GitException
{
	/**
	 * @param string $message A description about which particular command (with parameters) has failed.
	 * @param int $code A numeric exit code from the failed git command (if applicable).
	 * @param \Throwable|NULL $previous
	 * @param string[]|string $command_output stdout/stderr output text from exec(). Exec() passes an array of strings to the $output variable, but you can also pass a simple string value if you wish. If this is an array, the elements of the array will be joined to a single string using PHP_EOL as the separator.
	 */
	public function __construct($message = "", $code = 0, \Throwable $previous = null, $command_output = '')
	{
		if ($command_output) $message .= PHP_EOL . 'Command output: ' . PHP_EOL . static::array_to_string($command_output);

		parent::__construct($message, $code, $previous);
	}

	private static function array_to_string($array_or_string)
	{
		if (!is_array($array_or_string)) return $array_or_string;
		return implode(PHP_EOL, $array_or_string);
	}
}
