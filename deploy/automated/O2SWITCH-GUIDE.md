# O2Switch — Déploiement pas à pas (terminal cPanel)

Domaines : **admhost.fr** | **console.admhost.fr** | **manage.console.admhost.fr**

---

## ÉTAPE 1 — Terminal cPanel

cPanel → **Terminal**

---

## ÉTAPE 2 — Cloner le projet

```bash
cd ~
git clone https://github.com/admhostgtu/admhost.git admhost-src
cd admhost-src
```

---

## ÉTAPE 3 — Vérifier les dossiers web

```bash
ls ~/public_html
ls ~/console.admhost.fr/public_html
ls ~/manage.console.admhost.fr/public_html
```

Si un dossier manque, créez le sous-domaine dans cPanel → Domaines → Sous-domaines.

---

## ÉTAPE 4 — Déployer les 3 sites

```bash
cd ~/admhost-src
bash deploy/deploy-o2switch.sh
```

---

## ÉTAPE 5 — Configurer votre IP admin

Depuis votre PC : `curl ifconfig.me`

Sur O2Switch :

```bash
nano ~/admhost/.env
```

Ajoutez/modifiez :

```env
ADMIN_ALLOWED_IPS=VOTRE_IP_PUBLIQUE
```

Même IP dans `/var/www/admhost/.env` sur le VPS API.

---

## ÉTAPE 6 — Vérifier

```bash
curl -I https://admhost.fr
curl -I https://console.admhost.fr/login
curl -I https://manage.console.admhost.fr/login
```

---

## Mises à jour

```bash
cd ~/admhost-src && git pull && bash deploy/deploy-o2switch.sh
```
