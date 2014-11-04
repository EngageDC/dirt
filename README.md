# dirt
Dirt is a slightly opinionated deployment tool for getting projects Done In Record Time -- designed with teams in mind.

> **Note:** Dirt is a work in progress and is not quite ready for showtime yet.

![Screenshot of dirt in action](http://i.imgur.com/g54K4ey.png)

## Requirements
Before installing dirt, make sure that your development machines has the following tools installed:

* [VirtualBox](https://www.virtualbox.org)
* [Vagrant](http://vagrantup.com)
* [vagrant-hostmanager](https://github.com/smdahlen/vagrant-hostmanager)
	* `vagrant plugin install vagrant-hostmanager`
* [vagrant-triggers](https://github.com/emyl/vagrant-triggers)
	* `vagrant plugin install vagrant-triggers`
* [git](http://git-scm.com)
* [unzip](http://linux.die.net/man/1/unzip)
* [PHP (cli)](http://php.net)
* [Composer](http://getcomposer.org/download/)

## Installation
Clone this repository

    $ git clone git@github.com:EngageDC/dirt.git ~/bin/dirt
	$ cd ~/bin/dirt

Install dependencies with Composer

    $ composer install

(Optionally) add dirt to your PATH so it can be used everywhere, e.g on OS X, you would do:
	
	$ echo "export PATH=/Users/$USER/bin/dirt:\$PATH" >> ~/.bash_profile

## Usage
First a dirt configuration file must be created, this is done by running:

	$ dirt setup

A new project can then be created, the only required parameter is the project name. A description and framework can optionally be specified. If the framework parameter is set, dirt will download and add that framework to the repository.

	$ dirt create [-f|--framework="..."] [-d|--description="..."] name

Dirt handles deployment to both the staging and production environment, the deployment process can be invoked by calling:

	$ dirt deploy [environment]


## Additional screenshots
![Creating a new project](http://i.imgur.com/GLOkkIs.png)


## Assumptions
* MySQL Credentials for staging are shared between all projects
* MySQL Credentials for production are defined on a per-project basis
