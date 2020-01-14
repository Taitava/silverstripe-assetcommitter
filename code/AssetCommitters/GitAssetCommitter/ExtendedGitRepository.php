<?php


use Cz\Git\GitException;
use Cz\Git\GitRepository;

/**
 * Class ExtendedGitRepository
 *
 * The purpose of this subclass is to complement GitRepository with custom methods and (temporarily) override some methods
 * to improve error handling.
 */
class ExtendedGitRepository extends GitRepository
{
	/**
	 * TODO: Remove this overrider if [czproject/git-php pull request #50](https://github.com/czproject/git-php/pull/50) gets merged.
	 *
	 * @param string|string[]
	 * @return string[]  returns output
	 * @throws GitException
	 */
	public function execute($cmd)
	{
		if (!is_array($cmd))
		{
			$cmd = array($cmd);
		}

		array_unshift($cmd, 'git');
		$cmd = self::processCommand($cmd);

		$this->begin();
		exec($cmd . ' 2>&1', $output, $ret);
		$this->end();

		if ($ret !== 0)
		{
			throw new ExtendedGitException("Command '$cmd' failed (exit-code $ret).", $ret, NULL, $output); // ONLY THIS LINE IS MODIFIED
		}

		return $output;
	}

	/**
	 * TODO: Remove this overrider if [czproject/git-php pull request #50](https://github.com/czproject/git-php/pull/50) gets merged.
	 *
	 * @param string|array
	 * @return self
	 * @throws GitException
	 */
	protected function run($cmd/*, $options = NULL*/)
	{
		$args = func_get_args();
		$cmd = self::processCommand($args);
		exec($cmd . ' 2>&1', $output, $ret);

		if ($ret !== 0)
		{
			throw new ExtendedGitException("Command '$cmd' failed (exit-code $ret).", $ret, NULL, $output); // ONLY THIS LINE IS MODIFIED
		}

		return $this;
	}

	/**
	 * Checks whether the given file is committed to the git repository or not.
	 *
	 * TODO: If [pull request #48 on czproject/git-php](https://github.com/czproject/git-php/pull/48) gets merged, remove this method and use the method provided by the pull request.
	 *
	 * @param string $filename
	 * @return bool
	 * @throws GitException
	 */
	public function isFileInGit($filename)
	{
		try
		{
			$this->execute(['ls-files', '--error-unmatch', $filename]);
		}
		catch (GitException $git_exception)
		{
			switch ($git_exception->getCode())
			{
			case 1:
				// The `git ls-files --error-unmatch` command didn't find the given file in git and has yelled an error
				// number 1. This exception can be considered normal. We can just report that the file is not in git and
				// continue the execution normally.
				return false;
			break;
			default:
				// An unrecognised error has occurred. Rethrow the exception.
				throw $git_exception;
			}
		}
		// As the command didn't give any error code when exiting, it's a sign for us to know that the file _does_ exist in git.
		return true;
	}

	/**
	 * @param string $filename
	 * @param bool $tracked_files_are_not_ignored If true (default), files that are already staged or committed, will not be considered as ignored even if they match any patterns in .gitignore files. This is git's normal behaviour. If set to false, doesn't check the git's index at all, only checks the .gitignore files (good for debugging .gitignore files).
	 * @return bool
	 * @throws GitException
	 */
	public function isFileIgnored($filename, $tracked_files_are_not_ignored = true)
	{
		$command = ['check-ignore', '-q', $filename];
		if (!$tracked_files_are_not_ignored) $command[] = ' --no-index';
		try
		{
			$this->execute($command);
		}
		catch (GitException $git_exception)
		{
			switch ($git_exception->getCode())
			{
			case 1:
				// Not ignored
				return false;
			break;
			default:
				// An unrecognised error has occurred. Rethrow the exception.
				throw $git_exception;
			break;
			}
		}
		// As the command didn't give any error code when exiting, it's a sign for us to know that the file _is_ ignored.
		return true;
	}

	/**
	 * Checks whether the given file is modified.
	 *
	 * @param string $filename
	 * @return bool
	 * @throws GitException
	 */
	public function isFileModified($filename)
	{
		try
		{
			$this->execute(['ls-files', '-m', '--error-unmatch', $filename]);
		}
		catch (GitException $git_exception)
		{
			switch ($git_exception->getCode())
			{
			case 1:
				// The `git ls-files -m --error-unmatch` command didn't find the given file among modified files and has
				// yelled an error number 1. This exception can be considered normal. We can just report that the file is
				// not modified and continue the execution normally.
				return false;
			break;
			default:
				// An unrecognised error has occurred. Rethrow the exception.
				throw $git_exception;
			}
		}
		// As the command didn't give any error code when exiting, it's a sign for us to know that the file is modified.
		return true;
	}

}
