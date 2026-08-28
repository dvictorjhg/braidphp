FROM nginx:mainline-alpine

RUN rm /etc/nginx/conf.d/default.conf \
	&& mkdir -p \
	/var/cache/nginx/client_temp \
	/var/cache/nginx/fastcgi_temp \
	/var/cache/nginx/proxy_temp \
	/var/cache/nginx/scgi_temp \
	/var/cache/nginx/uwsgi_temp \
	&& chown -R nginx:nginx /var/cache/nginx
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/nginx.conf /etc/nginx/conf.d/nginx.conf

USER nginx
