# -*- mode: ruby -*-
# vi: set ft=ruby :


# Only tested with Vagrant + libvirtd

Vagrant.configure("2") do |config|
	config.vm.hostname = "brvneucore"
	config.vm.box = "generic/ubuntu1710"

	config.vm.network "forwarded_port", guest: 443, host: 443

	config.vm.synced_folder "./", "/var/www/bravecore"
	config.vm.network :private_network, ip: "192.168.121.4"
	
	# run setup script as root
	config.vm.provision "shell", inline: <<-SHELL
		export DEBIAN_FRONTEND=noninteractive

		usermod -a -G www-data vagrant

		apt update
		apt install -y curl git

		# setup swagger codegen
		apt install -y openjdk-8-jre-headless
		su vagrant -c 'mkdir ~/bin && cd ~/bin && wget https://oss.sonatype.org/content/repositories/releases/io/swagger/swagger-codegen-cli/3.0.0-rc0/swagger-codegen-cli-3.0.0-rc0.jar -q -O swagger-codegen.jar'

		# setup php + composer
		apt install -y php php-fpm php-mysql php-zip php-mbstring php-intl php-libsodium php-dom php-sqlite3 php-apcu

		php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"

		php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer

		# setup node
		apt install -y nodejs npm

		# install apache
		apt install apache2 -y

		# setup mysql
		apt install mariadb-server -y

		service mysql start

		mysql -e 'CREATE DATABASE IF NOT EXISTS core'
		# TODO should pass password in via env
		mysql -e "GRANT ALL PRIVILEGES ON core.* TO core@localhost IDENTIFIED BY 'braveineve'"

		cp /var/www/bravecore/apache2/010-bravecore.vagrant.conf /etc/apache2/sites-available/010-bravecore.conf

		a2enmod rewrite
		a2enmod ssl
		a2ensite default-ssl
		a2ensite 010-bravecore
		a2enmod proxy_fcgi setenvif
		a2enconf php7.1-fpm

		chmod 0777 /var/www/bravecore/var/logs
		chmod 0777 /var/www/bravecore/var/cache

		systemctl reload apache2

		# setup frontend stuff		
		su vagrant -c 'cd /var/www/bravecore/frontend  && npm i'

		su vagrant -c 'cd /var/www/bravecore/ && java -jar ~/bin/swagger-codegen.jar generate -i web/swagger.json -l typescript-fetch -o frontend/swagger'
		su vagrant -c 'cd /var/www/bravecore/frontend/swagger && npm i'

	SHELL

	# run the server as an unprivileged user
	config.vm.provision "up", type: "shell", run: "always", privileged: false, inline: <<-SHELL
		echo "starting server"

		cd /var/www/bravecore

		if [ ! -f .env ]; then
			echo '.env not setup'
			exit
		fi
		composer install
		vendor/bin/doctrine-migrations migrations:migrate
		vendor/bin/swagger --exclude bin,config,docs,var,vendor,web --output web

		java -jar ~/bin/swagger-codegen.jar generate -c frontend/swagger-options.json -i web/swagger.json -l typescript-fetch -o frontend/swagger
		cd frontend/swagger && npm i
		
		
		cd /var/www/bravecore/frontend && npm run build

		echo
		echo ------------------------------------
		echo -- server up at https://localhost --
		echo ------------------------------------
		echo For frontend rebuilding:
		echo you can either run npm run watch from the /frontend directory, or
		echo run it inside the vm: vagrant ssh -c 'cd /var/www/bravecore/frontend && npm run watch'

	SHELL
end
