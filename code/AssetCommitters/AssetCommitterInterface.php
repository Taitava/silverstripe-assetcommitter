<?php


interface AssetCommitterInterface
{
	public function CommitFileCreation(File $file);

	public function CommitFileDeletion(File $file);

	public function CommitFileRenaming(File $file, $old_name, $new_name);
}
