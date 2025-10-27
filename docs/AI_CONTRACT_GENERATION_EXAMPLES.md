# Esempi Output AI Contract Generator

## Esempio 1: NDA Standard

### Input
```
Cliente: ACME Corp SRL
P.IVA: IT12345678901
Titolo: "Accordo di Riservatezza per progetto Robotica Industriale"
Tipo: NDA
Data Inizio: 15/10/2025
Durata: 36 mesi
Valore: Non specificato
Clausole Speciali: (nessuna)
```

### Output Atteso (Estratto)

```html
<h3>Art. 1 - PREMESSE E OGGETTO</h3>
<p>
Le Parti, come di seguito identificate, intendono collaborare nell'ambito del progetto
"Robotica Industriale" e, a tal fine, si scambieranno reciprocamente informazioni riservate
e confidenziali relative a know-how, progetti, strategie commerciali e dati tecnici.
</p>
<p>
Il presente Accordo di Riservatezza ha per oggetto la regolamentazione degli obblighi di
riservatezza che le Parti si impegnano a rispettare in relazione alle Informazioni
Confidenziali che verranno scambiate nel corso della collaborazione.
</p>

<h3>Art. 2 - DEFINIZIONI</h3>
<p>
Ai fini del presente Accordo, per "Informazioni Confidenziali" si intendono tutte le
informazioni, dati, documenti, specifiche tecniche, disegni, progetti, processi, formule,
know-how, software, algoritmi, strategie commerciali, listini prezzi, informazioni su
clienti e fornitori, e qualsiasi altra informazione di natura tecnica, commerciale,
industriale o amministrativa comunicata da una Parte all'altra, sia in forma scritta,
orale, grafica o elettronica.
</p>
<p><strong>Non sono considerate Informazioni Confidenziali:</strong></p>
<ul>
  <li>Le informazioni già di pubblico dominio al momento della comunicazione;</li>
  <li>Le informazioni che divengono di pubblico dominio successivamente alla comunicazione,
      senza violazione del presente Accordo;</li>
  <li>Le informazioni già lecitamente in possesso della Parte ricevente prima della comunicazione;</li>
  <li>Le informazioni sviluppate in modo indipendente dalla Parte ricevente;</li>
  <li>Le informazioni ricevute lecitamente da terzi non vincolati da obblighi di riservatezza.</li>
</ul>

<h3>Art. 3 - OBBLIGHI DI RISERVATEZZA</h3>
<p>Le Parti si impegnano reciprocamente a:</p>
<ul>
  <li>Mantenere strettamente riservate tutte le Informazioni Confidenziali ricevute;</li>
  <li>Non divulgare a terzi, in tutto o in parte, le Informazioni Confidenziali senza
      previo consenso scritto dell'altra Parte;</li>
  <li>Utilizzare le Informazioni Confidenziali esclusivamente per le finalità del presente
      Accordo e del progetto comune;</li>
  <li>Proteggere le Informazioni Confidenziali con misure di sicurezza adeguate, almeno
      pari a quelle adottate per le proprie informazioni confidenziali;</li>
  <li>Consentire l'accesso alle Informazioni Confidenziali esclusivamente ai propri
      dipendenti, collaboratori e consulenti che abbiano necessità di conoscerle per le
      finalità del presente Accordo e che siano preventivamente vincolati a obblighi di
      riservatezza analoghi a quelli del presente Accordo.</li>
</ul>

<h3>Art. 4 - DURATA DELL'OBBLIGO</h3>
<p>
Il presente Accordo ha durata di 36 (trentasei) mesi a decorrere dalla data di sottoscrizione
(15/10/2025) e cesserà automaticamente alla scadenza di tale termine (15/10/2028).
</p>
<p>
Gli obblighi di riservatezza di cui al presente Accordo rimarranno in vigore per ulteriori
5 (cinque) anni dalla cessazione dell'Accordo per le Informazioni Confidenziali di particolare
rilevanza tecnica o commerciale.
</p>

<h3>Art. 5 - RESTITUZIONE DELLE INFORMAZIONI</h3>
<p>
Alla scadenza o alla cessazione anticipata del presente Accordo, ciascuna Parte, su richiesta
dell'altra, si impegna a restituire o distruggere tutte le Informazioni Confidenziali ricevute,
in qualsiasi forma (documenti cartacei, supporti elettronici, copie, estratti), fornendo
attestazione scritta dell'avvenuta distruzione.
</p>
<p>
È consentito conservare una sola copia delle Informazioni Confidenziali per finalità di
archivio o per adempimenti di legge, sempre nel rispetto degli obblighi di riservatezza.
</p>

<h3>Art. 6 - CONSEGUENZE DELLA VIOLAZIONE</h3>
<p>
Le Parti riconoscono che la violazione degli obblighi di riservatezza previsti dal presente
Accordo può causare danni gravi e irreparabili alla Parte danneggiata.
</p>
<p>
In caso di violazione degli obblighi di riservatezza, la Parte inadempiente sarà tenuta al
risarcimento di tutti i danni diretti e indiretti subiti dalla Parte danneggiata, inclusi il
lucro cessante e il danno all'immagine.
</p>
<p>
La Parte danneggiata avrà inoltre diritto di richiedere al Tribunale competente l'adozione
di provvedimenti cautelari e inibitori ai sensi degli articoli 669-bis e seguenti del Codice
di Procedura Civile.
</p>

<h3>Art. 7 - LEGGE APPLICABILE E FORO COMPETENTE</h3>
<p>
Il presente Accordo è regolato dalla legge italiana. Per qualsiasi controversia derivante
dal presente Accordo o ad esso connessa sarà competente in via esclusiva il Foro di Milano.
</p>

<h3>Art. 8 - DISPOSIZIONI FINALI</h3>
<p>
Qualsiasi modifica o integrazione al presente Accordo dovrà essere effettuata per iscritto
e sottoscritta da entrambe le Parti.
</p>
<p>
Le comunicazioni tra le Parti relative al presente Accordo saranno valide se inviate
tramite PEC agli indirizzi indicati in intestazione o tramite raccomandata A/R.
</p>
<p>
Qualora una o più clausole del presente Accordo risultassero invalide o inefficaci, tale
invalidità o inefficacia non si estenderà alle altre clausole, che rimarranno pienamente
valide ed efficaci.
</p>
```

**Caratteristiche Output**:
- 8 articoli ben strutturati
- ~4.200 caratteri
- HTML formattato con `<h3>` e `<p>`
- Clausole conformi CC italiano
- Linguaggio tecnico-giuridico

---

## Esempio 2: Service Agreement con Milestone

### Input
```
Cliente: TechStart SRL
P.IVA: IT98765432109
Titolo: "Sviluppo Prototipo IoT per Agricoltura di Precisione"
Tipo: Service Agreement
Data Inizio: 01/11/2025
Data Fine: 31/01/2026
Valore: €18.000
Clausole Speciali: "3 milestone:
- M1 (30gg): Progettazione schematica e BOM - €6.000 (33%)
- M2 (60gg): Prototipo funzionale e test - €7.200 (40%)
- M3 (90gg): Documentazione e assistenza - €4.800 (27%)
Assistenza post-consegna: 6 mesi"
```

### Output Atteso (Estratto)

```html
<h3>Art. 1 - PREMESSE E OGGETTO</h3>
<p>
Supernova Industries S.r.l. (di seguito "Fornitore") si impegna a fornire al Cliente
TechStart SRL servizi di progettazione, sviluppo e realizzazione di un prototipo IoT per
applicazioni di agricoltura di precisione (di seguito "Progetto").
</p>
<p>
I servizi oggetto del presente Contratto comprendono:
</p>
<ul>
  <li>Progettazione schematica dell'architettura hardware e software;</li>
  <li>Definizione della Bill of Materials (BOM) dei componenti elettronici;</li>
  <li>Realizzazione di un prototipo funzionale completo;</li>
  <li>Test di funzionalità, validazione e debugging;</li>
  <li>Redazione di documentazione tecnica completa;</li>
  <li>Assistenza tecnica post-consegna per 6 mesi.</li>
</ul>

<h3>Art. 2 - DESCRIZIONE DEI SERVIZI</h3>
<p>
Il Fornitore svilupperà il Progetto secondo metodologie Agile, con rilasci incrementali
e revisioni periodiche con il Cliente. Il progetto sarà suddiviso nelle seguenti fasi
(Milestone):
</p>

<p><strong>Milestone 1 - Progettazione Schematica (30 giorni)</strong></p>
<ul>
  <li>Analisi dei requisiti funzionali e tecnici;</li>
  <li>Progettazione architettura hardware (schema elettrico, PCB layout);</li>
  <li>Progettazione architettura software (firmware, protocolli comunicazione);</li>
  <li>Definizione BOM dettagliata con specifiche componenti;</li>
  <li>Review tecnica con il Cliente.</li>
</ul>
<p><em>Deliverable: Schema elettrico, layout PCB preliminare, BOM, specifiche tecniche</em></p>
<p><em>Corrispettivo: € 6.000,00 (33% del totale)</em></p>

<p><strong>Milestone 2 - Prototipo Funzionale (60 giorni)</strong></p>
<ul>
  <li>Approvvigionamento componenti;</li>
  <li>Assemblaggio prototipo PCB;</li>
  <li>Sviluppo firmware e software di controllo;</li>
  <li>Test funzionali di laboratorio;</li>
  <li>Debugging e ottimizzazioni;</li>
  <li>Validazione con il Cliente.</li>
</ul>
<p><em>Deliverable: Prototipo funzionante, firmware, report test</em></p>
<p><em>Corrispettivo: € 7.200,00 (40% del totale)</em></p>

<p><strong>Milestone 3 - Documentazione e Assistenza (90 giorni)</strong></p>
<ul>
  <li>Redazione manuale utente e manuale tecnico;</li>
  <li>Documentazione completa per industrializzazione;</li>
  <li>Training al team tecnico del Cliente;</li>
  <li>Consegna prototipo e file sorgente;</li>
  <li>Assistenza tecnica post-consegna per 6 mesi.</li>
</ul>
<p><em>Deliverable: Documentazione completa, file sorgente, assistenza</em></p>
<p><em>Corrispettivo: € 4.800,00 (27% del totale)</em></p>

<h3>Art. 5 - CORRISPETTIVO E MODALITÀ DI PAGAMENTO</h3>
<p>
Il corrispettivo complessivo per i servizi oggetto del presente Contratto è fissato in
<strong>€ 18.000,00 (diciottomila/00)</strong>, IVA esclusa.
</p>
<p>
I pagamenti saranno effettuati secondo il seguente piano:
</p>
<ul>
  <li><strong>Milestone 1</strong>: € 6.000,00 entro 30 giorni dalla approvazione dei
      deliverable della fase 1;</li>
  <li><strong>Milestone 2</strong>: € 7.200,00 entro 30 giorni dalla approvazione e
      validazione del prototipo funzionale;</li>
  <li><strong>Milestone 3</strong>: € 4.800,00 entro 30 giorni dalla consegna finale e
      approvazione della documentazione.</li>
</ul>
<p>
Il Cliente si impegna a effettuare i pagamenti entro i termini indicati tramite bonifico
bancario. In caso di ritardo superiore a 15 giorni, si applicheranno gli interessi di mora
ai sensi del D.Lgs. 231/2002.
</p>

<h3>Art. 6 - TEMPISTICHE E MILESTONE</h3>
<p>
Il Contratto ha durata di 3 (tre) mesi, con inizio il 01/11/2025 e termine previsto il 31/01/2026.
</p>
<p>
Le Milestone sopra indicate sono vincolanti e il Fornitore si impegna a rispettarle.
Eventuali ritardi dovuti a causa di forza maggiore o a ritardi del Cliente nella fornitura
di informazioni necessarie comporteranno una proroga automatica delle scadenze.
</p>
<p>
Il Cliente avrà 7 (sette) giorni lavorativi dalla consegna dei deliverable di ciascuna
Milestone per accettarli o richiedere modifiche ragionevoli. L'assenza di comunicazioni
entro tale termine si intenderà come accettazione tacita.
</p>

<h3>Art. 7 - PROPRIETÀ INTELLETTUALE</h3>
<p>
Tutti i diritti di proprietà intellettuale sui progetti, schemi, software, firmware,
documentazione e altri materiali sviluppati dal Fornitore nell'ambito del presente Contratto
(di seguito "Opere") saranno trasferiti al Cliente al momento del pagamento finale.
</p>
<p>
Il trasferimento comprende il diritto di utilizzare, modificare, riprodurre, distribuire
e commercializzare le Opere senza limitazioni.
</p>
<p>
Il Fornitore si riserva il diritto di utilizzare le competenze e le conoscenze generiche
acquisite durante lo svolgimento dei servizi per altri progetti, purché non vengano
divulgate Informazioni Confidenziali del Cliente.
</p>
<p>
Il Cliente riconosce che eventuali componenti software open source utilizzati nel Progetto
rimangono soggetti alle rispettive licenze originali.
</p>

<h3>Art. 8 - GARANZIE E RESPONSABILITÀ</h3>
<p>
Il Fornitore garantisce che i servizi saranno eseguiti con professionalità, competenza e
secondo le best practice del settore elettronico e dell'ingegneria IoT.
</p>
<p>
Il prototipo consegnato sarà coperto da garanzia di 12 (dodici) mesi dalla data di
accettazione finale, limitatamente a difetti di progettazione e realizzazione imputabili
al Fornitore.
</p>
<p>
Sono esclusi dalla garanzia:
</p>
<ul>
  <li>Difetti derivanti da uso improprio o negligenza del Cliente;</li>
  <li>Modifiche apportate dal Cliente o da terzi non autorizzati;</li>
  <li>Danneggiamenti dovuti a cause esterne (sovratensioni, eventi atmosferici, ecc.);</li>
  <li>Normale usura dei componenti.</li>
</ul>
<p>
La responsabilità del Fornitore per danni diretti è limitata al valore del Contratto.
Il Fornitore non sarà responsabile per danni indiretti, lucro cessante o perdite commerciali.
</p>
```

**Caratteristiche Output**:
- 14 articoli dettagliati
- ~7.800 caratteri
- Milestone specifiche integrate
- Clausole proprietà intellettuale
- Garanzie e limitazioni responsabilità

---

## Esempio 3: Supply Contract Componenti Elettronici

### Input
```
Cliente: Manufacturing Electronics SpA
P.IVA: IT11223344556
Titolo: "Fornitura Componenti SMD per Linea Produzione 2025"
Tipo: Supply Contract
Data Inizio: 01/12/2025
Data Fine: 30/11/2026
Valore: €120.000
Clausole Speciali: "Fornitura trimestrale di componenti SMD (resistenze, condensatori, IC).
Certificazioni obbligatorie: CE, RoHS, REACH.
Consegna DAP stabilimento cliente.
Lead time massimo: 15 giorni lavorativi.
Controllo qualità: AQL 1.0.
Tracciabilità lotti completa.
Garanzia 24 mesi da difetti fabbricazione."
```

### Output Atteso (Estratto)

```html
<h3>Art. 1 - PREMESSE E OGGETTO</h3>
<p>
Il presente Contratto ha per oggetto la fornitura da parte di Supernova Industries S.r.l.
(di seguito "Fornitore") a favore di Manufacturing Electronics SpA (di seguito "Cliente")
di componenti elettronici SMD (Surface Mount Device) per la linea di produzione 2025.
</p>
<p>
La fornitura comprende componenti passivi (resistenze, condensatori ceramici e elettrolitici)
e circuiti integrati (IC) secondo le specifiche tecniche concordate e allegate al presente
Contratto (Allegato A - Specifiche Tecniche).
</p>

<h3>Art. 2 - CARATTERISTICHE DELLA FORNITURA</h3>
<p>
I componenti forniti dovranno rispettare le seguenti caratteristiche e standard:
</p>

<p><strong>Specifiche Tecniche Generali:</strong></p>
<ul>
  <li>Formato: SMD (Surface Mount Device) nelle dimensioni standard (0402, 0603, 0805, 1206,
      SOIC, QFP, BGA secondo specifiche);</li>
  <li>Tolleranze: Conformi alle specifiche dei datasheet di riferimento;</li>
  <li>Temperature di esercizio: -40°C / +85°C (o superiori secondo specifiche);</li>
  <li>Classe affidabilità: Industrial Grade minimo.</li>
</ul>

<p><strong>Certificazioni Obbligatorie:</strong></p>
<ul>
  <li><strong>Marcatura CE</strong>: Tutti i componenti devono essere conformi alle
      Direttive Europee applicabili (Direttiva Bassa Tensione 2014/35/UE, Direttiva
      Compatibilità Elettromagnetica 2014/30/UE);</li>
  <li><strong>RoHS</strong>: Conformità alla Direttiva 2011/65/UE e successive modifiche
      (Direttiva RoHS 3 - 2015/863/UE) relativa alla restrizione dell'uso di sostanze
      pericolose;</li>
  <li><strong>REACH</strong>: Conformità al Regolamento CE 1907/2006 concernente la
      registrazione, valutazione, autorizzazione e restrizione delle sostanze chimiche;</li>
  <li>Certificazioni aggiuntive: ISO 9001:2015 per il processo produttivo.</li>
</ul>

<p><strong>Documentazione Tecnica:</strong></p>
<p>
Per ciascun lotto di fornitura, il Fornitore dovrà fornire:
</p>
<ul>
  <li>Datasheet ufficiali dei componenti;</li>
  <li>Certificati di conformità RoHS/REACH;</li>
  <li>Dichiarazione di conformità CE (se applicabile);</li>
  <li>Report di test di qualità del lotto;</li>
  <li>Codici di tracciabilità (lotto e data produzione).</li>
</ul>

<h3>Art. 3 - MODALITÀ DI FORNITURA E CONSEGNA</h3>
<p>
La fornitura avverrà con cadenza trimestrale secondo il seguente piano:
</p>
<ul>
  <li>Q1 (dicembre 2025-febbraio 2026): € 30.000</li>
  <li>Q2 (marzo-maggio 2026): € 30.000</li>
  <li>Q3 (giugno-agosto 2026): € 30.000</li>
  <li>Q4 (settembre-novembre 2026): € 30.000</li>
</ul>

<p><strong>Termini di Consegna:</strong></p>
<p>
Il Fornitore si impegna a consegnare i componenti entro un lead time massimo di
<strong>15 (quindici) giorni lavorativi</strong> dalla ricezione dell'ordine scritto
del Cliente.
</p>
<p>
Gli ordini dovranno essere inviati via PEC o email con almeno 7 giorni di anticipo
rispetto alla data di consegna desiderata.
</p>

<p><strong>Luogo e Modalità di Consegna:</strong></p>
<p>
La consegna avverrà con resa <strong>DAP (Delivered At Place)</strong> presso lo
stabilimento del Cliente sito in [indirizzo], secondo le clausole Incoterms 2020.
</p>
<p>
Il trasporto sarà a carico del Fornitore. Il trasferimento del rischio e della proprietà
avverrà al momento dello scarico presso lo stabilimento del Cliente, previa verifica di
conformità quantitativa (numero colli, peso).
</p>

<p><strong>Imballaggio ed Etichettatura:</strong></p>
<ul>
  <li>Imballaggio adeguato per il trasporto e la conservazione (imballi antistatici ESD
      per componenti sensibili);</li>
  <li>Etichettatura chiara con: codice componente, quantità, lotto, data produzione,
      codici QR per tracciabilità;</li>
  <li>Documenti di trasporto (DDT) conformi alla normativa italiana.</li>
</ul>

<h3>Art. 4 - CONTROLLO QUALITÀ E COLLAUDO</h3>
<p>
Il Fornitore si impegna a garantire standard di qualità elevati attraverso:
</p>
<ul>
  <li>Controllo qualità in ingresso sui lotti acquistati dai produttori;</li>
  <li>Test di campionamento secondo standard <strong>AQL 1.0</strong> (Acceptable Quality
      Limit) conformi alla norma ISO 2859-1;</li>
  <li>Ispezioni funzionali e dimensionali su campioni statistici;</li>
  <li>Tracciabilità completa dei lotti dall'origine.</li>
</ul>

<p><strong>Ispezioni del Cliente:</strong></p>
<p>
Il Cliente ha diritto di effettuare ispezioni in ingresso sui componenti ricevuti entro
10 (dieci) giorni lavorativi dalla consegna.
</p>
<p>
In caso di non conformità riscontrate durante l'ispezione (difetti oltre AQL 1.0,
componenti non conformi alle specifiche, certificazioni mancanti), il Cliente ha diritto di:
</p>
<ul>
  <li>Rifiutare il lotto;</li>
  <li>Richiedere sostituzione entro 5 giorni lavorativi;</li>
  <li>Richiedere credit note per componenti difettosi.</li>
</ul>
<p>
L'assenza di contestazioni entro 10 giorni si intende come accettazione definitiva del lotto.
</p>

<p><strong>Gestione Non Conformità:</strong></p>
<p>
In caso di non conformità gravi o ripetute (≥3 lotti non conformi in 6 mesi), il Cliente
si riserva il diritto di:
</p>
<ul>
  <li>Richiedere audit presso il Fornitore;</li>
  <li>Sospendere temporaneamente gli ordini fino a risoluzione;</li>
  <li>Risolvere il Contratto per inadempimento grave.</li>
</ul>

<h3>Art. 6 - GARANZIE</h3>
<p>
Il Fornitore garantisce che:
</p>
<ul>
  <li>I componenti forniti sono nuovi, non ricondizionati, di prima scelta;</li>
  <li>I componenti sono conformi alle specifiche tecniche concordate e ai datasheet ufficiali;</li>
  <li>I componenti sono privi di difetti di fabbricazione;</li>
  <li>Tutte le certificazioni (CE, RoHS, REACH) sono autentiche e valide.</li>
</ul>

<p><strong>Durata Garanzia:</strong></p>
<p>
I componenti sono coperti da garanzia di <strong>24 (ventiquattro) mesi</strong> dalla
data di consegna contro difetti di fabbricazione e non conformità.
</p>
<p>
Durante il periodo di garanzia, il Fornitore si impegna a:
</p>
<ul>
  <li>Sostituire gratuitamente i componenti difettosi entro 10 giorni lavorativi;</li>
  <li>Rimborsare il Cliente per eventuali danni diretti causati da componenti difettosi
      (limitatamente al valore del lotto difettoso);</li>
  <li>Fornire supporto tecnico per troubleshooting.</li>
</ul>

<p><strong>Esclusioni Garanzia:</strong></p>
<p>
La garanzia non copre:
</p>
<ul>
  <li>Difetti causati da uso improprio, stoccaggio inadeguato, installazione errata;</li>
  <li>Danni da sovratensioni, ESD non controllato, eventi atmosferici;</li>
  <li>Modifiche apportate dal Cliente o terzi;</li>
  <li>Normale obsolescenza tecnologica.</li>
</ul>

<h3>Art. 11 - TRACCIABILITÀ E OBSOLESCENZA</h3>
<p>
Il Fornitore si impegna a:
</p>
<ul>
  <li>Mantenere tracciabilità completa dei lotti per almeno 5 anni;</li>
  <li>Comunicare al Cliente con almeno 12 mesi di anticipo l'obsolescenza programmata
      (EOL - End of Life) di componenti critici;</li>
  <li>Proporre componenti alternativi pin-to-pin compatibili in caso di EOL;</li>
  <li>Mantenere scorte di sicurezza (safety stock) per almeno 3 mesi di fabbisogno.</li>
</ul>

<p><strong>Gestione Obsolescenza:</strong></p>
<p>
In caso di obsolescenza di componenti critici, il Fornitore si impegna a:
</p>
<ul>
  <li>Notificare immediatamente il Cliente;</li>
  <li>Proporre Last Time Buy (LTB) con quantitativi concordati;</li>
  <li>Identificare componenti sostitutivi equivalenti;</li>
  <li>Supportare il Cliente nella qualificazione dei sostituti.</li>
</ul>

<h3>Art. 13 - FORZA MAGGIORE</h3>
<p>
Le Parti non saranno responsabili per ritardi o inadempimenti causati da eventi di forza
maggiore, quali:
</p>
<ul>
  <li>Calamità naturali (terremoti, alluvioni, incendi);</li>
  <li>Guerre, atti terroristici, disordini civili;</li>
  <li>Epidemie/pandemie con restrizioni governative;</li>
  <li>Interruzioni prolungate delle infrastrutture (energia, telecomunicazioni, trasporti);</li>
  <li>Blocchi doganali o divieti di esportazione/importazione.</li>
</ul>
<p>
In caso di forza maggiore, la Parte impedita dovrà:
</p>
<ul>
  <li>Notificare immediatamente l'altra Parte;</li>
  <li>Fornire evidenza documentale dell'evento;</li>
  <li>Adottare tutte le misure ragionevoli per minimizzare l'impatto;</li>
  <li>Riprendere l'adempimento non appena cessato l'impedimento.</li>
</ul>
<p>
Se l'impedimento perdura per oltre 60 giorni, ciascuna Parte ha diritto di risolvere il
Contratto senza penali, con pagamento delle forniture già effettuate.
</p>

<h3>Art. 15 - LEGGE APPLICABILE E FORO COMPETENTE</h3>
<p>
Il presente Contratto è regolato dalla legge italiana. Per qualsiasi controversia derivante
dal presente Contratto sarà competente in via esclusiva il Foro di Milano.
</p>
<p>
Le Parti si impegnano a tentare una risoluzione amichevole delle controversie tramite
negoziazione diretta per almeno 30 giorni prima di adire le vie legali.
</p>
```

**Caratteristiche Output**:
- 16 articoli molto dettagliati
- ~9.500 caratteri
- Normative CE/RoHS/REACH integrate
- Incoterms 2020
- Gestione obsolescenza
- AQL e controlli qualità

---

## Formato Output Standard

### Struttura HTML

Tutti i contratti generati seguono questo formato:

```html
<h3>Art. N - TITOLO ARTICOLO</h3>
<p>Paragrafo principale dell'articolo...</p>

<p><strong>Sottotitolo (se presente):</strong></p>
<ul>
  <li>Punto elenco 1</li>
  <li>Punto elenco 2</li>
  <li>Punto elenco 3</li>
</ul>

<p><em>Note aggiuntive in corsivo se necessarie</em></p>
```

### CSS Styling nel PDF

Il template `/resources/views/pdf/customer-contract.blade.php` applica automaticamente:

```css
.article-title {  /* Tag <h3> */
    font-weight: bold;
    color: #1e40af;
    margin-bottom: 8px;
    font-size: 11pt;
}

.article-content {  /* Tag <p> */
    text-align: justify;
    padding-left: 15px;
}

ul {
    list-style-type: disc;
    padding-left: 24px;
    margin: 8px 0;
}

li {
    margin: 4px 0;
    line-height: 1.6;
}
```

### Qualità Attesa

| Metrica | Target | Tipico AI |
|---------|--------|-----------|
| Lunghezza NDA | 3.000-5.000 char | ✓ 4.200 |
| Lunghezza Service | 5.000-8.000 char | ✓ 7.800 |
| Lunghezza Supply | 6.000-9.000 char | ✓ 9.500 |
| Articoli NDA | 8 | ✓ 8 |
| Articoli Service | 12-14 | ✓ 14 |
| Articoli Supply | 15-16 | ✓ 16 |
| Riferimenti normativi | Presenti | ✓ Sì |
| Clausole personalizzate | Integrate | ✓ Sì |
| Linguaggio tecnico-giuridico | Professionale | ✓ Sì |
| Errori grammaticali | 0-2 | ~1 |

## Note sull'Utilizzo degli Esempi

1. **Personalizzazione**: Gli output mostrati sono esempi. Ogni generazione sarà leggermente diversa.
2. **Validazione**: Rivedi SEMPRE il contenuto generato prima dell'uso.
3. **Revisione Legale**: Fai validare da un avvocato prima della firma.
4. **Adattamento**: Modifica le clausole per il caso specifico.
5. **Disclaimer**: L'AI genera bozze professionali ma non sostituisce la consulenza legale.
