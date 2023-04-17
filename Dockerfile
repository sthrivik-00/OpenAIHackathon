FROM ubuntu:14.04.3

RUN apt-get update

# common tools
RUN apt-get -y install vim unzip sysv-rc sysv-rc systemd wget lynx

# apache2
RUN DEBIAN_FRONTEND=noninteractive apt-get -y install apache2

# php
RUN DEBIAN_FRONTEND=noninteractive apt-get -y install php5 libapache2-mod-php5 php5-mcrypt php5-pgsql 

# other tools
RUN DEBIAN_FRONTEND=noninteractive apt-get -y install libpng-dev libfreetype6 bison flex libfreetype6-dev dbus
RUN DEBIAN_FRONTEND=noninteractive apt-get -y install phppgadmin pgadmin3 
RUN DEBIAN_FRONTEND=noninteractive apt-get -y install php5-dev
RUN DEBIAN_FRONTEND=noninteractive apt-get -y install curl


# Create an user
RUN useradd -g root -ms /bin/bash appuser && usermod -a -G sudo appuser
RUN echo "appuser ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers \
  && echo "set background=dark" >> /home/appuser/.vimrc \
  && echo "set background=dark" >> /root/.vimrc

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
RUN cp /etc/apache2/ports.conf /etc/apache2/ports.conf.orig
RUN cat /etc/apache2/ports.conf.orig | sed 's/Listen 80/Listen 8080/g' > /etc/apache2/ports.conf
RUN chown -R appuser:root /run/apache2 /var/log/apache2 /var/www/html /usr/lib/php5
RUN chmod -R 777 /var/lock/apache2 /var/log/apache2 /var/run/apache2 /var/www
RUN a2enmod rewrite

# post installation
COPY setup/php.ini /etc/php5/apache2/
COPY setup/phppgadmin.conf /etc/apache2/conf-enabled/
COPY setup/Connection.php /usr/share/phppgadmin/classes/database/
COPY setup/config.inc.php /home/appuser/
RUN chown appuser:root /home/appuser/config.inc.php
RUN rm -f /etc/phppgadmin/config.inc.php && ln -s /home/appuser/config.inc.php /etc/phppgadmin/config.inc.php

COPY setup/libming-ming-0_4_8.zip /home/appuser/
COPY setup/www.zip /var/www/
RUN chown -R appuser:root /home/appuser /var/www/www.zip \
    && mkdir /log \
    && chmod -R 777 /log /home/appuser /usr/share/phppgadmin/classes/database/Connection.php

ENV APACHE_RUN_USER appuser
ENV APACHE_RUN_GROUP root

EXPOSE 8080

USER appuser

#----------- Build php extension
RUN cd /home/appuser \
 && unzip -q libming-ming-0_4_8.zip \
 && cd libming-ming-0_4_8 \
 && ./autogen.sh \
 && ./configure --enable-php \
 && make \
 && cp /home/appuser/libming-ming-0_4_8/php_ext/.libs/ming.so \
   /home/appuser/libming-ming-0_4_8/src/.libs/libming.so.1 \
   /home/appuser/libming-ming-0_4_8/src/.libs/libming.so.1.4.7 \
   /usr/lib/php5/20121212/ \
 && chown appuser:root /usr/lib/php5/20121212/*ming*


#---------- Uncompress videobill application
RUN cd /var/www \
    && unzip www.zip \
    && mv www/* . \
    && rmdir www \
    && rm www.zip \
    && chmod -R 777 *

#---------- Entrypoint
CMD . /etc/apache2/envvars \
    && /usr/sbin/apache2 -k start \
    && tail -F /var/log/apache2/access.log
