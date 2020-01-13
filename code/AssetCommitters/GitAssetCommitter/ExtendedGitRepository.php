<?php


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
	 * Checks whether the given file is committed to the git repository or not.
	 *
	 * TODO: If [pull request #48 on czproject/git-php](https://github.com/czproject/git-php/pull/48) gets merged, remove this method and use the method provided by the pull request.
	 *
	 * @param string|File $filename
	 * @return bool
	 * @throws GitException
	 * @throws InvalidConfigurationException
	 */
	private function isFileInGit($filename)
	{
		if ($filename instanceof File)
		{
			$filename = $filename->Filename;
		}
		elseif (!is_string($filename))
		{
			throw new InvalidArgumentException(__METHOD__ . ': $filename should be either a string path to a file, or a File object whose Filename property should be used as a file path. This was passed instead: ' . print_r($filename, true));
		}

		try
		{
			$this->repository()->execute(['ls-files', '--error-unmatch', $filename]);
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

}
