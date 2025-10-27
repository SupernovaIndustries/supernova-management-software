# Guida Visuale UI - Sistema Revisione Contratti AI

## 📱 Interfaccia Utente - Elementi Visivi

### 1. Tabella Contratti - Nuova Colonna "Review AI"

La colonna mostra badge colorati con lo score della revisione:

```
┌─────────────────────────────────────────────────────────┐
│ Numero      Cliente        Tipo        Review AI        │
├─────────────────────────────────────────────────────────┤
│ CTR-2025-001  TechStart   Servizio   [🟢 85/100]       │
│ CTR-2025-002  Digital SRL NDA        [🟢 92/100]       │
│ CTR-2025-003  Innovation  Fornitura  [🟡 67/100]       │
│ CTR-2025-004  StartupX    Partnership[🔴 45/100]       │
│ CTR-2025-005  CloudCo     Servizio   [⚪ Non revisionato]│
└─────────────────────────────────────────────────────────┘
```

**Legenda Colori**:
- 🟢 **Verde** (80-100): Eccellente - Sicuro da firmare
- 🟡 **Arancione** (60-79): Da migliorare - Richiede attenzione
- 🔴 **Rosso** (0-59): Alto rischio - Revisione urgente necessaria
- ⚪ **Grigio**: Non ancora revisionato

---

### 2. Actions Menu - Pulsanti Azione

Nella riga di ogni contratto, sono disponibili 3 nuove azioni:

```
┌────────────────────────────────────────────────┐
│  [✎]  [🗑]  [✨]  [🛡️]  [🔍]  [📄]  [↓]      │
│ Edit Delete Analizza Revisiona Vedi  Genera Download│
│                 AI      AI    Review  PDF    PDF   │
└────────────────────────────────────────────────┘
```

**Icone**:
- **✨ Analizza con AI** (blu): Estrazione automatica dati dal PDF
- **🛡️ Revisiona con AI** (arancione): Revisione intelligente contratto
- **🔍 Vedi Revisione** (blu): Visualizza risultati revisione (solo se già revisionato)

---

### 3. Modal "Revisiona con AI" - Conferma

```
┌──────────────────────────────────────────────────────┐
│  🛡️ Revisiona Contratto con AI                      │
├──────────────────────────────────────────────────────┤
│                                                      │
│  Il contratto verrà revisionato da Claude AI per    │
│  verificare clausole mancanti, rischi legali e      │
│  compliance normativa italiana.                      │
│                                                      │
│  Tempo stimato: 30-45 secondi                       │
│                                                      │
│  ┌────────────┐  ┌────────────┐                    │
│  │  Annulla   │  │ Revisiona  │                    │
│  └────────────┘  └────────────┘                    │
└──────────────────────────────────────────────────────┘
```

---

### 4. Notifica Completamento Revisione

```
┌──────────────────────────────────────────────────────┐
│  ✓ Revisione Completata                             │
├──────────────────────────────────────────────────────┤
│  Score: 72/100 - Problemi: 8                        │
│                                                      │
│  Il contratto è stato revisionato con successo.     │
│  Clicca su "Vedi Revisione" per i dettagli.        │
└──────────────────────────────────────────────────────┘
```

Colore notifica:
- 🟢 Verde se score ≥ 70
- 🟡 Arancione se score 50-69
- 🔴 Rosso se score < 50

---

### 5. Modal "Vedi Revisione" - Layout Completo

Il modal si apre come slide-over (pannello laterale) a tutta altezza:

```
┌────────────────────────────────────────────────────────────────┐
│  🔍 Revisione: CTR-2025-001                              [✕]  │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  ╔════════════════════════════════════════════════════════╗   │
│  ║  Score: 72/100                                    🟡   ║   │
│  ║  Problemi identificati: 8                              ║   │
│  ║  Revisionato il: 06/10/2025 18:30                     ║   │
│  ╚════════════════════════════════════════════════════════╝   │
│                                                                │
│  ─────────────────────────────────────────────────────────    │
│                                                                │
│  📋 Valutazione Generale                                      │
│                                                                │
│  Il contratto presenta una buona struttura di base...         │
│                                                                │
│  Punti di Forza:                                              │
│  • Parti chiaramente identificate                             │
│  • Oggetto del contratto definito                             │
│  • Termini di pagamento ben specificati                       │
│                                                                │
│  Punti Deboli:                                                │
│  • SLA assenti o insufficienti                                │
│  • Mancano clausole di limitazione responsabilità             │
│  • Diritti IP non definiti                                    │
│                                                                │
│  ─────────────────────────────────────────────────────────    │
│                                                                │
│  ✅ Checklist Clausole                                        │
│                                                                │
│  ┌──────────────────────────────────────────────────────┐    │
│  │ ✓ Parti Identificate                      [Presente] │    │
│  │                                                       │    │
│  │ Le parti sono correttamente identificate con         │    │
│  │ denominazione sociale, sede legale...                │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                                │
│  ┌──────────────────────────────────────────────────────┐    │
│  │ ⚠ SLA Definiti                        [Da Migliorare] │   │
│  │                                                       │    │
│  │ Il contratto menziona "tempi rapidi" ma non          │    │
│  │ definisce metriche oggettive...                      │    │
│  │                                                       │    │
│  │ 💡 Suggerimento: Specificare SLA misurabili:         │    │
│  │ tempo di prima risposta (es. 4h)...                  │    │
│  │                                                       │    │
│  │ ┌───────────────────────────────────────────┐        │    │
│  │ │ Testo suggerito:                          │        │    │
│  │ │                                           │        │    │
│  │ │ Art. X - Service Level Agreement         │        │    │
│  │ │                                           │        │    │
│  │ │ Il Fornitore garantisce i seguenti SLA:  │        │    │
│  │ │ a) Tempi di Risposta:                    │        │    │
│  │ │    - Severity 1: 2 ore lavorative...     │        │    │
│  │ └───────────────────────────────────────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                                │
│  ┌──────────────────────────────────────────────────────┐    │
│  │ ✗ Diritti di Proprietà Intellettuale    [Mancante]  │    │
│  │                                                       │    │
│  │ Il contratto non specifica la proprietà del          │    │
│  │ software sviluppato...                               │    │
│  │                                                       │    │
│  │ 💡 Suggerimento: Definire chiaramente:              │    │
│  │ proprietà del software sviluppato ad-hoc...          │    │
│  │                                                       │    │
│  │ [Testo suggerito espanso...]                         │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                                │
│  ─────────────────────────────────────────────────────────    │
│                                                                │
│  🚨 Rischi Legali                                             │
│                                                                │
│  ┌──────────────────────────────────────────────────────┐    │
│  │ Assenza Proprietà Intellettuale            [CRITICAL] │   │
│  │                                                       │    │
│  │ Il contratto non definisce chi detiene la proprietà  │    │
│  │ del software sviluppato. In assenza di clausola...   │    │
│  │                                                       │    │
│  │ 🔧 Raccomandazione: Inserire immediatamente clausola │   │
│  │ di trasferimento IP come suggerito sopra...          │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                                │
│  ┌──────────────────────────────────────────────────────┐    │
│  │ Limitazione Responsabilità Assente            [HIGH] │    │
│  │                                                       │    │
│  │ [Descrizione del rischio...]                         │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                                │
│  ─────────────────────────────────────────────────────────    │
│                                                                │
│  ⚖️ Problemi di Compliance                                    │
│                                                                │
│  ┌──────────────────────────────────────────────────────┐    │
│  │ GDPR - Ruoli non Definiti                            │    │
│  │ Reg. UE 2016/679 - Art. 28                          │    │
│  │                                                       │    │
│  │ Il contratto menziona "conformità GDPR" ma non       │    │
│  │ specifica se il Fornitore è Responsabile...          │    │
│  │                                                       │    │
│  │ ✅ Soluzione: Definire ruoli, allegare DPA...        │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                                │
│  ─────────────────────────────────────────────────────────    │
│                                                                │
│  💡 Miglioramenti Suggeriti                                   │
│                                                                │
│  ┌──────────────────────────────────────────────────────┐    │
│  │ Procedura di Accettazione Deliverable    [ALTA]     │    │
│  │                                                       │    │
│  │ Attuale: Il contratto menziona "consegna" senza      │    │
│  │ definire come avviene l'accettazione formale.        │    │
│  │                                                       │    │
│  │ [Testo suggerito completo...]                        │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                                │
│  [Scroll per altri miglioramenti...]                          │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

---

### 6. Dashboard Widget - Statistiche Revisioni

Il widget mostra 6 card statistiche affiancate:

```
┌────────────────────────────────────────────────────────────────┐
│  📊 Statistiche Revisioni Contratti                            │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐          │
│  │ Revisionati │  │ Score Medio │  │ Alto Rischio│          │
│  │    45/78    │  │   78.5/100  │  │      3      │          │
│  │             │  │             │  │             │          │
│  │ 58% totale  │  │  Buona      │  │ Score < 60  │          │
│  │ [📈]        │  │  qualità    │  │             │          │
│  └─────────────┘  └─────────────┘  └─────────────┘          │
│                                                                │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐          │
│  │ Richiedono  │  │ Problemi    │  │  Revisioni  │          │
│  │ Revisione   │  │   Totali    │  │   Recenti   │          │
│  │     12      │  │     127     │  │      8      │          │
│  │             │  │             │  │             │          │
│  │Non rev/low  │  │Identificati │  │ Ultimi 7gg  │          │
│  └─────────────┘  └─────────────┘  └─────────────┘          │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

**Colori Card**:
- Revisionati: Blu (info)
- Score Medio: Verde/Arancione/Rosso (basato su valore)
- Alto Rischio: Rosso (danger)
- Richiedono Revisione: Arancione (warning)
- Problemi Totali: Rosso (danger)
- Revisioni Recenti: Verde (success)

---

## 🎨 Palette Colori Sistema

### Score e Qualità
```css
🟢 Verde (#10b981)  - Score 80-100 - Eccellente
🟡 Arancione (#f59e0b) - Score 60-79 - Da migliorare
🔴 Rosso (#ef4444)  - Score 0-59 - Alto rischio
⚪ Grigio (#9ca3af) - Non revisionato
```

### Status Clausole
```css
✓ Verde (#10b981)  - Presente
⚠ Arancione (#f59e0b) - Da migliorare
✗ Rosso (#ef4444)  - Mancante
```

### Severità Rischi
```css
🔴 Rosso scuro (#dc2626)   - Critical
🟠 Arancione scuro (#ea580c) - High
🟡 Arancione (#f59e0b)      - Medium
⚫ Grigio scuro (#64748b)    - Low
```

### Priorità Miglioramenti
```css
🔴 Rosso (#ef4444)     - Alta
🟡 Arancione (#f59e0b) - Media
⚫ Grigio (#64748b)     - Bassa
```

---

## 🔔 Notifiche Sistema

### Notifica Successo (Verde)
```
┌──────────────────────────────────────────────┐
│  ✓ Revisione Completata                     │
│                                              │
│  Score: 85/100 - Problemi: 4                │
│  Il contratto è stato revisionato.          │
└──────────────────────────────────────────────┘
```

### Notifica Warning (Arancione)
```
┌──────────────────────────────────────────────┐
│  ⚠ Attenzione                               │
│                                              │
│  Score: 58/100 - 12 problemi identificati   │
│  Revisione legale raccomandata.             │
└──────────────────────────────────────────────┘
```

### Notifica Errore (Rosso)
```
┌──────────────────────────────────────────────┐
│  ✗ Errore Revisione AI                      │
│                                              │
│  Il contratto non contiene testo da         │
│  analizzare. Compilare "Termini".           │
└──────────────────────────────────────────────┘
```

---

## 📱 Responsive Design

### Desktop (> 1280px)
- Modal revisione: Slide-over largo (5xl = 1280px)
- Widget dashboard: 3 colonne di card
- Tabella: Tutte le colonne visibili

### Tablet (768px - 1280px)
- Modal: Slide-over medio (4xl = 896px)
- Widget: 2 colonne di card
- Tabella: Alcune colonne nascoste (toggleable)

### Mobile (< 768px)
- Modal: Full screen
- Widget: 1 colonna, card impilate
- Tabella: Solo colonne essenziali

---

## 🎯 UX Best Practices Implementate

### 1. **Feedback Immediato**
- Loading spinner durante revisione
- Notifica push al completamento
- Aggiornamento real-time badge nella tabella

### 2. **Codice Colore Consistente**
- Verde = Positivo/Sicuro
- Arancione = Attenzione/Migliorabile
- Rosso = Pericolo/Urgente
- Grigio = Neutro/Non disponibile

### 3. **Gerarchia Visiva**
- Score in evidenza (24px, bold)
- Sezioni separate con divider
- Badge per status rapido
- Icone descrittive per azioni

### 4. **Accessibilità**
- Icone + testo per ogni azione
- Contrasto colori WCAG AA compliant
- Tooltip esplicativi
- Keyboard navigation supportata

### 5. **Progressive Disclosure**
- Riassunto in tabella (badge score)
- Dettagli in modal on-demand
- Testi suggeriti espandibili
- Sezioni collassabili per lunghe revisioni

---

## 🔍 Esempio Interazione Utente

### Scenario: Revisionare Contratto NDA

1. **Utente naviga** → Clienti → Contratti Clienti
2. **Individua contratto** → CTR-2025-045 (NDA con TechPartner)
3. **Clicca icona scudo** 🛡️ "Revisiona con AI"
4. **Conferma nel modal** → Pulsante "Revisiona"
5. **Attende 30 secondi** → Spinner visibile
6. **Riceve notifica** → "✓ Revisione Completata - Score: 88/100"
7. **Vede badge aggiornato** → [🟢 88/100] nella tabella
8. **Clicca icona lente** 🔍 "Vedi Revisione"
9. **Legge risultati** nel modal slide-over:
   - Score alto (88) = buona qualità ✓
   - 2 clausole da migliorare (Durata, Penali)
   - 1 clausola mancante (Restituzione materiali)
   - Nessun rischio legale critico
   - 3 suggerimenti di miglioramento
10. **Decide azione**:
    - Se score > 80: Può procedere alla firma
    - Se score 60-80: Implementa suggerimenti principali
    - Se score < 60: Richiede revisione legale

---

## 💻 Codice Esempio Badge

### Badge Score nella Tabella
```php
Tables\Columns\TextColumn::make('ai_review_score')
    ->label('Review AI')
    ->badge()
    ->color(fn ($record) => $record->isReviewed()
        ? $record->review_score_color
        : 'gray')
    ->formatStateUsing(fn ($record) => $record->isReviewed()
        ? $record->ai_review_score . '/100'
        : 'Non revisionato')
```

### Badge Status Clausola
```html
<!-- Presente (Verde) -->
<span style="background: #10b98120; color: #10b981; padding: 2px 8px; border-radius: 12px;">
    ✓ Presente
</span>

<!-- Da Migliorare (Arancione) -->
<span style="background: #f59e0b20; color: #f59e0b; padding: 2px 8px; border-radius: 12px;">
    ⚠ Da Migliorare
</span>

<!-- Mancante (Rosso) -->
<span style="background: #ef444420; color: #ef4444; padding: 2px 8px; border-radius: 12px;">
    ✗ Mancante
</span>
```

---

## 📸 Screenshot UI (Rappresentazione Testuale)

### Vista Tabella con Colonna Review
```
╔══════════════════════════════════════════════════════════════════════════╗
║ Contratti Clienti                                              [+ Nuovo] ║
╠══════════════════════════════════════════════════════════════════════════╣
║                                                                          ║
║ 🔍 [Cerca contratti...]                            [Filtri ▼]          ║
║                                                                          ║
╠═══════╦═══════════════╦═══════════╦════════════╦═══════════╦════════════╣
║ N.    ║ Cliente       ║ Tipo      ║ Inizio     ║ Review AI ║ Azioni    ║
╠═══════╬═══════════════╬═══════════╬════════════╬═══════════╬════════════╣
║ CTR-  ║ TechStart SRL ║ 🟢Servizio║ 01/01/2025 ║ 🟢 85/100 ║ [✎][🛡️][🔍]║
║ 2025- ║               ║           ║            ║           ║           ║
║ 001   ║               ║           ║            ║           ║           ║
╠═══════╬═══════════════╬═══════════╬════════════╬═══════════╬════════════╣
║ CTR-  ║ Digital SRL   ║ 🟡NDA     ║ 15/02/2025 ║ 🟢 92/100 ║ [✎][🛡️][🔍]║
║ 2025- ║               ║           ║            ║           ║           ║
║ 002   ║               ║           ║            ║           ║           ║
╠═══════╬═══════════════╬═══════════╬════════════╬═══════════╬════════════╣
║ CTR-  ║ Innovation    ║ 🔵Fornitura║ 10/03/2025║ 🟡 67/100 ║ [✎][🛡️][🔍]║
║ 2025- ║ SpA           ║           ║            ║           ║           ║
║ 003   ║               ║           ║            ║           ║           ║
╠═══════╬═══════════════╬═══════════╬════════════╬═══════════╬════════════╣
║ CTR-  ║ StartupX SRL  ║ 🟣Partner ║ 05/04/2025 ║ 🔴 45/100 ║ [✎][🛡️][🔍]║
║ 2025- ║               ║           ║            ║           ║           ║
║ 004   ║               ║           ║            ║           ║           ║
╠═══════╬═══════════════╬═══════════╬════════════╬═══════════╬════════════╣
║ CTR-  ║ CloudCo Inc   ║ 🟢Servizio║ 20/05/2025 ║ ⚪ Non    ║ [✎][🛡️]   ║
║ 2025- ║               ║           ║            ║ revision. ║           ║
║ 005   ║               ║           ║            ║           ║           ║
╚═══════╩═══════════════╩═══════════╩════════════╩═══════════╩════════════╝

Mostrando 1-5 di 78 contratti                            [◀ 1 2 3 ... 16 ▶]
```

---

## 🎬 Tips per Migliore UX

1. **Tooltips**: Aggiungere tooltip su badge score con interpretazione
   - 85/100 → "Qualità eccellente, sicuro da firmare"

2. **Quick Actions**: Menu contestuale click-destro per azioni rapide

3. **Bulk Review**: Pulsante per revisionare multiple contratti selezionati

4. **Export Report**: Pulsante per esportare revisione in PDF/Word

5. **Notifiche Email**: Opzione per ricevere email quando revisione completa

6. **Filtri Rapidi**:
   - "Da revisionare" → Non revisionati + score < 70
   - "Alto rischio" → Score < 60
   - "Pronti firma" → Score >= 80

---

**Versione UI**: 1.0
**Framework**: Filament v3
**Design System**: Tailwind CSS
**Icons**: Heroicons
