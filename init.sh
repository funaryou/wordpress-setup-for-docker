#!/bin/bash

# wp-contentディレクトリが存在しない場合に作成
if [ ! -d "/var/www/html/wp-content" ]; then
    mkdir -p /var/www/html/wp-content
fi

# 必要なサブディレクトリを作成
mkdir -p /var/www/html/wp-content/uploads
mkdir -p /var/www/html/wp-content/plugins
mkdir -p /var/www/html/wp-content/themes
mkdir -p wordpress
mkdir -p wordpress/exports
mkdir -p wordpress/wp-content/posts wordpress/wp-content/custom-pages
mkdir -p wordpress/wp-content/plugins/wp-content-sync

# パーミッションを設定
chown -R www-data:www-data /var/www/html/wp-content
chmod -R 755 /var/www/html/wp-content 