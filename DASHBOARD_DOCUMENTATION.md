# 📊 Dashboard - Dokumentace

## Přehled

Dashboard poskytuje komplexní přehled všech systémových metrik a statistik v přehledné grafické formě. Je navržen pro rychlé získání přehledu o stavu aplikace a aktivitě uživatelů.

## Funkce

### 📈 Souhrnné statistiky
- **Celkem záznamů** - Celkový počet všech záznamů v systému
- **Nové záznamy (7 dní)** - Počet nově vytvořených záznamů za posledních 7 dní  
- **Celkem uživatelů** - Počet registrovaných uživatelů
- **Celková částka** - Suma všech finančních částek v záznamech
- **Záznamy s částkou** - Počet záznamů, které obsahují finanční částku
- **Akce za 24h** - Počet audit akcí za posledních 24 hodin (pouze pro administrátory)

### 📊 Grafické vizualizace

#### 1. Distribuce stavů záznamů (Doughnut graf)
- Vizuální zobrazení poměru různých stavů záznamů
- Barevné rozlišení: Zaplaceno (zelená), Zasláno (modrá), Odmítnuto (červená), Rozpracované (oranžová)

#### 2. Trendy aktivity (Line graf)
- Zobrazuje aktivitu vytváření záznamů za posledních 14 dní
- Pomáhá identifikovat vzorce a trendy v používání systému

#### 3. Finanční přehled podle stavů (Bar graf)
- Suma finančních částek podle různých stavů záznamů
- Umožňuje rychlé pochopení finanční distribuce

#### 4. Aktivita uživatelů (Horizontal Bar graf)
- Top 5 nejaktivnějších uživatelů podle počtu vytvořených záznamů
- Zobrazuje uživatelské jméno a počet záznamů

#### 5. Audit Log grafy (pouze pro administrátory)
- **Typy akcí** - Distribuce různých typů audit akcí
- **Závažnost** - Rozložení akcí podle úrovně závažnosti

### 📋 Tabulkové přehledy

#### Nejaktivnější uživatelé (30 dní)
- Detailní tabulka nejaktivnějších uživatelů za posledních 30 dní
- Zobrazuje uživatelské jméno, počet záznamů a vizuální indikátor aktivity

## Technické informace

### API Endpoint
```
GET api.php?action=dashboard-stats
```

### Struktura dat
```json
{
  "success": true,
  "data": {
    "records": {
      "total_records": 150,
      "recent_records": 12,
      "by_status": [...],
      "top_users": [...]
    },
    "users": {
      "total_users": 5,
      "by_role": [...],
      "active_users": [...],
      "new_users": 2
    },
    "trends": [...],
    "status_distribution": [...],
    "financial": {
      "totals": {...},
      "by_status": [...],
      "monthly_trends": [...]
    },
    "audit": {
      "total_actions": 1200,
      "recent_actions": 25,
      "top_actions": [...],
      "by_severity": [...],
      "daily_activity": [...]
    }
  }
}
```

### Databázové dotazy

Dashboard využívá optimalizované SQL dotazy s využitím indexů pro rychlé načítání dat:

1. **Základní statistiky** - COUNT, SUM, AVG agregace
2. **Trendy** - GROUP BY datum s časovým filtrem
3. **Top uživatelé** - ORDER BY s LIMITem
4. **Finanční statistiky** - Podmíněné SUMy podle stavů

### Zabezpečení

- **Ověření autentizace** - Všechny statistiky vyžadují přihlášeného uživatele
- **Role-based access** - Audit statistiky pouze pro administrátory
- **SQL injection ochrana** - Prepared statements pro všechny dotazy

## Použití

1. **Přístup** - Dashboard je dostupný jako první záložka v hlavní navigaci
2. **Obnovení dat** - Tlačítko "Obnovit data" pro aktuální statistiky
3. **Responsivní design** - Automatické přizpůsobení velikosti obrazovky
4. **Real-time aktualizace** - Čas poslední aktualizace je zobrazen ve spodní části

## Technologie

- **Chart.js 3.x** - Moderní knihovna pro grafy
- **Vanilla JavaScript** - Bez závislosti na frameworku
- **CSS Grid & Flexbox** - Responsivní layout
- **Iframe integrace** - Standalone aplikace v rámci hlavního systému

## Výkonnost

- **Optimalizované dotazy** - Využití databázových indexů
- **Kešování na frontend** - Předchozí data zůstávají viditelná během načítání
- **Asynchronní načítání** - Non-blocking UI operace
- **Error handling** - Graceful degradation při chybách API

## Budoucí rozšíření

- Export dashboard dat do PDF/PNG
- Konfigurovatelné časové rozsahy
- Personalizované dashboardy pro různé role
- Real-time aktualizace pomocí WebSockets
- Pokročilé filtry pro specifické analýzy