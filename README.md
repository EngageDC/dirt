[![Build Status](https://travis-ci.org/EngageDC/dirt.svg)](https://travis-ci.org/EngageDC/dirt)

# dirt
[![Gitter](https://badges.gitter.im/Join Chat.svg)](https://gitter.im/EngageDC/dirt?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
dirt is a slightly opinionated deployment and development workflow tool for getting projects Done In Record Time -- designed with teams in mind.

dirt can assist you and your team with creating and configuring web projects as well as deploying them to staging and production environments. dirt also handles database dumping, allowing you to easily sync data between dev/staging/production environments.

dirt is an essential part of our workflow here at [Engage](http://enga.ge) and has been in use and under development since 2012.

![dirt in action](https://engage-assets.s3.amazonaws.com/dirtgif.gif)

## Contents
- [Contents](#contents)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Create project](#create-project)
  - [Deployment](#deployment)
  - [Database dumping](#database-dumping)
  - [Creating a team configuration](#creating-a-team-configuration)
- [Setting up a staging server](#setting-up-a-staging-server)
- [Assumptions](#assumptions)
- [Uninstalling](#uninstalling)

## Requirements
dirt has been tested and is actively being used on OS X 10.10. It has however previously been used with Ubuntu Linux and Windows 7/8 and should still work on these platforms. Please open an issue if you experience any problems.

Before installing dirt, please make sure that your development machine has the following tools installed:

* [git](http://git-scm.com)
* [unzip](http://linux.die.net/man/1/unzip)
* [PHP 5.4+](http://php.net)
* [Composer](http://getcomposer.org/download/)

Per default, dirt is configured to generate a Vagrantfile for you project. If you want use Vagrant, please make sure that the following tools are installed:

* [VirtualBox](https://www.virtualbox.org)
* [Vagrant](http://vagrantup.com)

## Installation
The recommended way to install dirt is via git, this makes it easy to install any updates.

Clone this repository

    $ git clone git@github.com:EngageDC/dirt.git /usr/local/bin/dirt

Install dependencies with Composer

	$ cd /usr/local/bin/dirt && composer install

Add dirt to your PATH so it can be used everywhere, e.g on OS X, you would do:
	
	$ echo "export PATH=/usr/local/bin/dirt:\$PATH" >> ~/.bash_profile

## Configuration
After installing dirt, you need to set up a configuration. dirt allows you to set up a team specific and user specific configuration.

You *could* put everything in the user-specific configuration file only, but it might make sense to share some things such as the staging server hostname and alike.

## Team configuration
Team configuration is optional. The team configuration file is required to be stored in the `team/` folder in the dirt root.

Using the default install location, that location would be: `/usr/local/bin/dirt/team/config.php`. If the file exists, it will be loaded automatically. The `team` directory doesn't exist per default, so it will need to be created.

### GitLab Example:
```php
<?php // /usr/local/bin/dirt/team/config.php
return [
  'environments' => [
    'dev' => [
      'domain_suffix' => '.local' // All projects will use the mysite.local format for dev
    ],
    'staging' => [
      'hostname' => 'stage.mycompany.com', // This is the SSH hostname for the staging server
      'port' => '22', // SSH Port
      'username' => null, // This will be overwritten by the user config
      'keyfile' => null, // This will be overwritten by the user config
      'domain_suffix' => '.stage.mycompany.com'  // All projects will use the mysite.stage.mycompany.com format for staging
    ],
    'production' => [
      'hostname' => 'prod.mycompany.com',
      'port' => '22', // SSH Port
      'username' => null, // This will be overwritten by the user config
      'keyfile' => null, // This will be overwritten by the user config
      'domain_suffix' => '.com'
    ]
  ],
  'scm' => [
    'type' => 'gitlab',
    'domain' => 'https://git.mycompany.com', // GitLab server URL
    'private_token' => null, // This will be overwritten by the user config
    'group_id' => 8 // Id for default GitLab group to use
  ]
];
```

### GitHub Example:
```php
<?php // /usr/local/bin/dirt/team/config.php
return [
  'environments' => [
    'dev' => [
      'domain_suffix' => '.local' // All projects will use the mysite.local format for dev
    ],
    'staging' => [
      'hostname' => 'stage.mycompany.com', // This is the SSH hostname for the staging server
      'port' => '22', // SSH Port
      'username' => null, // This will be overwritten by the user config
      'keyfile' => null, // This will be overwritten by the user config
      'domain_suffix' => '.stage.mycompany.com'  // All projects will use the mysite.stage.mycompany.com format for staging
    ],
    'production' => [
      'hostname' => 'prod.mycompany.com',
      'port' => '22', // SSH Port
      'username' => null, // This will be overwritten by the user config
      'keyfile' => null, // This will be overwritten by the user config
      'domain_suffix' => '.com'
    ]
  ],
  'scm' => [
    'type' => 'github',
    'organization' => 'mycompany'
  ]
];
```

## User configuration
The user configuration file is **required**. Any user configuration properties will **overwrite** the team configuration.

### GitLab w/ team config example:
```php
<?php // /Users/codemonkey/.dirt
return [
  'environments' => [
    'staging' => [
      'username' => 'codemonkey',
      'keyfile' => '/Users/codemonkey/.ssh/id_rsa',
      'mysql' => [
        'username' => 'codemonkey',
        'password' => 'MyS3cur3P4ssw0rd!'
      ],
    ],
    'production' => [
      'username' => 'codemonkey',
      'keyfile' => '/Users/codemonkey/.ssh/id_rsa'
    ]
  ],
  'scm' => [
    'type' => 'gitlab',
    'private_token' => 'oMqWQhfckG2pAY1jOv0i' // This string is bogus, put your own token here
  ]
];
```

### GitHub w/ team config example:
```php
<?php // /Users/codemonkey/.dirt
return [
  'environments' => [
    'staging' => [
      'username' => 'codemonkey',
      'keyfile' => '/Users/codemonkey/.ssh/id_rsa',
      'mysql' => [
        'username' => 'codemonkey',
        'password' => 'MyS3cur3P4ssw0rd!'
      ],
    ],
    'production' => [
      'username' => 'codemonkey',
      'keyfile' => '/Users/codemonkey/.ssh/id_rsa'
    ]
  ],
  'scm' => [
    'type' => 'github',
    'username' => 'codemonkey',
    'password' => 'MyS3cur3P4ssw0rd!'
  ]
];
```

### GitHub example without a team config:
```php
<?php // /Users/codemonkey/.dirt
return [
  'environments' => [
	  'dev' => [
      'domain_suffix' => '.local' // All projects will use the mysite.local format for dev
    ],
    'staging' => [
	  'hostname' => 'stage.mycompany.com', // This is the SSH hostname for the staging server
      'port' => '22', // SSH Port
      'username' => 'codemonkey',
      'keyfile' => '/Users/codemonkey/.ssh/id_rsa',
      'mysql' => [
        'username' => 'codemonkey',
        'password' => 'MyS3cur3P4ssw0rd!'
      ],
      'domain_suffix' => '.stage.mycompany.com'  // All projects will use the mysite.stage.mycompany.com format for staging
    ],
    'production' => [
      'hostname' => 'prod.mycompany.com',
      'port' => '22', // SSH Port
      'username' => 'codemonkey',
      'keyfile' => '/Users/codemonkey/.ssh/id_rsa',
      'domain_suffix' => '.com'
    ]
  ],
  'scm' => [
    'type' => 'github',
    'username' => 'codemonkey',
    'password' => 'MyS3cur3P4ssw0rd!'
  ]
];
```

## Usage

### Create project
The only required parameter is the project name. A description and framework can optionally be specified. If the framework parameter is set, dirt will download, configure and add that framework to the repository.

The `--skip-repository` option allows you to skip creating a GitHub/GitLab repository for the project, this is usually if you want to use a different remote or prefer to work with it locally only for the time being.

	$ create [-f|--framework="..."] [-d|--description="..."] [--skip-repository] name

### Deployment
dirt handles deployment to both the staging and production environment, the deployment process can be invoked by calling:

	$ deploy [-u|--undeploy] [-v|--verbose] [-y|--yes] [-n|--no] staging|production

Deploying till staging will do the following things:
* dirt will ensure that any local git changes will be added/committed/pushed (You will be prompted for a commit message if there is any changes)
* All changes will be merged to the *staging* branch

If this is the first time the project is being deployed:
* An Apache vhost file is being generated, syntax checked and Apache is being reloaded
* The git repository will be cloned to the staging server in `/var/www/sites/sitename`
* A MySQL database and user is generated for the site
* You will be prompted to optionally sync the dev database to staging

In all cases:
* The current framework (if any) is being configured -- e.g. for Laravel we will run `artisan migrate`, chmod the `storage` folder, etc.
* Latest changes are being synced using `git fetch` & `git reset`

If you specify the undeploy parameter, the process will be reversed. Meaning, the files will be deleted from the server, the MySQL account and database will be removed and the apache vhost file will be deleted as well. This is usually for removing a project from the staging server that you no longer wish to have there.

### Database dumping
This allows you to create a MySQL database dump of either the development or staging environment.

When creating a database dump from staging, you can optionally specify the `--i` option to import the database dump to your development server.

	$ dirt database:dump [-i|--import] dev|staging

## Setting up a staging server
We run Centos 6.5 on our staging server, but dirt should be easily adoptable to other systems as well. This is what dirt expects:

* SSH users are expected to have passwordless `sudo` access, so Apache can be reloaded and vhost files can be created.
* Deployed websites are stored in `/var/www/sites/`
* Apache vhost config files are created on a per-website basis and are stored in `/etc/httpd/sites-enabled`
* dirt users should have access to a MySQL account that allows creation of databases and users

## Assumptions
As mentioned in the introduction, dirt is opinionated to some extend. This is some of the assumptions we make:

* Staging server is running Apache (An apache vhost config file is created)
* Default database server is MySQL
* All developers have root access to the staging server via *sudo* (For adding a vhost config, reloading apache, etc.)
* Developers do not have root access on production
* Deploying to staging is heavily automated (creating database accounts, etc.). Deploying to production is however super simple and doesn't do much more than compress the staging files (excluding `.git`), copy them to production and extract them in the correct directory.

We warmly welcome changes that makes dirt less opinionated, so feel free to create an issue or pull request.

## Uninstalling
Should you no longer want dirt on your system, you will just need to delete a few files.

	rm -rf /usr/local/bin/dirt # Delete dirt installation
	rm -rf ~/.dirt # Delete dirt user config file
