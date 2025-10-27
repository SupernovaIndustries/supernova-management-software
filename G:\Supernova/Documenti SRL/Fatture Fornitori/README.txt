FATTURE FORNITORI - GESTIONE COMPONENTI ELETTRONICI
====================================================

Questa cartella contiene tutte le fatture d'acquisto dai fornitori di componenti.

STRUTTURA:
  Fatture Fornitori/
  ├── [Anno]/
  │   ├── Mouser/
  │   ├── DigiKey/
  │   ├── Farnell/
  │   └── Altri/

FUNZIONAMENTO:
- Le fatture vengono caricate automaticamente tramite il sistema di gestione
- Ogni fattura è collegata ai componenti importati tramite il numero di fattura
- Le fatture sono sincronizzate su Nextcloud via Syncthing
- Accessibile a: Amministratori + Contabile

FORMATI ACCETTATI:
- PDF (preferito)
- JPG, PNG (per fatture scannerizzate)

ACCESSO:
- Sistema di gestione: Filament Admin > Components > Import
- Nextcloud: Documenti SRL/Fatture Fornitori/
- Syncthing: Sincronizzato automaticamente

INFORMAZIONI FATTURA:
Ogni fattura contiene i seguenti metadati nel database:
- Numero fattura
- Data fattura
- Totale (€)
- Fornitore
- Componenti acquistati
- Note

Per maggiori informazioni, consultare il manuale del sistema di gestione.

Generato: {date('Y-m-d H:i:s')}
Sistema: Supernova Management