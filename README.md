[![Build Status](https://travis-ci.org/EngageDC/dirt.svg)](https://travis-ci.org/EngageDC/dirt)

# dirt
Dirt is a slightly opinionated deployment and development workflow tool for getting projects Done In Record Time -- designed with teams in mind.

Dirt can assist you and your team with creating and configuring web projects as well as deploying them to staging and production environments. Dirt also handles database dumping, allowing you to easily sync data between dev/staging/production environments.

> **Note:** Dirt is a work in progress and is not quite ready for showtime yet.

![Screenshot of dirt in action](http://i.imgur.com/qAw55hK.gif)

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
- [Assumptions](#assumptions)

## Requirements
Dirt has been tested and is actively being used on OS X 10.10. It has however previously been used with Ubuntu Linux and Windows 7/8 and should still work on these platforms. Please open an issue if you experience any problems.

Before installing dirt, please make sure that your development machine has the following tools installed:

* [git](http://git-scm.com)
* [unzip](http://linux.die.net/man/1/unzip)
* [PHP 5.4+](http://php.net)
* [Composer](http://getcomposer.org/download/)

Per default, Dirt is configured to generate a Vagrantfile for you project. If you want use Vagrant, please make sure that the following tools are installed:

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
After installing dirt, you need to set up a configuration. Dirt allows you to set up a team specific and user specific configuration.

You *could* put everything in the user-specific configuration file only, but it might make sense to share some things such as the staging server hostname and alike.

## Team configuration
Team configuration is optional. The team configuration file is required to be stored in the `team/` folder in the Dirt root.

Using the default install location, that location would be: `/usr/local/bin/dirt/team/config.php`. If the file exists, it will be loaded automatically. The `team` directory doesn't exist per default, so it will need to be created.

GitLab Example:
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

GitHub Example:
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

GitLab w/ team config example:
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

GitHub w/ team config example:
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

GitHub example without a team config:
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
The only required parameter is the project name. A description and framework can optionally be specified. If the framework parameter is set, dirt will download and add that framework to the repository.

	$ dirt create [-f|--framework="..."] [-d|--description="..."] name

### Deployment
Dirt handles deployment to both the staging and production environment, the deployment process can be invoked by calling:

	$ dirt deploy staging|production

### Database dumping
This allows you to create a MySQL database dump of either the development or staging environment.

When creating a database dump from staging, you can optionally specify the `--i` option to import the database dump to your development server.

	$ dirt database:dump [-i|--import] dev|staging

## Creating a team configuration
When using dirt with a team, you might want to share common configuration with all team members.

## Assumptions
* Default database server is MySQL
* All developers have root access to the staging server via *sudo* (For adding a vhost config, reloading apache, etc.)
* Developers does not have root access on production
