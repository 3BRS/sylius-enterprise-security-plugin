FROM ghcr.io/sylius/sylius-nginx:alpine

RUN echo "${WORKING_DIR}"

ENV WORKING_DIR=/srv/sylius/tests/Application

WORKDIR "${WORKING_DIR}"

# The base image ships /etc/nginx/templates/default.conf.template, and the stock
# nginx entrypoint (20-envsubst-on-templates.sh) renders every template into
# conf.d at container start - which overwrote a conf copied straight to conf.d,
# so this file never took effect. Copying over the template both fixes that and
# is what substitutes ${FASTCGI_PASS} below.
COPY ./nginx.conf /etc/nginx/templates/default.conf.template

ENV FASTCGI_PASS=php:9000

# To test PHP FastCGI, use `apk add fcgi-dev`, then `cgi-fcgi -bind -connect 127.0.0.1:9000` or `cgi-fcgi -bind -connect php:9000`
