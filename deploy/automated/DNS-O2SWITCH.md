# Déploiement VPS AdmHost — DNS O2Switch

Tout est hébergé sur le **VPS Scaleway** (`51.159.66.221`).
O2Switch sert uniquement à **gérer les DNS** (pas d'hébergement web).

## DNS à configurer (O2Switch → Zone DNS admhost.fr)

| Enregistrement | Type | Valeur |
|----------------|------|--------|
| `@` (admhost.fr) | A | `51.159.66.221` |
| `www` | A | `51.159.66.221` |
| `console` | A | `51.159.66.221` |
| `manage.console` | A | `51.159.66.221` |
| `api` | A | `51.159.66.221` |

Propagation DNS : 5 min à 24h.

## Déploiement sur le VPS

```bash
sudo bash deploy/deploy-vps.sh
```

## SSL (après propagation DNS)

```bash
sudo certbot --nginx \
  -d admhost.fr -d www.admhost.fr \
  -d console.admhost.fr \
  -d manage.console.admhost.fr \
  -d api.admhost.fr
```

## Architecture sur le VPS

```
/var/www/admhost/
├── public/vitrine/     → admhost.fr
├── public/console/     → console.admhost.fr
├── public/admin/       → manage.console.admhost.fr
├── backend/public/     → api.admhost.fr
├── shared/ frontend/ admin/
└── .env
```
