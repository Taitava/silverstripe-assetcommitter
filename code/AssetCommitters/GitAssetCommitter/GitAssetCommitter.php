<?php


use Cz\Git\GitException;
use Cz\Git\GitRepository;

class GitAssetCommitter extends AssetCommitter implements AssetCommitterInterface
{
	/**
	 * @conf string
	 */
	private static $repository_path = 'assets';

	/**
	 * If you want all commits to be pushed automatically to a remote repository, set this to for example "origin master"
	 * (remote name + branch name separated by a single space). Note that pushing is done immediately after each commit,
	 * which may execute multiple pushes in a short time if for example a folder containing many files is renamed/moved.
	 *
	 * @conf false|string
	 */
	private static $push_to_after_committing = false;

	/**
	 * If true, the currently logged in CMS user's email and name will be used as an author of commits. If nobody is logged
	 * in (for example when running tasks from the command line interface), the `--author` parameter is omitted when calling
	 * `git commit`, which makes git to use the default author of the repository.
	 *
	 * @config bool
	 */
	private static $automatically_define_author = true;

	/**
	 * When defining an author, this string is used as the email address in case if the user's email address happens to be
	 * empty. This setting will not be used if no user is logged in or if automatically_define_author is false.
	 *
	 * An empty string is not allowed here!
	 *
	 * @config string
	 */
	private static $supplement_empty_author_email = 'cms.user@localhost';

	/**
	 * When defining an author, this string is used as the person name in case if the user's name happens to be
	 * empty. This setting will not be used if no user is logged in or if automatically_define_author is false.
	 *
	 * An empty string is not allowed here!
	 *
	 * @config string
	 */
	private static $supplement_empty_author_name = 'CMS User';

	/**
	 * @conf bool
	 */
	private static $commit_file_creations = true;

	/**
	 * @conf bool
	 */
	private static $commit_file_deletions = true;

	/**
	 * @conf bool
	 */
	private static $commit_file_renamings = true;

	/**
	 * @param File $file
	 * @throws GitException
	 * @throws InvalidConfigurationException
	 * @throws GitAssetCommitterException
	 */
	public function CommitFileCreation(File $file)
	{
		if (!static::config()->commit_file_creations) return;
		$this->reset_git_stage();

		$this->repository()->addFile($this->getAbsoluteFilename($file));
		$commit_message = _t('GitAssetCommitter.CommitMessage.FileCreation', 'Created file {filename}.', '', ['filename' => $file->Filename]);
		$this->commit($commit_message);
	}

	/**
	 * @param File $file
	 * @throws GitException
	 * @throws InvalidConfigurationException
	 * @throws GitAssetCommitterException
	 */
	public function CommitFileDeletion(File $file)
	{
		if (!static::config()->commit_file_deletions) return;
		if (!$this->isFileInGit($file)) return; // If the file does not exist in the repository, it can't be removed from there, so nothing to do.
		$this->reset_git_stage();

		$this->repository()->removeFile($this->getAbsoluteFilename($file)); // The file is actually already deleted from the disk before calling this.
		$commit_message = _t('GitAssetCommitter.CommitMessage.FileDeletion', 'Deleted file {filename}.', '', ['filename' => $file->Filename]);
		$this->commit($commit_message);
	}

	/**
	 * @param File $file
	 * @param string $old_name
	 * @param string $new_name
	 * @throws GitException
	 * @throws InvalidConfigurationException
	 * @throws GitAssetCommitterException
	 */
	public function CommitFileRenaming(File $file, $old_name, $new_name)
	{
		if (!static::config()->commit_file_renamings) return;
		$this->reset_git_stage();

		// FIXME: This method does not work when handling a file that resides in a folder that has been renamed/moved. To put it in other words: the file itself has not been renamed or moved, but the container folder has been renamed/moved. In this case, git will yell "no changes added to commit". But our own check in self::commit() does indicate that there _is_ staged changes, so it proceeds with committing, but then git will say that there's nothing to commit.

		$absolute_old_name = Director::getAbsFile($old_name);
		$absolute_new_name = Director::getAbsFile($new_name);

		if ($this->isFileInGit($absolute_old_name))
		{
			// Normal case, rename the file in git.
			// Do not use $this->repository()->renameFile() because it would call `git mv` which doesn't like that the source file is already renamed to the target.
			// We can safely call `git rm` and `git add` - git will figure out that this is still just about one file being given another name.
			$this->repository()->removeFile($absolute_old_name);
			$this->repository()->addFile($absolute_new_name);
			$commit_message = _t('GitAssetCommitter.CommitMessage.FileRenaming', 'Renamed file {old_filename} to {new_filename}.', '', ['old_filename' => $old_name, 'new_filename' => $new_name]);
		}
		else
		{
			// The old file didn't exist in git. Create it!
			$this->repository()->addFile($absolute_new_name);
			$commit_message = _t('GitAssetCommitter.CommitMessage.FileRenaming_didntexistbefore', 'Renamed file {old_filename} to {new_filename}. Note that this file was not previously committed in the repository, so it appears as a new file in this commit although it\'s been around for a while.', '', ['old_filename' => $old_name, 'new_filename' => $new_name]);
		}
		$this->commit($commit_message);
	}

	/**
	 * @var GitRepository
	 */
	private $repository;

	/**
	 * @return GitRepository|mixed
	 * @throws InvalidConfigurationException
	 */
	private function repository()
	{
		if (!$this->repository)
		{
			$this->repository = Injector::inst()->createWithArgs(ExtendedGitRepository::class, [
				$this->repository_path(),
			]);
		}
		return $this->repository;
	}

	/**
	 * @return string
	 * @throws InvalidConfigurationException
	 */
	private function repository_path()
	{
		$repository_path = Director::getAbsFile(static::config()->repository_path);

		// Check that the directory exists
		if (!is_dir($repository_path) || $repository_path === '')
		{
			throw new InvalidConfigurationException(__METHOD__ . ': YAML configuration value for "' . static::class . '.repository_path" should be an existing directory. The path is currently: '.$repository_path);
		}

		// Check that git is initialized
		$git_path = join(DIRECTORY_SEPARATOR, [$repository_path, '.git']);
		if (!is_dir($git_path))
		{
			throw new InvalidConfigurationException(__METHOD__.': It seems that a git repository is not initialized in "'.$repository_path.'" because it doesn\'t contain a directory named ".git". You can try to run "git init" in command line in the repository directory. You should also define a default author for the new repository.');
		}

		return $repository_path;
	}

	/**
	 * @param string $commit_message
	 * @throws GitException
	 * @throws InvalidConfigurationException
	 * @throws GitAssetCommitterException
	 */
	private function commit($commit_message)
	{
		if (!$this->repository()->hasChanges())
		{
			// Nothing to commit
			throw new GitAssetCommitterException(__METHOD__.': No changes are staged to be committed.');
		}

		$commit_parameters = [];
		if (static::automatically_define_author() && $author = $this->getCommitAuthor())
		{
			// We can only have an author name if someone is logged in in the CMS
			$commit_parameters['--author'] = $author;
		}
		$this->repository()->commit($commit_message, $commit_parameters);

		// Push
		if ($push_to = static::config()->push_to_after_committing)
		{
			if (preg_match('/ /', $push_to))
			{
				// Both remote and branch are defined (separated by a space)
				[$remote, $branch] = explode(' ', $push_to);
			}
			else
			{
				// Only the remote is defined
				$remote = $push_to;
				$branch = null;
			}
			$this->repository()->push($remote, $branch);
		}
	}

	/**
	 * Return's the name and email address of the currently logged in user. If nobody is logged in in the CMS,
	 * return null. This is usually the best bet for the author: if we would use the owner of the file, it would probably
	 * work when uploading files, but could be incorrect when renaming or deleting files.
	 *
	 * @throws InvalidConfigurationException
	 */
	private function getCommitAuthor()
	{
		$user = Member::currentUser();
		if (is_object($user) && $user->exists())
		{
			$email = $user->Email;
			$name = $user->getName();

			// Make sure that the strings are not empty so that we will get an explicit author name. Otherwise `git commit` would use the author as a pattern to search for an author in previous commits.
			if (!$email) $email = static::config()->supplement_empty_author_email; // I think $email should never be empty, but just in case ...
			if (!$name) $name = static::config()->supplement_empty_author_name;
			if (!$email) throw new InvalidConfigurationException(__METHOD__.': YAML config value for "'.static::class.'.supplement_empty_author_email" should not be empty!');
			if (!$name) throw new InvalidConfigurationException(__METHOD__.': YAML config value for "'.static::class.'.supplement_empty_author_name" should not be empty!');
			return "$name <$email>";
		}
		return null;
	}

	private static function automatically_define_author()
	{
		return (bool) static::config()->automatically_define_author;
	}

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
			throw new InvalidArgumentException(__METHOD__ . ': $filename should be either a string path to a file, or a File object whose Filename property should be used as a file path. This was passed instead: '.print_r($filename, true));
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

	/**
	 * Makes sure that there's nothing staged. This is called before starting to add files to staging to make sure that
	 * we won't accidentally commit some odd changes not related to what we are doing.
	 *
	 * This method does not undo any changes in the working tree! So it's safe to use. Should be, at least. Don't blame me
	 * if it isn't. Blame someone else. I'm serious. If this method has a bug, I didn't write the method. Never. It was
	 * my cat who ran over the keyboard.
	 *
	 * @throws GitException
	 * @throws InvalidConfigurationException
	 */
	private function reset_git_stage()
	{
		if ($this->repository()->hasChanges())
		{
			$this->repository()->execute(['reset', '--mixed']);
		}
	}
}
