web: php artisan migrate --force && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=$PORT
worker: php artisan queue:work redis --tries=3 --timeout=90