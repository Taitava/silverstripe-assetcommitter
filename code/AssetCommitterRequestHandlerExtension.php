<?php

/**
 * Class AssetCommitterRequestHandlerExtension
 *
 * @property $owner static|RequestHandler
 */
class AssetCommitterRequestHandlerExtension extends Extension
{
	/**
	 * This hook method handles all tasks that should be performed at the end of the execution flow. In practise this means
	 * pushing new commits to a remote repository, which needs to be done as a batch job, not repeatedly after each commit.
	 *
	 * @throws AssetCommitterFactoryException
	 */
	public function afterCallActionHandler()
	{
		$committer = AssetCommitterFactory::getCommitter();

		// Check if there are any pushable commits
		if ($committer->isPushingEnabled() && $committer->hasCreatedNewCommits())
		{
			$committer->PushToRemoteRepository();
		}
	}
}
