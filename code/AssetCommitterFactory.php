<?php

// Support SilverStripe versions lower than 3.7:
if (!class_exists('SS_Object')) class_alias('Object', 'SS_Object');

class AssetCommitterFactory extends SS_Object
{
	/**
	 * @conf string
	 */
	private static $committer_class = GitAssetCommitter::class;

	/**
	 * @return AssetCommitterInterface
	 * @throws AssetCommitterFactoryException
	 */
	public static function CreateCommitter()
	{
		$committer_class = static::config()->committer_class;
		if (!ClassInfo::classImplements($committer_class, AssetCommitterInterface::class))
		{
			$error = __METHOD__ . ': YAML configuration value for "' . static::class . '.committer_class" should be a class name that implements the ' . AssetCommitterInterface::class . ' interface.';
			if (class_exists($committer_class))
			{
				$error .= ' The class name "' . $committer_class . '" exists but is either a wrong class, or maybe the class is missing an "implements" clause.';
			}
			else
			{
				$error .= ' The class name "' . $committer_class . '" does not exist.';
			}
			throw new AssetCommitterFactoryException($error);
		}

		/** @var AssetCommitterInterface $committer */
		$committer = singleton($committer_class);
		return $committer;
	}
}

