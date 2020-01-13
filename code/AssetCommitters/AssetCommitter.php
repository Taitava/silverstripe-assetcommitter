<?php

// Support SilverStripe versions lower than 3.7:
if (!class_exists('SS_Object')) class_alias('Object', 'SS_Object');

abstract class AssetCommitter extends SS_Object implements AssetCommitterInterface
{
	protected function getAbsoluteFilename(File $file)
	{
		return Director::getAbsFile($file->Filename);
	}
}
