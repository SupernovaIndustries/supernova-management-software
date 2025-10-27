# Conversione Automatica Valuta

## Overview

Il sistema di importazione componenti ora supporta la **conversione automatica delle valute** durante l'import di CSV/Excel.

Quando importi componenti da fornitori che usano prezzi in dollari ($) o altre valute, il sistema:
1. ✅ **Rileva automaticamente** la valuta dal formato del prezzo
2. ✅ **Ottiene il tasso di cambio** aggiornato online (dalla Banca Centrale Europea)
3. ✅ **Converte automaticamente** il prezzo in Euro (€)
4. ✅ **Salva il prezzo convertito** nel database

## Valute Supportate

Il sistema supporta automaticamente le seguenti valute:

| Valuta | Simbolo | Codice | Esempio |
|--------|---------|--------|---------|
| **Dollaro USA** | $ | USD | `$10.50`, `USD 25.99` |
| **Euro** | € | EUR | `€15.75`, `EUR 20.00` |
| **Sterlina** | £ | GBP | `£8.99`, `GBP 45.00` |
| **Yen Giapponese** | ¥ | JPY | `¥1000`, `JPY 5000` |
| **Franco Svizzero** | - | CHF | `CHF 50.00` |
| **Yuan Cinese** | - | CNY | `CNY 100.00` |

## Come Funziona

### 1. Rilevamento Automatico

Il sistema rileva la valuta analizzando:
- **Simboli**: `$`, `€`, `£`, `¥`
- **Codici ISO**: `USD`, `EUR`, `GBP`, `JPY`, `CHF`, `CNY`

Esempi di formati rilevati:
```
$10.50          → USD
USD 25.99       → USD
$1,234.56       → USD (con separatore migliaia)
€15.75        → EUR
£8.99          → GBP
10.50 USD       → USD
```

### 2. Conversione con Tasso di Cambio Reale

Il sistema utilizza l'API **Frankfurter** (dati della Banca Centrale Europea):
- ✅ **Gratuita** - nessun costo o limite
- ✅ **Aggiornata giornalmente** - tassi reali ECB
- ✅ **Affidabile** - gestita dalla BCE
- ✅ **Cache 24h** - riduce le richieste API

**Esempio di conversione:**
```
Input CSV:    $10.50
Tasso:        1 USD = 0.8572 EUR
Output DB:    9.00 EUR
```

### 3. Fallback Automatico

Se l'API non è disponibile, il sistema usa tassi di fallback:
- 1 USD = 0.92 EUR
- 1 GBP = 1.17 EUR
- 1 JPY = 0.0063 EUR
- 1 CHF = 1.05 EUR
- 1 CNY = 0.13 EUR

## Utilizzo

### Import Automatico (Default)

La conversione è **abilitata di default**. Non devi fare nulla!

Quando importi un CSV con prezzi in dollari:

```csv
Part Number,Description,Unit Price
ABC-123,Resistor 1K,USD 0.50
DEF-456,Capacitor 10uF,$1.25
```

Il sistema:
1. Rileva `USD 0.50` → Converte in EUR
2. Rileva `$1.25` → Converte in EUR
3. Salva i prezzi convertiti nel database

### Disabilitare la Conversione (Opzionale)

Se preferisci disabilitare la conversione automatica:

```php
$importService = app(App\Services\ComponentImportService::class);
$importService->setAutoConvertCurrency(false);
```

### Verificare lo Stato

```php
$importService = app(App\Services\ComponentImportService::class);

if ($importService->isCurrencyConversionAvailable()) {
    echo "Conversione automatica attiva ✓";
} else {
    echo "Conversione automatica non disponibile ✗";
}
```

## Log e Tracciamento

Ogni conversione viene loggata in `storage/logs/laravel.log`:

```
[INFO] Price automatically converted
{
    "original": "$10.50",
    "original_amount": 10.5,
    "from_currency": "USD",
    "converted_amount": 9.0005,
    "to_currency": "EUR"
}
```

Per monitorare le conversioni in tempo reale:
```bash
tail -f storage/logs/laravel.log | grep "Price automatically converted"
```

## Test

### Test Manuale

Esegui lo script di test incluso:

```bash
php test_currency_conversion.php
```

Il test verifica:
- ✅ Rilevamento corretto delle valute
- ✅ Conversione con tassi reali
- ✅ Integrazione con ComponentImportService
- ✅ Gestione formati diversi (migliaia, decimali, etc.)

### Test Output Esempio

```
=== Currency Conversion Test ===

Current Exchange Rates:
------------------------------------------------------------
1 USD = 0.8572 EUR
1 GBP = 1.1700 EUR
1 JPY = 0.0057 EUR

Testing Conversion:
------------------------------------------------------------
$5.50    → 4.7145 EUR  ✓
€5.50   → 5.5000 EUR  ✓ (no conversion)
£10.00  → 11.7000 EUR ✓
```

## Gestione Cache

### Visualizzare Tassi in Cache

```php
$currencyService = app(App\Services\CurrencyExchangeService::class);
$rates = $currencyService->getAllCachedRates();

foreach ($rates as $currency => $rate) {
    echo "1 $currency = $rate EUR\n";
}
```

### Pulire Cache Manualmente

Se vuoi forzare l'aggiornamento dei tassi:

```bash
php artisan cache:forget exchange_rate_USD_EUR
php artisan cache:forget exchange_rate_GBP_EUR
```

Oppure via codice:

```php
$currencyService = app(App\Services\CurrencyExchangeService::class);
$currencyService->clearCache();
```

## Configurazione Avanzata

### Personalizzare Tassi di Fallback

```php
$currencyService = app(App\Services\CurrencyExchangeService::class);
$currencyService->setFallbackRate('USD', 0.95);
```

### Cambiare Durata Cache

Modifica `app/Services/CurrencyExchangeService.php`:

```php
protected int $cacheDuration = 86400; // 24 ore (default)
// Cambia in:
protected int $cacheDuration = 43200; // 12 ore
```

## API Utilizzata

**Frankfurter API**
- Endpoint: `https://api.frankfurter.app/latest?from=USD&to=EUR`
- Documentazione: https://www.frankfurter.app/docs/
- Dati: Banca Centrale Europea (ECB)
- Frequenza aggiornamento: Giornaliera
- Limitazioni: Nessuna

## FAQ

### Q: I prezzi già importati vengono riconvertiti?
**A:** No, solo i nuovi import vengono convertiti. I prezzi esistenti nel database rimangono invariati.

### Q: Cosa succede se l'API non è disponibile?
**A:** Il sistema usa automaticamente i tassi di fallback configurati. La conversione avviene comunque.

### Q: Posso vedere quale tasso è stato usato per una conversione?
**A:** Sì, controlla i log in `storage/logs/laravel.log` - ogni conversione è tracciata.

### Q: Posso importare CSV con prezzi misti (USD + EUR)?
**A:** Sì! Il sistema rileva la valuta per ogni riga. Puoi avere prezzi in USD, EUR, GBP nello stesso CSV.

### Q: I tassi vengono aggiornati in tempo reale?
**A:** I tassi sono cached per 24 ore per performance. Dopo 24h vengono aggiornati automaticamente dalla BCE.

### Q: Posso usare altre API per i tassi di cambio?
**A:** Sì, puoi modificare `app/Services/CurrencyExchangeService.php` e cambiare l'URL API in `$apiUrl`.

## Supporto

Per problemi o domande:
1. Controlla i log: `tail -f storage/logs/laravel.log`
2. Esegui il test: `php test_currency_conversion.php`
3. Verifica lo stato: `$importService->isCurrencyConversionAvailable()`

## Esempio Completo di Import

### File CSV (DigiKey formato USA)
```csv
DigiKey Part #,Manufacturer Part Number,Description,Unit Price
296-1234-1-ND,ABC123,RESISTOR 1K 1% 0603,$0.10
296-5678-1-ND,DEF456,CAPACITOR 10UF 16V,USD 0.25
296-9012-1-ND,GHI789,LED RED 0805,$0.15
```

### Import via Filament
1. Vai su **Components** → **Import**
2. Seleziona file CSV
3. Seleziona supplier: **DigiKey**
4. Click **Import**

### Risultato nel Database
```
ABC123  → Prezzo: 0.0857 EUR (convertito da $0.10)
DEF456  → Prezzo: 0.2143 EUR (convertito da USD 0.25)
GHI789  → Prezzo: 0.1286 EUR (convertito da $0.15)
```

### Log di Conversione
```
[INFO] Price automatically converted
{"original":"$0.10","original_amount":0.1,"from_currency":"USD","converted_amount":0.0857,"to_currency":"EUR"}

[INFO] Price automatically converted
{"original":"USD 0.25","original_amount":0.25,"from_currency":"USD","converted_amount":0.2143,"to_currency":"EUR"}

[INFO] Price automatically converted
{"original":"$0.15","original_amount":0.15,"from_currency":"USD","converted_amount":0.1286,"to_currency":"EUR"}
```

---

**Implementato:** 2025-10-07
**Versione:** 1.0
**Servizi:** `CurrencyExchangeService`, `ComponentImportService`
