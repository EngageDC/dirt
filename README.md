[![Build Status](https://travis-ci.org/EngageDC/dirt.svg)](https://travis-ci.org/EngageDC/dirt)

# dirt
Dirt is a slightly opinionated deployment tool for getting projects Done In Record Time -- designed with teams in mind.

> **Note:** Dirt is a work in progress and is not quite ready for showtime yet.

![Screenshot of dirt in action](http://i.imgur.com/Wd5hhQO.png)

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

The recommended via to install dirt is via git, this makes it easy to install any updates.

Clone this repository

    $ git clone git@github.com:EngageDC/dirt.git /usr/local/bin/dirt
	$ cd /usr/local/bin/dirt

Install dependencies with Composer

    $ composer install

Add dirt to your PATH so it can be used everywhere, e.g on OS X, you would do:
	
	$ echo "export PATH=/usr/local/bin/dirt:\$PATH" >> ~/.bash_profile

Now, create a dirt configuration. This is done by running:

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

## Additional screenshots
![Creating a new project](http://i.imgur.com/GLOkkIs.png)
