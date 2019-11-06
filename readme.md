SBS - Super Build Script
========================

SBS allows to build just parts of a mono repo in a CI environment.

Eg. you have a React application and a PHP backend in the same repository.
When the repository gets built, the PHP Unit tests get executed and also
the React Application gets built and tested. This always happens when you
push something to the master branch.

Building the React app when only a PHP file is modified is not needed. 

SBS allows to define conditions when a certain build step is needed and
executes only the needed parts.

The build steps are defined in a YAML file the root directory.

sbs.yml
-------

    composer:
        title: Composer dependencies
        cmd: "composer install"
        files:
            - composer.lock
        output: vendor 
        
 This defines a build step named `composer`. It gets only executed if
 the file `composer.lock` gets modified. In this case the command
 `composer install` gets executed.

When to build a build step
----------------------------
SBS decides if it has to build the build step according to the specification in sbs.yml
SBS can decide this according:
* hash of files or directories (use `files`)
* hash of last commit of a branch in a git repository (use `commit`) 

SBS stores the hashes of the build afterwards in a file called `sbs.built.json`
in the given `output`  directory of the build step. (eg `vendor/sbs.built.json`) in this case.
This is used to check if the build step is needed to be built in the next run.

SBS does this:
* Read all build steps
* Create hash (according to build step config) of build step
* Reads hash of last built step (if any)
* Runs the command if the new hash is different to the one of the last build
* Stores the new hash for the next run

 
Configuration reference
-----------------------

    buildstep:
        title: the title which gets displayed on building, defaults to build step name
        cmd: the command to build this build step, required
        timeout: seconds the command has to execute before it timeouts, defaults to 3600
        working_dir: if the working directory of the command is not the project root, specify it here
        output: where SBS writes the information which last state of the build step is built, required
        depends_on: name of other build step which hash gets included
        commit: tells SBS to look if there is a new commit hash on a certain repository
            repo: the link to the repository
            branch: the branch name
        files: list of files or directory                    
          
Installation and usage
----------------------

    composer require mmz-srf/sbs --dev

To create an initial sbs.yml:

    vendor/bin/sbs init

To build the build steps:
    
    vendor/bin/sbs

To build only one specific step:

    vendor/bin/sbs build buildStepName
 
To force to build all build steps

    vendor/bin/sbs --force