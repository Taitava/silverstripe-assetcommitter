# silverstripe-assetcommitter

So, your SilverStripe `assets` directory has different content in your production server and in your development machine and you want to use a VCS to sync them? Or you just want to backup `assets` in a VCS as a crash plan? This module is designed just for that: it automatically commits uploaded/deleted/renamed files in the `assets` directory by listening to the `File` class's hooks. It can also push to a remote repository. It does not pull/merge from a remote repository, though. That's something that should be done manually.

You can use any version control system you want **as long as it's git**.

Will it support any other VCS's in the future? No plans, but if it will, it will require other developers' effort (pull requests welcome), as git is the only one I'm currently familiar with. But the module is designed in a way that should make it quite easy to introduce other VC systems too.

## Requirements

- SilverStripe framework 3.0.0 or greater (CMS not needed)
- [czproject/git-php](https://github.com/czproject/git-php) 3.17.1 or greater (automatically installed if you use composer)

## Installation & configuration

1. Install the module: `composer require taitava/silverstripe-assetcommitter`

2. Initialise a git repository in your `assets` directory if you haven't done so already: `cd assets && git init` (I highly recommend you to use a dedicated repo for your assets - don't mix it in the same repo where your storing your project's source code files). Also run `git config user.email "default@email.address" && git config --global user.name "Default User"`. This is to provide a default *author* name and email for commits where the module fails to determine the `author`. The module will override the `author` with the logged in user's email and name (by calling `git commit` with the `--author` parameter), but if no one is logged in, the default `author` will be used.

3. Make sure that your webserver has a permission to write to `assets/.git`!

4. Final fine-tuning via YAML configuration:
```yaml
GitAssetCommitter:
  repository_path: 'assets' # This should point to the 'assets' directory (which should be your repository's root directory.)
  push_to_after_committing: false # If you want commit's to be pushed to a remote repository, set this to a string like "origin master" or just "origin", otherwise set this to false.
  automatically_define_author: true # If true, the currently logged in CMS user's email and name will be used as an author of commits. If nobody is logged in, the default author of the repository will be used.
  supplement_empty_author_email: 'cms.user@localhost' # If a logged in user does not have an email address, use this instead. Has no effect if automatically_define_author is false.
  supplement_empty_author_name: 'CMS User' # If a logged in user does not have a name, use this instead. Has no effect if automatically_define_author is false.
  commit_file_creations: true
  commit_file_deletions: true
  commit_file_renamings: true # Also affects movings
  
AssetCommitterFactory:
  committer_class: GitAssetCommitter # If you want to write a custom class that should handle committing, define the fully qualified name of the class here. Most of the time you will not want to change this value. Note that you may need to copy the above configuration values and apply them to your new class in your YAML config file!
```

## Philosophy

This module is very simple and still under construction. Actually: I don't even have a real world project for this where I would use this. At least not at the moment. I made this just out of curiosity :). What it means is that you should be ware that this module is not yet tested in any real website/application and therefore it might contain bugs that have just not been noticed because of lack of usage/testing.

Now that the above thing is covered, let's check what this module **does do** and **does not do**:

What it does:
- The module commits new asset files, asset file deletions, and asset file renamings/movings. Each type can be disabled separately if you only want for example to add files, but not to delete them from the repo.
- You can configure it to automatically *push* changes to a remote repository, but it's not mandatory.
- It listens to hooks present in the `File` class of SilverStripe. It doesn't scan the filesystem for changes. So old, already existing files will not get committed.

What it doesn't do:
- It doesn't initialize the git repository for you. So you should run this in terminal: `cd assets && git init` and also configure a default author.
- Branches. It doesn't create them, nor checkout them. It always stays in the current branch, whatever it is.
- Pulling/merging. No, because it would not be able to handle merge conflicts. And because it would require it to also create database records for pulled new files (and to edit records of pulled renamed/moved files). It would be too risky to do without a nerd supervising the process.
- Modify anything in the SilverStripe database. No tables created or columns added. No runtime writings, so your data in the database should be kept in intact. But if your db still get's corrupted, please please please don't teleport into my office.
- It cannot be used for any other directory than the directory that is used for storing assets. It simply doesn't detect any changes by scanning the filesystem.

Important notes:
- Git is a complicated thing. Be prepared to check your repository every now and then to see if it's okay: working directory should be clean, branch should be the one you wanted, pushing should work, no untracked files should be there (.gitignore them). If something is screwed up, it's you who should screw it down again! :)
- If the remote branch contains commits not present in the local branch, *pushing* will fail. In other words: committing works, but all commits will only be kept in the local repository, and if the filesystem gets corrupted or a bastard steals your homebrewed server, all the "committed" assets are lost because they were not pushed to the remote repository. So do make sure that you dedicate a specific branch in the remote repository where only a single server/machine will push these automatic commits! No living nerd should commit to that branch anything else - or if they do, they should go to the machine/server where this module is running and *pull* changes from the remote.
- You should create a new repository for your `assets` folder that is separate from your project's source code. Three reasons: 1) If the module screws up the repository, I don't want to meet angry coders at my front door telling me how many kilometers of beloved code they have lost. 2) Automatic pushing will not work if the remote repository contains commits not present in the local repository (unless you use separate branches). 3) You need to give your webserver user (i.e. `www-data` or `apache`) write access to the `.git` directory in your repository directory.
- **See the TODO & BUGS section below for current problems.**

## Problems

### Uncaught GitException: Command 'git ...' failed (exit-code 128)
Note that the three dots are just a substitute for a more specific error message that you will get if you run into this problem.

You simply might be missing write permissions to the .git directory of your repository. Make sure that your webserver has permission to write to that directory.

But this is not always the reason for this error message. Unfortunately czproject/git-php does not report original error messages yelled by git (at least that's the case for czproject/git-php version 3.17.1). For further reference, please see [czproject/git-php issue #47](https://github.com/czproject/git-php/issues/47)

I've customised the library (by subclassing `GitRepository` class and `GitException` exception) to add more information to `GitException`. You should be able to see a "Command output:" part in the exception's `message`, which contains descriptive error messages from git commands.

I've also made [a pull request to bring my improvisations to the original library](https://github.com/czproject/git-php/pull/50). If it gets merged, I will remove my customisations from this module.

### How to provide username and password for commands

    This section is copied on 2020-01-14 from [czproject/git-php README.md](https://github.com/czproject/git-php/blob/b5e709f0e9c4e4e39b49d4e322f7fa73c1bb21dc/readme.md).
    
    > 1. use SSH instead of HTTPS - https://stackoverflow.com/a/8588786
    > 2. store credentials to Git Credential Storage
    >    http://www.tilcode.com/push-github-without-entering-username-password-windows-git-bash/
    >    https://help.github.com/articles/caching-your-github-password-in-git/
    >    https://git-scm.com/book/en/v2/Git-Tools-Credential-Storage
    > 3. insert user and password into remote URL - https://stackoverflow.com/a/16381160
    >    git remote add origin https://user:password@server/path/repo.git
    > 4. NOT IMPLEMENTED IN THIS MODULE: for push() you can use --repo argument - https://stackoverflow.com/a/12193555
    >    $git->push(NULL, array('--repo' => 'https://user:password@server/path/repo.git'));


## TODO & BUGS
### For a 1.0 release:
- Handle file overwriting. I'm not sure how it goes right now, will it first perform a deletion commit and then a creation commit? See [issue #2](https://github.com/Taitava/silverstripe-assetcommitter/issues/2).

### Can be done later:
- Make it possible to use a subfolder of `assets` as the repository root directory. See [issue #4](https://github.com/Taitava/silverstripe-assetcommitter/issues/4).
- Create a `BuildTask` that could be ran to init a git repository. This would also setup a default author for the repository.
- Create a `BuildTask` that could be ran to check that the git repository can be properly accessed (exists and is writable).

## Maintainer contact

 Jarkko Linnanvirta
 jarkko (at) taitavasti (dot) fi (in English or in Finnish)
