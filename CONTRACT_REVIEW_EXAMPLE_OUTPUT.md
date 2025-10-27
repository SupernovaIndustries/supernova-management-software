# Esempio Output Revisione Contratto AI

## Esempio Completo: Contratto di Servizio IT

### Informazioni Contratto
- **Numero**: CTR-2025-001
- **Tipo**: Contratto di Servizio (Service Agreement)
- **Cliente**: TechStart SRL
- **Titolo**: Servizi di Sviluppo e Manutenzione Software

---

## 🎯 Score Generale: 72/100

**Problemi identificati**: 8
**Revisionato il**: 06/10/2025 18:30

---

## 📋 Valutazione Generale

**Qualità**: Il contratto presenta una buona struttura di base con definizione chiara dell'oggetto e delle parti coinvolte. Tuttavia, mancano alcune clausole fondamentali per la tutela di entrambe le parti, in particolare relativamente agli SLA, alla proprietà intellettuale e alla limitazione di responsabilità. Il testo necessita di integrazioni prima della sottoscrizione.

### Punti di Forza ✓
- Parti chiaramente identificate con tutti i dati richiesti (denominazione sociale, sede legale, P.IVA, rappresentante legale)
- Oggetto del contratto definito in modo dettagliato
- Termini di pagamento ben specificati con scadenze e modalità
- Riferimenti GDPR presenti e conformi al Reg. UE 2016/679
- Clausola di foro competente presente (Tribunale di Milano)

### Punti Deboli ✗
- Service Level Agreement (SLA) assenti o non sufficientemente dettagliati
- Mancano clausole di limitazione di responsabilità per entrambe le parti
- Diritti di proprietà intellettuale sui deliverable non chiaramente definiti
- Assenza di clausola di forza maggiore
- Garanzie vaghe e non quantificabili
- Procedura di risoluzione anticipata poco chiara

---

## ✅ Checklist Clausole

### 1. ✓ Parti Identificate (PRESENTE)
**Livello Rischio**: None

**Commento**: Le parti sono correttamente identificate con denominazione sociale completa, sede legale, Codice Fiscale/P.IVA e rappresentante legale. Conforme all'art. 1321 c.c.

**Testo Attuale**:
"Tra Supernova Electronics SRL, con sede in Milano (MI), Via Giuseppe Verdi 42, C.F./P.IVA 12345678901, nella persona del legale rappresentante Ing. Marco Rossi... e TechStart SRL, con sede in Roma (RM), Via Nazionale 100, C.F./P.IVA 98765432100..."

---

### 2. ✓ Date Chiare (PRESENTE)
**Livello Rischio**: None

**Commento**: Decorrenza, durata e modalità di rinnovo chiaramente specificate.

---

### 3. ✓ Oggetto Definito (PRESENTE)
**Livello Rischio**: None

**Commento**: L'oggetto del contratto (sviluppo software gestionale + manutenzione) è descritto in modo adeguato con riferimento alle specifiche tecniche allegate.

---

### 4. ⚠ SLA Definiti (DA MIGLIORARE)
**Livello Rischio**: High

**Commento**: Il contratto menziona "tempi di intervento rapidi" e "alta disponibilità" ma non definisce metriche oggettive, tempi di risposta garantiti o percentuali di uptime.

**Suggerimento**: Specificare SLA misurabili: tempo di prima risposta (es. 4 ore lavorative per severity alta), tempo di risoluzione target (es. 24h per severity alta, 72h per severity media), percentuale uptime garantito (es. 99.5%), finestre di manutenzione programmate.

**Testo Suggerito**:
```
Art. X - Service Level Agreement

Il Fornitore garantisce i seguenti Service Level Agreement (SLA):

a) Tempi di Risposta:
   - Severity 1 (Bloccante): Prima risposta entro 2 ore lavorative
   - Severity 2 (Alta): Prima risposta entro 4 ore lavorative
   - Severity 3 (Media): Prima risposta entro 8 ore lavorative
   - Severity 4 (Bassa): Prima risposta entro 24 ore lavorative

b) Tempi di Risoluzione Target:
   - Severity 1: 8 ore lavorative
   - Severity 2: 24 ore lavorative
   - Severity 3: 72 ore lavorative
   - Severity 4: 5 giorni lavorativi

c) Disponibilità Sistema:
   - Uptime garantito: 99.5% mensile (escluse finestre di manutenzione programmate)
   - Manutenzione programmata: Massimo 4 ore/mese, comunicata con 7 giorni di anticipo

d) Penali:
   In caso di mancato rispetto degli SLA:
   - Severity 1: Sconto 5% sul canone mensile per ogni 24h di ritardo
   - Uptime < 99%: Sconto proporzionale al downtime eccedente
```

---

### 5. ✓ Responsabilità delle Parti (PRESENTE)
**Livello Rischio**: Low

**Commento**: Le responsabilità sono indicate ma potrebbero essere più dettagliate per casi specifici (es. dati forniti dal cliente errati, modifiche richieste dal cliente, ritardi nelle approvazioni).

---

### 6. ⚠ Garanzie (DA MIGLIORARE)
**Livello Rischio**: Medium

**Commento**: Il contratto prevede generiche "garanzie di buon funzionamento" ma non specifica durata, esclusioni, condizioni di applicazione.

**Suggerimento**: Definire periodo di garanzia (es. 12 mesi dalla consegna), cosa è coperto e cosa è escluso, modalità di attivazione della garanzia.

**Testo Suggerito**:
```
Art. X - Garanzie

1. Il Fornitore garantisce che il Software:
   a) Sarà conforme alle specifiche funzionali concordate
   b) Sarà privo di difetti che ne impediscano l'uso normale
   c) Non violerà diritti di terzi (IP, brevetti, copyright)

2. Periodo di Garanzia: 12 mesi dalla data di Accettazione finale

3. Esclusioni dalla Garanzia:
   a) Difetti derivanti da uso improprio o non conforme alla documentazione
   b) Modifiche effettuate dal Cliente o da terzi non autorizzati
   c) Problemi causati da hardware, rete o software di terze parti
   d) Danni da virus o attacchi informatici non imputabili a difetti del Software

4. Attivazione Garanzia:
   - Segnalazione scritta entro 8 giorni dalla scoperta del difetto
   - Descrizione dettagliata del problema e condizioni di riproduzione
   - Il Fornitore avrà 30 giorni per correggere il difetto
```

---

### 7. ✓ Clausole di Risoluzione (PRESENTE)
**Livello Rischio**: Medium

**Commento**: Presenti ma generiche. Mancano dettagli su preavviso, obblighi post-risoluzione, restituzione dati.

---

### 8. ✗ Diritti di Proprietà Intellettuale (MANCANTE)
**Livello Rischio**: Critical

**Commento**: Il contratto non specifica la proprietà del software sviluppato, dei deliverable, della documentazione e del codice sorgente. Questa è una lacuna critica che può generare contenziosi futuri.

**Suggerimento**: Definire chiaramente: proprietà del software sviluppato ad-hoc, licenza d'uso, proprietà del codice sorgente, diritti sui miglioramenti futuri, IP su componenti riutilizzabili.

**Testo Suggerito**:
```
Art. X - Proprietà Intellettuale

1. Software Sviluppato ad-hoc:
   - Il Cliente acquisisce la piena proprietà del software sviluppato specificamente per il presente contratto
   - Include codice sorgente, documentazione tecnica, database design
   - Trasferimento proprietà alla consegna finale e completo pagamento

2. Componenti Preesistenti:
   - Il Fornitore mantiene la proprietà di librerie, framework, componenti riutilizzabili preesistenti
   - Il Cliente riceve licenza d'uso perpetua, non esclusiva, trasferibile per tali componenti
   - Lista componenti preesistenti allegata (Allegato B)

3. Licenze Software di Terze Parti:
   - Software di terze parti (es. librerie open-source) manterranno le rispettive licenze
   - Il Cliente si impegna a rispettare i termini di tali licenze
   - Lista completa in Allegato C

4. Know-how e Miglioramenti:
   - Il know-how generale sviluppato durante il progetto resta del Fornitore
   - Miglioramenti specifici alle funzionalità del Cliente sono di proprietà del Cliente
   - Il Fornitore può riutilizzare concetti generali in progetti per altri clienti

5. Riservatezza:
   - Entrambe le parti si impegnano a non divulgare il software e la documentazione a terzi
   - Obbligo di riservatezza permanente anche dopo la conclusione del contratto
```

---

### 9. ✓ Termini di Pagamento (PRESENTE)
**Livello Rischio**: None

**Commento**: Ben definiti con scadenze, modalità, interessi di mora conformi al D.Lgs. 231/2002.

---

### 10. ✗ Limitazione di Responsabilità (MANCANTE)
**Livello Rischio**: Critical

**Commento**: Assenza completa di clausole di limitazione di responsabilità. In caso di danni, entrambe le parti potrebbero essere esposte a risarcimenti illimitati. Clausola fondamentale per contratti IT.

**Suggerimento**: Inserire limiti di responsabilità quantificati, esclusioni di danni indiretti, procedura di notifica danni.

**Testo Suggerito**:
```
Art. X - Limitazione di Responsabilità

1. Massimale di Responsabilità:
   La responsabilità massima complessiva del Fornitore per qualsiasi danno derivante dal presente contratto è limitata a:
   - Per danni diretti: € 50.000 (cinquantamila) o il valore dei corrispettivi pagati negli ultimi 12 mesi, se superiore
   - Per danni indiretti: Esclusi (vedi punto 2)

2. Esclusione Danni Indiretti:
   Il Fornitore non sarà in nessun caso responsabile per:
   - Perdita di profitti o ricavi
   - Perdita di opportunità commerciali
   - Perdita di dati (salvo inadempimento obbligo di backup se contrattualmente previsto)
   - Interruzione dell'attività
   - Danni reputazionali
   - Altri danni indiretti, consequenziali o punitivi

3. Eccezioni:
   Le limitazioni di cui sopra non si applicano in caso di:
   - Dolo o colpa grave del Fornitore
   - Violazione di diritti di terzi (IP)
   - Danni a persone
   - Responsabilità non limitabile per legge

4. Notifica Danni:
   Il Cliente deve notificare per iscritto qualsiasi richiesta di risarcimento entro 30 giorni dalla scoperta del danno, a pena di decadenza.

5. Assicurazione:
   Il Fornitore dichiara di essere coperto da polizza di Responsabilità Civile Professionale per massimale di € 100.000.
```

---

### 11. ✓ Conformità GDPR (PRESENTE)
**Livello Rischio**: Low

**Commento**: Riferimenti al GDPR presenti. Tuttavia manca l'indicazione specifica di chi è Titolare e chi è Responsabile del trattamento (se applicabile).

**Suggerimento**: Specificare ruoli GDPR e allegare eventuale DPA (Data Processing Agreement) se il Fornitore tratta dati personali per conto del Cliente.

---

### 12. ✓ Foro Competente (PRESENTE)
**Livello Rischio**: None

**Commento**: Foro di Milano indicato. Conforme all'art. 1341 c.c. se approvato specificamente.

---

### 13. ⚠ Supporto e Manutenzione (DA MIGLIORARE)
**Livello Rischio**: Medium

**Commento**: Servizio di manutenzione menzionato ma senza dettagli su: orari di supporto, canali di contatto, cosa include manutenzione ordinaria vs straordinaria, costi extra.

**Suggerimento**: Dettagliare servizio di supporto: orari (es. 9-18 lun-ven), canali (email, telefono, ticketing), cosa è incluso nel canone e cosa è extra.

---

## 🚨 Rischi Legali Identificati

### 1. Assenza Proprietà Intellettuale (CRITICAL)
**Descrizione**: Il contratto non definisce chi detiene la proprietà del software sviluppato. In assenza di clausola specifica, si applica l'art. 2578 c.c. che attribuisce al creatore (Fornitore) la proprietà dell'opera dell'ingegno. Questo significa che il Cliente pagherebbe lo sviluppo ma non ne diverrebbe proprietario, potendo solo utilizzarlo.

**Raccomandazione**: Inserire immediatamente clausola di trasferimento IP come suggerito sopra. Per software su misura, la best practice è trasferire la proprietà al Cliente. Per componenti riutilizzabili, concedere licenza perpetua.

---

### 2. Limitazione Responsabilità Assente (HIGH)
**Descrizione**: In assenza di limiti contrattuali, la responsabilità per inadempimento o danni è regolata dagli artt. 1218-1229 c.c. e 2043 c.c., potenzialmente illimitata. Il Fornitore potrebbe essere esposto a richieste di risarcimento per danni diretti e indiretti senza massimale.

**Raccomandazione**: Inserire clausola di limitazione di responsabilità con massimale ragionevole (es. valore contratto o multiplo). È prassi comune nei contratti IT limitare a 1-2 volte il valore annuo del contratto ed escludere danni indiretti. Verificare copertura assicurativa RC Professionale.

---

### 3. SLA Vaghi - Rischio Contestazioni (MEDIUM)
**Descrizione**: Espressioni generiche come "tempi rapidi" o "alta qualità" sono soggettive e fonte di contestazioni. In caso di contenzioso, il giudice dovrebbe interpretare secondo "buona fede" (art. 1375 c.c.) ma con incertezza sull'esito.

**Raccomandazione**: Quantificare sempre gli SLA con metriche oggettive e misurabili (tempi in ore, percentuali, ecc.). Definire penali per inadempimento e bonus per superamento SLA.

---

## ⚖️ Problemi di Compliance

### 1. GDPR - Ruoli non Definiti
**Regolamento**: Reg. UE 2016/679 (GDPR)
**Articolo**: Art. 28 (Responsabile del Trattamento)

**Problema**: Il contratto menziona "conformità GDPR" ma non specifica se il Fornitore è Responsabile del Trattamento (Data Processor) o Contitolare. Se il Fornitore accede a dati personali dei dipendenti o clienti del Cliente, deve essere formalizzato con DPA.

**Soluzione**:
1. Definire ruoli: Cliente = Titolare, Fornitore = Responsabile (se applicabile)
2. Allegare Data Processing Agreement (DPA) conforme all'art. 28 GDPR
3. Il DPA deve includere: finalità trattamento, tipologie dati, misure di sicurezza, subprocessori, trasferimenti extra-UE, obblighi in caso di data breach

---

### 2. Clausola Vessatoria - Approvazione Specifica
**Normativa**: Codice Civile Italiano
**Articolo**: Art. 1341 c.c. (Condizioni Generali di Contratto)

**Problema**: Se il contratto contiene clausole vessatorie (es. limitazione responsabilità, foro competente), queste richiedono approvazione specifica per iscritto oltre alla firma del contratto.

**Soluzione**: Aggiungere al contratto:
```
"Il Cliente, ai sensi e per gli effetti dell'art. 1341 secondo comma c.c., dichiara di approvare specificamente le seguenti clausole: [elencare clausole vessatorie, es. Art. X Limitazione Responsabilità, Art. Y Foro Competente, ecc.]

Firma per approvazione specifica: ________________"
```

---

## 💡 Miglioramenti Suggeriti

### 1. Procedura di Accettazione Deliverable (Priorità: ALTA)

**Attuale**: Il contratto menziona "consegna del software" senza definire come avviene l'accettazione formale.

**Suggerito**:
```
Art. X - Procedura di Accettazione

1. Consegna:
   Il Fornitore comunicherà per email la disponibilità del deliverable per il testing.

2. Periodo di Test:
   Il Cliente avrà 15 giorni lavorativi dalla consegna per testare il deliverable.

3. Esiti Possibili:
   a) Accettazione: Il Cliente invia email di accettazione formale. Il deliverable si intende accettato.
   b) Rifiuto con Bug Critici: Lista dettagliata bug da correggere. Il Fornitore ha 10 giorni per correggere.
   c) Accettazione Tacita: Trascorsi 15 giorni senza comunicazioni, il deliverable si intende accettato.

4. Effetti Accettazione:
   - Decorrenza garanzia
   - Fatturazione del saldo (se previsto)
   - Trasferimento proprietà intellettuale (se contrattualmente previsto)

5. Criteri di Accettazione:
   - Conformità alle specifiche funzionali (Allegato A)
   - Assenza di bug critici (severity 1-2)
   - Performance entro parametri definiti (es. tempo risposta < 2 secondi)
```

---

### 2. Clausola di Forza Maggiore (Priorità: MEDIA)

**Attuale**: Assente

**Suggerito**:
```
Art. X - Forza Maggiore

1. Definizione:
   Si intendono eventi di forza maggiore: guerra, terrorismo, disastri naturali, epidemie/pandemie, scioperi generali, atti dell'autorità, interruzioni servizi essenziali (energia, internet), atti di Dio, e ogni altro evento imprevedibile e inevitabile.

2. Effetti:
   - Sospensione obblighi contrattuali per la durata dell'evento
   - Proroga automatica delle scadenze per periodo corrispondente
   - Nessuna responsabilità per inadempimento durante forza maggiore

3. Notifica:
   La Parte che invoca forza maggiore deve notificare l'altra entro 5 giorni dall'inizio dell'evento, indicando durata prevista e impatti.

4. Durata Prolungata:
   Se la forza maggiore perdura oltre 60 giorni, ciascuna Parte può recedere dal contratto con preavviso scritto di 15 giorni, senza penali.
```

---

### 3. Gestione Modifiche e Change Requests (Priorità: ALTA)

**Attuale**: Non è chiaro come vengono gestite le richieste di modifica alle specifiche durante lo sviluppo.

**Suggerito**:
```
Art. X - Gestione Modifiche

1. Change Request:
   Qualsiasi modifica alle specifiche concordate (Allegato A) deve essere formalizzata con Change Request scritta.

2. Valutazione:
   - Il Fornitore ha 5 giorni lavorativi per valutare impatti: tempi, costi, risorse
   - Invio preventivo aggiuntivo e stima tempi

3. Approvazione:
   - Il Cliente approva o rifiuta entro 5 giorni
   - Approvazione implica accettazione di costi e tempi aggiuntivi

4. Esecuzione:
   - Modifiche entrano in vigore solo dopo approvazione scritta
   - Timeline progetto prorogata di conseguenza

5. Minor Changes:
   - Modifiche marginali (< 4 ore sviluppo, no impatti architetturali) possono essere gestite verbalmente
   - Conferma via email entro 24h
```

---

### 4. Riservatezza e NDA (Priorità: ALTA)

**Attuale**: Menzionata genericamente ma non dettagliata.

**Suggerito**: Aggiungere articolo dedicato o allegare NDA bilaterale separato. Elementi chiave:
- Definizione informazioni confidenziali
- Obblighi di protezione (standard ragionevole, almeno pari a quelli usati per proprie info)
- Esclusioni (info pubbliche, già note, sviluppate indipendentemente)
- Durata obbligo (es. 5 anni dopo termine contratto)
- Obbligo restituzione/distruzione alla fine
- Personale autorizzato (dipendenti con need-to-know)
- Subappaltatori (previa autorizzazione e vincolo NDA)

---

### 5. Backup e Business Continuity (Priorità: MEDIA)

**Attuale**: Non menzionato.

**Suggerito**: Se il servizio include hosting o gestione dati:
```
Art. X - Backup e Disaster Recovery

1. Backup:
   - Frequenza: Giornaliero (incrementale), Settimanale (completo)
   - Retention: 30 giorni
   - Locazione: Server primario + backup off-site

2. Restore:
   - Tempo di ripristino: Entro 4 ore dalla richiesta (orario lavorativo)
   - Test ripristino: Trimestrale, con verbale

3. Disaster Recovery:
   - RTO (Recovery Time Objective): 8 ore
   - RPO (Recovery Point Objective): Max 24 ore perdita dati
   - Sito secondario: [specificare se presente]
```

---

## 📊 Riepilogo Score per Area

| Area                          | Score | Colore    |
|-------------------------------|-------|-----------|
| Identificazione Parti         | 100   | 🟢 Verde  |
| Oggetto e Termini Generali    | 95    | 🟢 Verde  |
| Aspetti Economici             | 90    | 🟢 Verde  |
| Service Level Agreement       | 45    | 🔴 Rosso  |
| Proprietà Intellettuale       | 0     | 🔴 Rosso  |
| Responsabilità e Garanzie     | 50    | 🟡 Arancio|
| Compliance Normativa          | 75    | 🟡 Arancio|
| Risoluzione e Termine         | 70    | 🟡 Arancio|

---

## 🎬 Azioni Raccomandate

### Prima della Firma (URGENTE)
1. ✅ Inserire clausola Proprietà Intellettuale (vedi testo suggerito)
2. ✅ Inserire clausola Limitazione Responsabilità
3. ✅ Dettagliare SLA con metriche misurabili

### Prima della Firma (IMPORTANTE)
4. ✅ Aggiungere procedura di Accettazione deliverable
5. ✅ Chiarire ruoli GDPR e allegare DPA se necessario
6. ✅ Aggiungere clausola Forza Maggiore
7. ✅ Definire gestione Change Request

### Post-Firma (CONSIGLIATO)
8. 📝 Allegare specifiche tecniche dettagliate
9. 📝 Pianificare review annuale del contratto
10. 📝 Documentare tutti gli accordi verbali per iscritto

---

## 📞 Note Finali

Questo contratto, con uno score di **72/100**, presenta una **buona base** ma **necessita di integrazioni fondamentali** prima della firma. In particolare, l'assenza di clausole su **Proprietà Intellettuale** e **Limitazione Responsabilità** rappresenta un **rischio critico** per entrambe le parti.

### Raccomandazione Generale
**🟡 REVISIONE NECESSARIA PRIMA DELLA FIRMA**

Si consiglia di:
1. Implementare le clausole critiche (IP e Responsabilità) - OBBLIGATORIO
2. Dettagliare gli SLA - FORTEMENTE RACCOMANDATO
3. Far revisionare il contratto da un legale esperto in IT/IP - CONSIGLIATO per contratti > €50.000
4. Approvazione specifica clausole vessatorie ex art. 1341 c.c.

### Disclaimer
Questa revisione AI è uno strumento di supporto e non sostituisce la consulenza legale professionale. Per contratti di valore significativo o particolarmente complessi, si raccomanda sempre il supporto di un avvocato specializzato in diritto commerciale/IT.

---

**Report generato da**: Sistema di Revisione Intelligente Contratti AI
**Motore AI**: Claude 3.5 Sonnet (Anthropic)
**Data**: 06/10/2025 18:30:45
**Versione Sistema**: 1.0
