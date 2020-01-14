<?php

// Support SilverStripe versions lower than 3.7:
if (!class_exists('SS_Object')) class_alias('Object', 'SS_Object');

abstract class AssetCommitter extends SS_Object implements AssetCommitterInterface
{
	private $count_new_commits = 0;

	/**
	 * AssetCommitters should call this method every time they have successfully created a commit. The exact counter value
	 * is not actually needed, but it's used to determine if there are commits that should be pushed to a remote server
	 * at the end of the application's execution flow. (Of course pushing will not be done if no pushing is configured in
	 * the application's YAML config files. See AssetCommitterInterface::isPushingEnabled() for more details).
	 */
	protected function newCommitCreated()
	{
		$this->count_new_commits++;
	}

	/**
	 * Indicates whether there are any new commits created by the AssetCommitter subclass that can be pushed to a remote
	 * repository.
	 *
	 * @return bool
	 */
	public function hasCreatedNewCommits()
	{
		return $this->count_new_commits > 0;
	}

	protected function getAbsoluteFilename(File $file)
	{
		return Director::getAbsFile($file->Filename);
	}
}
