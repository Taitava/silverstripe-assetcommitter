<?php

/**
 * Class AssetCommitterFileExtension
 *
 * @property $owner static|File
 */
class AssetCommitterFileExtension extends Extension
{
	/**
	 * Commits a new file.
	 */
	public function onAfterUpload()
	{
		if ($this->isFolder()) return; // Folder management is not supported at the moment.
		$this->committer()->CommitFileCreation($this->owner);
	}

	/**
	 * Commits a file deletion.
	 */
	public function onAfterDelete()
	{
		if ($this->isFolder()) return; // Folder management is not supported at the moment.
		$this->committer()->CommitFileDeletion($this->owner);
	}

	/**
	 * Commits a renamed/moved file.
	 */
	public function updateLinks($old_name, $new_name)
	{
		if ($this->isFolder()) return; // Folder management is not supported at the moment.
		$this->committer()->CommitFileRenaming($this->owner, $old_name, $new_name);
	}

	/**
	 * Helps to prevent doing any commits on folders, as this module is currently only designed for single file commits
	 * only. If a folder gets renamed, it should trigger separate commits for each file in it (for now). Same for deletion.
	 *
	 * @return bool
	 */
	private function isFolder()
	{
		return $this->owner instanceof Folder;
	}

	/**
	 * @var AssetCommitterInterface
	 */
	private $committer;
	private function committer()
	{
		if (!$this->committer)
		{
			$factory = new AssetCommitterFactory;
			$this->committer = $factory->CreateCommitter();
		}
		return $this->committer;
	}
}
