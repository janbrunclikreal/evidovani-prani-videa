# 📊 Evidence přání videií v3.2.0

Kompletní webová aplikace pro evidenci přání videií s pokročilým uživatelským systémem, dashboard analytikou a audit systémem.

## ✨ Hlavní funkce

- 👤 **Uživatelský systém** - Přihlašování, role (admin/user), správa uživatelů
- 📅 **Evidence záznamů** - Kompletní CRUD operace s filtry a stránkováním
- 📊 **Dashboard s analytikou** - Pokročilé grafy a statistiky (Chart.js)
- 📈 **Měsíční a roční přehledy** - Detailní statistiky podle období
- 💰 **Finanční přehled** - Sledování částek podle stavů s trendy
- 🔍 **Audit systém** - Kompletní auditní log pro administrátory
- 🔍 **Testovací rozhraní** - Kompletní testování všech API funkcionalít
- ⚙️ **Snadná instalace** - Automatický instalátor s GUI
- 👥 **Správa uživatelů** - Pro administrátory (vytváření, úpravy, změna hesel)

## 🚀 Rychlá instalace

### Krok 1: Požadavky
- PHP 7.4 nebo vyšší
- MySQL 5.7+ nebo MariaDB 10.2+
- Webový server (Apache/Nginx)
- PDO MySQL rozšíření

### Krok 2: Stažení a nahrání
1. Stáhněte všechny soubory aplikace
2. Nahrajte je na váš webový server
3. Ujistěte se, že složka má oprávnění k zápisu

### Krok 3: Spuštění instalace
1. Otevřete v prohlížeči: `http://vase-domena.cz/install.php`
2. Vyplňte databázové údaje:
   - **Databázový server**: obvykle `localhost`
   - **Port**: obvykle `3306` 
   - **Název databáze**: např. `evidence_prani`
   - **Uživatelské jméno**: váš DB uživatel
   - **Heslo**: heslo k databázi
3. Nastavte název aplikace a **heslo pro administrátora**
4. Klikněte na **"🚀 Spustit instalaci"**

### Krok 4: První přihlášení
Po úspěšné instalaci se můžete přihlásit pomocí:

**Administrator:**
- Uživatel: `admin`
- Heslo: [vaše zadané heslo při instalaci]

**Standardní uživatel:**
- Uživatel: `user`  
- Heslo: `password`

## 📂 Struktura souborů

```
evidence-app/
├── 📄 index.html          # Hlavní aplikace s integrovaným přihlášením
├── 📄 login.html          # Standalone přihlašovací stránka
├── 📄 users.html          # Správa uživatelů (pouze admin)
├── 📄 install.php         # Instalační průvodce  
├── 📄 config.php          # Konfigurace (vytvoří se automaticky)
├── 📄 api.php            # API endpoint
├── 📄 Record.php         # Databázová logika záznamů
├── 📄 User.php           # Databázová logika uživatelů
├── 📄 database.php       # Databázová třída
├── 📄 app.js             # Frontend JavaScript
├── 📄 styles.css         # Styly aplikace
├── 📄 test_kompletni.html # Testovací rozhraní
└── 📄 README.md          # Tento soubor
```

## 🔧 Použití aplikace

### Hlavní aplikace (`index.html`)
- Integrované přihlašování
- Evidence záznamů přání
- Měsíční a roční přehledy
- Export/import dat

### Správa uživatelů (`users.html`)
**Pouze pro administrátory:**
- Vytváření nových uživatelů
- Úprava uživatelských jmen a rolí  
- Změna hesel
- Mazání uživatelů (kromě hlavního admina)

### Testovací rozhraní (`test_kompletni.html`)
- Testování všech API endpointů
- Kontrola funkčnosti po instalaci
- Debugging a monitoring

## 🎯 API endpointy

### Uživatelské
| Endpoint | Popis | Metoda | Oprávnění |
|----------|-------|--------|-----------|
| `?action=login` | Přihlášení | POST | Všichni |
| `?action=logout` | Odhlášení | POST | Přihlášení |
| `?action=check-session` | Kontrola session | GET | Přihlášení |
| `?action=get-users` | Seznam uživatelů | GET | Admin |
| `?action=create-user` | Vytvoření uživatele | POST | Admin |
| `?action=update-user` | Úprava uživatele | POST | Admin |
| `?action=delete-user` | Smazání uživatele | POST | Admin |
| `?action=change-password` | Změna hesla | POST | Vlastník/Admin |

### Záznamové
| Endpoint | Popis | Parametry |
|----------|-------|-----------|
| `?action=records` | Denní přehled | `date=YYYY-MM-DD` |
| `?action=get-records` | Záznamy s filtrováním | `page`, filtry |
| `?action=create-record` | Vytvoření záznamu | POST data |
| `?action=update-record` | Úprava záznamu | `id`, POST data |
| `?action=delete-record` | Smazání záznamu | `id` |

### Přehledy
| Endpoint | Popis | Parametry |
|----------|-------|-----------|
| `?action=monthly-overview` | Měsíční statistiky | `year=YYYY&month=MM` |
| `?action=monthly-detail` | Měsíční detail po dnech | `year=YYYY&month=MM` |
| `?action=yearly-overview` | Roční přehled | `year=YYYY` |

## 📊 Databázová struktura

### Tabulka `users`
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Tabulka `records`
```sql
CREATE TABLE records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    datum DATE NOT NULL,
    jmeno VARCHAR(100) NOT NULL,
    ucet VARCHAR(50),
    castka DECIMAL(10,2),
    stav ENUM('zaplaceno','zaslano','odmitnuto','rozpracovane') DEFAULT 'rozpracovane',
    prani TEXT,
    nick VARCHAR(50),
    link VARCHAR(255),
    faktura VARCHAR(100),
    created_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## 🔍 Řešení problémů

### Chyba 401 (Unauthorized)
- Ujistěte se, že jste přihlášeni
- Zkontrolujte, zda máte správná oprávnění pro akci
- Session může být vypršená - přihlaste se znovu

### Chyba 400 (Bad Request) u create-user
- Zkontrolujte, zda existuje tabulka `users`
- Spusťte znovu instalátor pro opravu databáze
- Ověřte oprávnění databázového uživatele

### Nefunguje změna hesla/úprava uživatelů
- Pouze administrátoři mohou spravovat uživatele
- Uživatelé mohou měnit pouze své vlastní heslo
- Hlavního admina (ID=1) nelze smazat

### Chyba připojení k databázi
- Zkontrolujte údaje v `config.php`
- Ověřte, že je MySQL/MariaDB spuštěn
- Zkontrolujte oprávnění databázového uživatele

## 🔒 Zabezpečení

### Implementovaná opatření:
- Hashování hesel pomocí `password_hash()`
- SQL injection ochrana pomocí prepared statements
- Session timeout (1 hodina)
- Role-based přístup (admin/user)
- Ochrana citlivých souborů přes `.htaccess`

### Doporučená opatření:
- Změňte výchozí hesla po instalaci
- Omezte přístup k `install.php` po instalaci
- Pravidelně aktualizujte PHP a MySQL
- Používejte HTTPS v produkci
- Nastavte silná hesla pro databázi

### Po instalaci:
```bash
# Smazání nebo přejmenování instalačního souboru
rm install.php
# nebo
mv install.php install.php.bak
```

## 👥 Správa uživatelů

### Role a oprávnění

**Administrátor (`admin`):**
- Plný přístup ke všem funkcím
- Správa uživatelů (vytváření, úpravy, mazání)
- Změna hesel všech uživatelů
- Přístup k admin rozhraní

**Uživatel (`user`):**
- Základní CRUD operace se záznamy
- Zobrazení přehledů a statistik
- Změna vlastního hesla
- Export dat

### Vytváření uživatelů
1. Přihlaste se jako admin
2. Přejděte na `users.html`
3. Klikněte "➕ Přidat uživatele"
4. Vyplňte údaje a vyberte roli
5. Zadejte silné heslo

## 🚀 Pokročilé funkce

### Přizpůsobení
- Upravte styly v `styles.css`
- Rozšiřte API v `api.php` a `Record.php`
- Přidejte nové funkce do `app.js`

### Zálohování
```bash
# Záloha databáze
mysqldump -u username -p database_name > backup.sql

# Záloha souborů
tar -czf backup-$(date +%Y%m%d).tar.gz .
```

### Monitoring
- Používejte `test_kompletni.html` pro monitoring API
- Sledujte PHP error logy
- Kontrolujte velikost databáze

## 📧 Podpora

Pro technickou podporu nebo hlášení chyb:
- Zkontrolujte tento README
- Použijte testovací rozhraní pro diagnostiku
- Zkontrolujte PHP error logy
- Kontaktujte administrátora systému

## 📄 Licence

Evidence přání videií - Vytvořeno MiniMax Agent © 2025

---

**🎉 Děkujeme za použití Evidence aplikace!**

*Pro více informací navštivte správu uživatelů (`users.html`) nebo testovací rozhraní (`test_kompletni.html`).*