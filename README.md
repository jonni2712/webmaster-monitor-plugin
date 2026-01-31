# Webmaster Monitor - WordPress Plugin

<div align="center">

![WordPress Plugin Version](https://img.shields.io/badge/version-1.0.2-blue.svg)
![WordPress Compatibility](https://img.shields.io/badge/WordPress-5.0%2B-0073aa.svg)
![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)
![License](https://img.shields.io/badge/license-GPL%20v2-green.svg)
![Vibe Coded](https://img.shields.io/badge/Vibe%20Coded-Claude-ff6b6b.svg)

**Plugin WordPress per connettere i tuoi siti alla piattaforma Webmaster Monitor**

[Installazione](#-installazione) •
[Funzionalita](#-funzionalita) •
[API Reference](#-api-reference) •
[Sviluppo](#-sviluppo)

</div>

---

## Cos'e Webmaster Monitor?

**Webmaster Monitor** e una piattaforma di monitoraggio centralizzato per agenzie web e freelancer che gestiscono molteplici siti WordPress. Questo plugin permette di collegare ogni sito WordPress alla piattaforma, abilitando:

- Monitoraggio remoto dello stato del server
- Tracciamento versioni WordPress, plugin e temi
- Notifiche aggiornamenti disponibili
- Aggiornamenti remoti one-click
- Health check automatici

---

## Vibe Coded

> Questo plugin e stato sviluppato con **Vibe Coding** - un nuovo paradigma di sviluppo dove il codice viene scritto interamente da AI (Claude) guidata da istruzioni in linguaggio naturale.

Il progetto dimostra come lo sviluppo di plugin WordPress production-ready sia possibile attraverso la collaborazione umano-AI, mantenendo alti standard di qualita, sicurezza e best practices.

---

## Funzionalita

### Informazioni Server

| Info | Descrizione |
|------|-------------|
| PHP Version | Versione PHP e configurazione (memory_limit, max_execution_time, etc.) |
| Database | MySQL/MariaDB version, charset, dimensione database |
| Web Server | Apache, Nginx, LiteSpeed, IIS detection |
| Disk Space | Spazio totale, usato, libero + dimensione WordPress |
| Extensions | Stato estensioni PHP critiche (curl, gd, openssl, etc.) |

### Informazioni WordPress

| Info | Descrizione |
|------|-------------|
| Core | Versione WP, aggiornamenti disponibili, configurazione |
| Plugin | Lista completa con versioni e stato aggiornamenti |
| Temi | Tema attivo, child theme detection, aggiornamenti |
| Utenti | Conteggio per ruolo, lista amministratori |
| Site Health | Integrazione con WordPress Site Health |
| Costanti | Stato WP_DEBUG, WP_CACHE, DISALLOW_FILE_EDIT, etc. |

### Multisite Support

Supporto completo per installazioni WordPress Multisite con informazioni su:
- Network sites
- Subsiti e loro configurazione
- Statistiche aggregate

### Aggiornamenti Remoti

Esegui aggiornamenti direttamente dalla piattaforma:
- Aggiorna plugin singoli
- Aggiorna temi
- Aggiorna WordPress core

### Sicurezza

- **API Key unica** per ogni installazione
- **Autenticazione richiesta** per tutti gli endpoint sensibili
- **Health check pubblico** per uptime monitoring (senza dati sensibili)
- **Rigenerazione API Key** con un click

---

## Installazione

### Metodo 1: Upload Manuale

1. Scarica `webmaster-monitor.zip` dalla piattaforma
2. Vai su **Plugin > Aggiungi nuovo > Carica plugin**
3. Seleziona il file ZIP e clicca **Installa ora**
4. Attiva il plugin

### Metodo 2: Via FTP

1. Estrai `webmaster-monitor.zip`
2. Carica la cartella `webmaster-monitor` in `/wp-content/plugins/`
3. Vai su **Plugin** nel pannello admin
4. Attiva **Webmaster Monitor**

### Configurazione

1. Vai su **Impostazioni > Webmaster Monitor**
2. Copia l'**API Key** generata automaticamente
3. Nella piattaforma Webmaster Monitor:
   - Aggiungi il sito con URL
   - Inserisci l'API Key quando richiesto
   - Clicca "Verifica Connessione"

---

## API Reference

Il plugin espone diversi endpoint REST API:

### Autenticazione

Tutte le richieste (eccetto `/health`) richiedono l'API Key:

```bash
# Via Header (consigliato)
curl -H "X-WM-API-Key: wm_xxx..." https://tuosito.com/wp-json/webmaster-monitor/v1/status

# Via Query Parameter
curl https://tuosito.com/wp-json/webmaster-monitor/v1/status?api_key=wm_xxx...
```

### Endpoints

| Endpoint | Metodo | Auth | Descrizione |
|----------|--------|------|-------------|
| `/webmaster-monitor/v1/status` | GET | Si | Status completo (server + WP) |
| `/webmaster-monitor/v1/server` | GET | Si | Solo informazioni server |
| `/webmaster-monitor/v1/wordpress` | GET | Si | Solo informazioni WordPress |
| `/webmaster-monitor/v1/health` | GET | No | Health check per uptime monitoring |
| `/webmaster-monitor/v1/ping` | GET | Si | Test connessione |
| `/webmaster-monitor/v1/apply-update` | POST | Si | Applica aggiornamento |

### Esempio Response `/status`

```json
{
  "plugin_version": "1.0.2",
  "timestamp": "2025-01-31T10:30:00+00:00",
  "server": {
    "php": {
      "version": "8.2.0",
      "memory_limit": "256M",
      "max_execution_time": "30",
      "extensions": {
        "curl": true,
        "gd": true,
        "openssl": true
      }
    },
    "database": {
      "type": "MariaDB",
      "version": "10.6.12-MariaDB",
      "total_size": "45.2 MB"
    },
    "disk": {
      "total": "100 GB",
      "free": "65 GB",
      "used_percentage": 35
    }
  },
  "wordpress": {
    "core": {
      "version": "6.4.2",
      "update_available": false,
      "multisite": false
    },
    "plugins": {
      "total": 15,
      "active": 12,
      "updates_available": 3
    },
    "themes": {
      "total": 4,
      "updates_available": 1
    }
  }
}
```

### Esempio Response `/health`

```json
{
  "status": "ok",
  "timestamp": "2025-01-31T10:30:00+00:00",
  "checks": {
    "database": true,
    "filesystem": true,
    "cron": true
  }
}
```

### Apply Update

```bash
curl -X POST \
  -H "X-WM-API-Key: wm_xxx..." \
  -H "Content-Type: application/json" \
  -d '{"type": "plugin", "slug": "akismet"}' \
  https://tuosito.com/wp-json/webmaster-monitor/v1/apply-update
```

Tipi supportati: `plugin`, `theme`, `core`

---

## Struttura File

```
webmaster-monitor/
├── webmaster-monitor.php      # File principale plugin
├── readme.txt                  # Readme formato WordPress.org
├── admin/
│   └── settings.php           # Pagina impostazioni admin
└── includes/
    ├── class-api.php          # REST API endpoints
    ├── class-server-info.php  # Raccolta info server
    ├── class-wp-info.php      # Raccolta info WordPress
    ├── class-multisite-info.php # Supporto multisite
    └── class-updater.php      # Sistema aggiornamenti automatici
```

---

## Sviluppo

### Requisiti

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+

### Hooks Disponibili

```php
// Filter: Modifica dati server prima dell'invio
add_filter('wm_monitor_server_info', function($info) {
    // Personalizza $info
    return $info;
});

// Filter: Modifica dati WordPress prima dell'invio
add_filter('wm_monitor_wp_info', function($info) {
    // Personalizza $info
    return $info;
});
```

### Costanti Configurabili

```php
// wp-config.php

// Disabilita raccolta informazioni utenti
define('WM_MONITOR_DISABLE_USER_INFO', true);

// Disabilita aggiornamenti remoti
define('WM_MONITOR_DISABLE_REMOTE_UPDATES', true);
```

---

## FAQ

### E sicuro?

Si. Il plugin utilizza un'API Key unica (64 caratteri hex) generata per ogni installazione. Solo chi possiede l'API Key puo accedere ai dati. L'endpoint `/health` e l'unico pubblico e non espone dati sensibili.

### Quali dati vengono raccolti?

Informazioni tecniche su server e WordPress: versioni software, plugin installati, temi, spazio disco, configurazione. **Non vengono raccolti dati personali** degli utenti del sito ne contenuti.

### Posso rigenerare l'API Key?

Si, dalla pagina **Impostazioni > Webmaster Monitor** puoi rigenerare l'API Key in qualsiasi momento. La vecchia chiave smettera immediatamente di funzionare.

### Funziona con WordPress Multisite?

Si, il plugin supporta pienamente le installazioni Multisite e raccoglie informazioni aggregate sulla rete.

### Rallenta il sito?

No. Il plugin non esegue operazioni in frontend. Le API vengono chiamate solo quando la piattaforma richiede dati, tipicamente ogni 5-15 minuti per il monitoraggio.

---

## Changelog

### 1.0.2
- Aggiunto supporto aggiornamenti remoti (plugin, temi, core)
- Migliorata gestione errori API
- Fix compatibilita PHP 8.2

### 1.0.1
- Aggiunto supporto Multisite
- Migliorata raccolta info Site Health
- Fix minori

### 1.0.0
- Prima versione pubblica
- Informazioni server (PHP, MySQL, web server, disco)
- Informazioni WordPress (core, plugin, temi, utenti)
- Endpoint REST API sicuro
- Pagina impostazioni admin

---

## Supporto

- **Documentazione**: [docs.webmaster-monitor.com](https://docs.webmaster-monitor.com)
- **Piattaforma**: [webmaster-monitor.com](https://webmaster-monitor.com)
- **Issues**: [GitHub Issues](https://github.com/jonni2712/webmaster-plugin/issues)

---

## Licenza

Questo plugin e rilasciato sotto licenza **GPL v2 or later**.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

---

<div align="center">

**Webmaster Monitor Plugin** - Sviluppato con Vibe Coding

Made with Claude AI

</div>
