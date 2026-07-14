# Project Context - Practica Login

## Infrastructure

### Servers

| Role | Public IP | VPC (eth0) | Private (eth1) | OS | RAM |
|------|-----------|------------|-----------------|-----|-----|
| Load Balancer | 143.110.237.225 | 10.48.0.8 | 10.124.0.5 | Rocky Linux 9.8 | 512MB+ |
| APP1 (Laravel) | 164.90.155.81 | 10.48.0.5 | 10.124.0.3 | Rocky Linux 9.x | ? |
| APP2 (Laravel) | 24.199.118.31 | 10.48.0.7 | 10.124.0.2 | Rocky Linux 9.x | ? |
| MySQL | 165.232.154.223 | 10.48.0.6 | 10.124.0.4 | Rocky Linux 9.x | ? |

### Network
- **VPC eth0**: 10.48.0.0/16
- **Private eth1**: 10.124.0.0/20
- Apps connect to MySQL via eth1 (10.124.0.x)
- LB connects to apps via eth1 (10.124.0.x)

### SSH Access
- All servers: root access via SSH key
- Keys loaded in Windows OpenSSH agent:
  - LOAD-BALANER-KEY-MANAGEMENT (LB)
  - MYSQL-MANAGEMENT-DATABASE (MySQL)
  - APP2-SSH-MANAGEMENT (APP2)
  - JUMPER_MANAGEMENT
  - eddsa-key-20260708
- Use: `C:/Windows/System32/OpenSSH/ssh.exe root@<IP>` (NOT git bash ssh)

---

## MySQL Server (165.232.154.223)

### Config
- Port: 53072
- bind-address: 0.0.0.0
- TLS: enabled (TLSv1.2, TLSv1.3)
- Certs: /etc/pki/ca/certs/ and /etc/pki/ca/private/
- CA cert: /etc/pki/ca/certs/ca.cert.pem
- Server cert: /etc/pki/ca/certs/mysql.cert.pem
- Server key: /etc/pki/ca/private/mysql.key.pem
- Owner: mysql:mysql
- require_secure_transport = ON

### MySQL Root Password
```
Q#Uor$vv7Avm%NSt@~jK&kL&ukWxmh%^e#nmRuAwKcCq3#@wgYt22W
```

### MySQL Users
| User | Host | SSL Type | Password |
|------|------|----------|----------|
| logindatabase | 10.124.0.2 | X509 | s4fzgmgHy4CkKhPheX^mT2Dw~iWsMV`3f`A^SK#FVgSKaohSQ |
| logindatabase | 10.124.0.3 | X509 | jA#c&Kx%bFi2EbrwSu^z3XwWwbQF%y2q$%%Q4UgUw&CT |
| root | localhost | - | (see above) |

### MySQL CA (on MySQL server)
- Location: /etc/pki/ca/
- CAs exist for: app1, app2, mysql, jumphost, ca
- Certs: /etc/pki/ca/certs/{ca,mysql,app1,app2}.cert.pem
- Keys: /etc/pki/ca/private/{ca,mysql,app1,app2}.key.pem

### SELinux
- Mode: Enforcing (config + runtime)
- Config file: /etc/selinux/config

### Firewalld
- Active, default zone: public
- Services: ssh
- Ports: 53072/tcp
- Rich rules: allow 53072 from 10.124.0.2 and 10.124.0.3 only
- Target: REJECT

---

## APP1 (164.90.155.81)

### Stack
- Laravel + PHP-FPM + Nginx 1.30.3
- App path: /var/www/app/
- Config: /var/www/app/config/database.php
- .env: /var/www/app/.env

### .env DB Config
```
DB_CONNECTION=mysql
DB_HOST=10.124.0.4
DB_PORT=53072
DB_DATABASE=login_db
DB_USERNAME=logindatabase
DB_PASSWORD="jA#c&Kx%bFi2EbrwSu^z3XwWwbQF%y2q$%%Q4UgUw&CT"
DB_SSL_CA=/etc/pki/mysql-client/ca.cert.pem
DB_SSL_CERT=/etc/pki/mysql-client/app1.cert.pem
DB_SSL_KEY=/etc/pki/mysql-client/app1.key.pem
```

### Nginx Config (/etc/nginx/conf.d/app.conf)
- Listen: 443 ssl
- SSL cert: /etc/pki/app1/ca/certs/app1.cert.pem
- SSL key: /etc/pki/app1/ca/private/app1.key.pem
- Protocols: TLSv1.2 TLSv1.3
- PHP-FPM: unix:/run/php-fpm/www.sock

### Certs
- Nginx SSL: /etc/pki/app1/ca/{certs/app1.cert.pem, private/app1.key.pem}
- MySQL client: /etc/pki/mysql-client/{ca.cert.pem, app1.cert.pem, app1.key.pem}

### CA (APP1's own CA)
- Location: /etc/pki/app1/ca/
- CA cert: /etc/pki/app1/ca/certs/ca.cert.pem (CN=10.124.0.3, self-signed)
- Server cert: /etc/pki/app1/ca/certs/app1.cert.pem (CN=10.124.0.3)
- CA key: /etc/pki/app1/ca/private/ca.key.pem
- Server key: /etc/pki/app1/ca/private/app1.key.pem

### SELinux: Enforcing
### Firewalld: Active (SSH + HTTPS only, target REJECT)

---

## APP2 (24.199.118.31)

### Stack
- Same as APP1

### .env DB Config
```
DB_CONNECTION=mysql
DB_HOST=10.124.0.4
DB_PORT=53072
DB_DATABASE=login_db
DB_USERNAME=logindatabase
DB_PASSWORD="s4fzgmgHy4CkKhPheX^mT2Dw~iWsMV`3f`A^SK#FVgSKaohSQ"
DB_SSL_CA=/etc/pki/mysql-client/ca.cert.pem
DB_SSL_CERT=/etc/pki/mysql-client/app2.cert.pem
DB_SSL_KEY=/etc/pki/mysql-client/app2.key.pem
```

### Nginx Config (/etc/nginx/conf.d/app.conf)
- Listen: 443 ssl
- SSL cert: /etc/pki/app2/ca/certs/app2.cert.pem
- SSL key: /etc/pki/app2/ca/private/app2.key.pem

### Certs
- Nginx SSL: /etc/pki/app2/ca/{certs/app2.cert.pem, private/app2.key.pem}
- MySQL client: /etc/pki/mysql-client/{ca.cert.pem, app2.cert.pem, app2.key.pem}

### CA (APP2's own CA)
- Location: /etc/pki/app2/ca/
- CA cert: /etc/pki/app2/ca/certs/ca.cert.pem (CN=10.124.0.2, self-signed)
- Server cert: /etc/pki/app2/ca/certs/app2.cert.pem (CN=10.124.0.2)
- CA key: /etc/pki/app2/ca/private/ca.key.pem
- Server key: /etc/pki/app2/ca/private/app2.key.pem

### SELinux: Enforcing
### Firewalld: Active (SSH + HTTPS only, target REJECT)

---

## Load Balancer (143.110.237.225)

### Stack
- Nginx 1.30.3 (stable from nginx.org)
- Rocky Linux 9.8
- Config: /etc/nginx/conf.d/loadbalance.conf

### Config (loadbalance.conf)
- Listen: 80 (HTTP only - Cloudflare handles HTTPS externally)
- Split clients 50/50 between APP1 (10.124.0.3:443) and APP2 (10.124.0.2:443)
- proxy_pass: https://$backend_addr (SSL re-encryption to backends)
- proxy_ssl_verify: on
- proxy_ssl_trusted_certificate: /etc/nginx/ssl/ca-chain.pem (both APP1+APP2 CA certs)
- proxy_ssl_name: $backend_name (matches cert CN)
- Rate limiting: 10r/s general
- Security headers: X-Frame-Options, X-Content-Type-Options, X-XSS-Protection

### CA Chain
- /etc/nginx/ssl/ca-chain.pem = APP1 CA cert + APP2 CA cert concatenated

### SELinux: Enforcing
- Booleans: httpd_can_network_connect=on, httpd_can_network_relay=on

### Firewalld: Active (SSH + HTTP only, target REJECT)

---

## Laravel database.php

SSL options use env vars:
```php
'options' => extension_loaded('pdo_mysql') ? array_filter([
    PDO::MYSQL_ATTR_SSL_CA   => env('DB_SSL_CA'),
    PDO::MYSQL_ATTR_SSL_CERT => env('DB_SSL_CERT'),
    PDO::MYSQL_ATTR_SSL_KEY  => env('DB_SSL_KEY'),
]) : [],
```

---

## Known Issues
- LB returns HTTP 500 from Laravel (app not fully configured, not infra issue)
- MySQL has REQUIRE X509 on logindatabase users
- Passwords have special characters ($, %, #, &, ~, `, ^) requiring careful escaping

## Current Error
- SQLSTATE[HY000] [2002] Permission denied (Connection: mysql)
- Error code 2002 = connection failed, likely SSL cert permission or firewall
- Happening on both APP1 and APP2 when connecting to MySQL
