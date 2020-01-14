<?php


interface AssetCommitterInterface
{
	public function CommitFileCreation(File $file);

	public function CommitFileReplacement(File $file);

	public function CommitFileDeletion(File $file);

	public function CommitFileRenaming(File $file, $old_name, $new_name);

	/**
	 * The AssetCommitter subclass should have a configuration option that can be used to enable/disable pushing new commits
	 * to a remote repository. This method should read the configuration value and indicate if pushing is enabled. The actual
	 * parameters used in the pushing process are not needed to be available outside of the AssetCommitter subclass, because
	 * the subclass will perform the actual pushing process completely when the PushToRemoteRepository() method gets called.
	 *
	 * @return bool
	 * @see PushToRemoteRepository()
	 */
	public function isPushingEnabled();

	/**
	 * Performs the process of delivering new commits to a configured remote repository. Takes no parameters as the repository
	 * should be configured in a way defined by the subclass itself.
	 */
	public function PushToRemoteRepository();

	/**
	 * Indicates whether there are any new commits created by the AssetCommitter subclass that can be pushed to a remote
	 * repository.
	 *
	 * @return bool
	 */
	public function hasCreatedNewCommits();

}
