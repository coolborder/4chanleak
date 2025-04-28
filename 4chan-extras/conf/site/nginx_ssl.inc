ssl_certificate /usr/local/www/ssl/4chan.org-combined.crt;
ssl_certificate_key /usr/local/www/ssl/4chan.org-nopw.key;
ssl_protocols               TLSv1 TLSv1.1 TLSv1.2;
ssl_ciphers                 EECDH+AES128:RSA+AES128:EECDH+AES256:RSA+AES256:EECDH+3DES:RSA+3DES:EECDH+RC4:RSA+RC4:!MD5;
ssl_prefer_server_ciphers   on;
ssl_stapling on;
ssl_buffer_size 4k;

resolver 127.0.0.1;
