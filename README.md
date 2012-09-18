
# MADden 
### A Project for CIS 6930 Data Science

## Collaborators
### Morgan Bauer
### Christan Grant
### Joir-dan Gumbs

We will explore the power of postgres and the madlib package.
We combine Machineeen Learning and SQL to allow a user to perform interesting
queries.

We obtain data from twitter and CBSSports.com play-by-play pages.


## Running the code locally (Ubuntu 12.04+)

    sudo apt-get install php5 php5-pgsql

You need to use php 5.4 or higher so run `php -v` to check the version number.
If you don't have that version you can add an external paper repository such
as one from ondrej. Be sure to thank him.

    sudo add-apt-repository ppa:ondrej/php5

Now clone the repository from git gub.

    sudo apt-get install git
		git clone https://github.com/cegme/MADden.git

Now we can start a php server locally.

    cd MADden/web
		php -S localhost:8080

Now point your web browser to http://localhost:8080 ahd have fun!


