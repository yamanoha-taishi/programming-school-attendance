FROM node:22-alpine

RUN apk add --no-cache php84 php84-openssl php84-mbstring php84-tokenizer php84-xml php84-fileinfo php84-dom php84-pdo php84-curl php84-session \
    && ln -sf /usr/bin/php84 /usr/bin/php

WORKDIR /var/www/html