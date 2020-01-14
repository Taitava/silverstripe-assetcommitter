<?php


use Cz\Git\GitException;

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
		$absolute_filename = $this->getAbsoluteFilename($file);
		if ($this->repository()->isFileIgnored($absolute_filename)) return; // Do not try to commit ignored files
		$this->reset_git_stage();

		$this->repository()->addFile($absolute_filename);
		$commit_message = _t('GitAssetCommitter.CommitMessage.FileCreation', 'Create file {filename}.', '', ['filename' => $file->Filename]);
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
		$absolute_filename = $this->getAbsoluteFilename($file);
		if (!$this->repository()->isFileInGit($absolute_filename)) return; // If the file does not exist in the repository, it can't be removed from there, so nothing to do.
		$this->reset_git_stage();

		$this->repository()->removeFile($absolute_filename); // The file is actually already deleted from the disk before calling this.
		$commit_message = _t('GitAssetCommitter.CommitMessage.FileDeletion', 'Delete file {filename}.', '', ['filename' => $file->Filename]);
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

		$absolute_old_name = Director::getAbsFile($old_name);
		$absolute_new_name = Director::getAbsFile($new_name);
		$old_name_ignored = $this->repository()->isFileIgnored($absolute_old_name, true);
		$new_name_ignored = $this->repository()->isFileIgnored($absolute_new_name, true);
		$is_old_name_in_git = $this->repository()->isFileInGit($absolute_old_name);
		if (!$is_old_name_in_git && $new_name_ignored) return; // Old filename is not committed in the past, and new filename is ignored, so cancel because there would not be any deletion or addition to commit.

		// Are we moving or just renaming?
		if (dirname($absolute_old_name) === dirname($absolute_new_name))
		{
			$verb = _t('GitAssetCommitter.CommitMessage.FileRenaming_Verb_Rename', 'Rename');
		}
		else
		{
			$verb = _t('GitAssetCommitter.CommitMessage.FileRenaming_Verb_Move', 'Move');
		}

		$base_commit_message = _t('GitAssetCommitter.CommitMessage.FileRenaming', "{verb} file {old_filename} to {new_filename}.", '', ['verb' => $verb, 'old_filename' => $old_name, 'new_filename' => $new_name]);
		$extra_commit_message = '';
		$operations = [
			'delete_old' => false,
			'create_new' => false,
		];

		// Check if the old file actually is in the repository
		if ($is_old_name_in_git)
		{
			// Normal case, rename the file in git.
			// No need to check whether the old name is ignored. We know it's not because the file is already committed in the past
			if ($new_name_ignored)
			{
				// The file will become ignored after the renaming, so commit this operation as a deletion only
				$operations['delete_old'] = true;
				$extra_commit_message = _t('GitAssetCommitter.CommitMessage.FileRenaming_NewNameIgnored', 'The new filename is excluded by a .gitignore file, so only a deletion is committed.');
			}
			else
			{
				// Do not use $this->repository()->renameFile() because it would call `git mv` which doesn't like that the source file is already renamed to the target.
				// We can safely call `git rm` and `git add` - git will figure out that this is still just about one file being given another name.
				$operations['delete_old'] = true;
				$operations['create_new'] = true;
			}
		}
		else
		{
			// The old file didn't exist in git. Create it!
			$operations['create_new'] = true;
			if ($old_name_ignored)
			{
				$extra_commit_message = _t('GitAssetCommitter.CommitMessage.FileRenaming_OldNameIgnored', 'The previous filename was excluded by a .gitignore file, so it appears as a new file in this commit.');
			}
			else
			{
				$extra_commit_message = _t('GitAssetCommitter.CommitMessage.FileRenaming_OldNameNotCommitted', 'The file was not previously committed in the repository, although it did exist in the filesystem, so it appears as a new file in this commit.');
			}
		}

		// Execute the operations
		if ($operations['delete_old']) $this->repository()->removeFile($absolute_old_name);
		if ($operations['create_new']) $this->repository()->addFile($absolute_new_name);
		$commit_message = $base_commit_message;
		if ($extra_commit_message) $commit_message .= PHP_EOL . $extra_commit_message;
		$this->commit($commit_message);
	}

	/**
	 * @var ExtendedGitRepository
	 */
	private $repository;

	/**
	 * @return ExtendedGitRepository|mixed
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
			$this->repository()->push($remote, [$branch]);
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
