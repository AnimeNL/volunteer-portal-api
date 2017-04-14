FROM registry.fedoraproject.org/fedora:26

VOLUME ["/code"]
RUN useradd docker -d /code -M
RUN chown docker:docker /code
RUN dnf update -y
RUN dnf install -y composer npm httpd mod_php

COPY start.sh /usr/local/bin/start.sh
COPY httpd.conf /usr/local/etc/httpd.conf

EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/start.sh"]
USER docker
WORKDIR /code
