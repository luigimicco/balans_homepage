# Iscrizioni dal sito — come funziona e come si attiva

Quando qualcuno si iscrive alla waiting list o chiede una demo, il sito fa
tre cose, in quest'ordine:

1. **registra il contatto** nel gestionale dello studio (StudioGestione),
   chiamando la sua API;
2. **avvisa il team** con un'email a `info@balansapp.it`, che riporta i dati
   dell'iscritto e l'esito del passo 1;
3. **scrive all'iscritto** l'email di conferma.

Prima questo lavoro lo faceva Web3Forms, che è stato rimosso: adesso fa tutto
uno script sul nostro hosting OVH.

> **Se il passo 1 fallisce, l'iscrizione non si perde.** L'email del passo 2
> parte comunque e dice a chiare lettere `NON registrato — da inserire a
> mano`, con tutti i dati sotto. È la copia di riserva.

Serve una configurazione iniziale, da fare una volta sola.

---

## 0. Verifica che l'hosting lo permetta (bastano FTP e 2 minuti)

Si fa senza password e senza accesso al pannello OVH. Carica via FTP il solo
file `verifica-ambiente.php` (sta nella cartella principale del progetto,
quella con `index.html`) e aprilo nel browser:

```
https://www.balansapp.it/verifica-ambiente.php?chiave=c4d275b44099
```

Controlla che l'hosting sappia spedire email **e** uscire in HTTPS verso il
gestionale. Se le voci sono tutte verdi si può procedere. Se qualcuna è rossa,
manda uno screenshot a chi gestisce l'hosting: le voci rosse dicono
esattamente cosa manca. A verifica conclusa cancella il file dal server.

---

## 1. Procurati le due credenziali

### a) La password di `info@balansapp.it`

Il mittente configurato è `info@balansapp.it`, la casella che il sito usa già
nei contatti. Non serve crearne una nuova: serve solo la sua **password**, la
stessa con cui si legge la posta.

Chiedila a chi ha l'accesso al [Manager OVH](https://www.ovh.com/manager/)
(la trova in **Web Cloud → Email → balansapp.it**, dove può anche
reimpostarla).

Usare `info@` invece di un `no-reply@` ha due vantaggi: chi riceve l'email può
rispondere e la risposta arriva dove la leggete già, e gli avvisi di errore
per gli indirizzi digitati male finiscono in una casella che qualcuno apre.

### b) La chiave API di StudioGestione

La genera il responsabile dello studio dalla **scheda studio → tab "API
esterne"**. Due cose da sapere:

- il token viene mostrato **una sola volta**, alla creazione: va copiato
  subito e trasmesso su un canale privato, mai via email in chiaro;
- servono i permessi **Lettura** e **Creazione** sui soli **Contatti**. Tutto
  il resto va lasciato vuoto: la chiave vive in un file di testo su un hosting
  condiviso, e non deve poter modificare o cancellare nulla nello studio.

---

## 2. Compila `api/config.php`

Apri `api/config.php` con un editor di testo (va bene anche Blocco note) e
sostituisci questi valori:

| Cosa | Con cosa |
|---|---|
| `'smtp_pass' => 'INSERISCI-LA-PASSWORD'` | la password della casella del passo 1a |
| `'studio_api_key' => 'INSERISCI-LA-CHIAVE-API'` | la chiave del passo 1b (inizia con `sk_live_`) |
| `'diagnostics_token' => 'CAMBIA-QUESTA-STRINGA'` | una parola lunga e casuale a tua scelta, es. `k7fp2-verifica-balans-9x` |

Lascia `'studio_api_dry_run' => true` per adesso: con il dry run attivo si
prova tutto il resto **senza creare contatti veri** nello studio. Si spegne
all'ultimo passo.

Se un domani si volesse spedire da un indirizzo diverso, va cambiato sia in
`smtp_user` sia in `from_email`: i due valori devono restare identici,
altrimenti OVH rifiuta l'invio.

> `config.php` contiene una password e una chiave API, quindi non finisce mai
> su GitHub. Se cambi computer devi ricrearlo partendo da
> `config.example.php`.

---

## 3. (facoltativo) Prova sul tuo computer prima di pubblicare

Le email partono davvero anche dal tuo PC: il collegamento al server di posta
OVH funziona da qualsiasi connessione. Nel terminale, dalla cartella del
progetto:

```bash
php -S localhost:8080
```

Poi apri nel browser (`token` è la parola scelta al passo 2):

```
http://localhost:8080/api/diagnostica.php?token=IL-TUO-TOKEN
```

Da lì trovi tutti i controlli e le prove. Vedi la sezione **"Le prove che si
possono fare"** più sotto.

Puoi anche compilare il form su `http://localhost:8080`: con il dry run attivo
non viene creato nessun contatto, ma le due email partono davvero.

> Non serve modificare `allowed_origins` per la prova in locale: quando il
> sito gira su `localhost` l'endpoint accetta le sue pagine automaticamente.

---

## 4. Carica i file sul server via FTP

Prima di iniziare, in FileZilla attiva **Server → Forza visualizzazione file
nascosti**: senza questa opzione i file che iniziano con un punto (come
`.htaccess`) restano invisibili e rischi di non caricarli.

Carica, mantenendo la stessa struttura di cartelle:

```
api/                        <- tutta la cartella, compresi .htaccess e config.php
assets/js/script.js         <- sovrascrivi quello presente
index.html                  <- sovrascrivi
business/index.html         <- sovrascrivi
privacy/index.html          <- sovrascrivi
cookie/index.html           <- sovrascrivi
```

Se sul server è rimasto `api/send-confirmation.php` da una versione
precedente, **cancellalo**: non serve più.

Le quattro pagine HTML vanno ricaricate perché richiamano `script.js?v=5`:
senza il numero aggiornato i browser continuerebbero a usare la versione
vecchia del JavaScript, tenuta in cache per un anno.

---

## 5. Verifica in produzione

Apri, sostituendo il token con quello scelto al passo 2:

```
https://www.balansapp.it/api/diagnostica.php?token=IL-TUO-TOKEN
```

Devono essere **tutte verdi**, tranne la voce "Registrazione dei contatti
nello studio", che finché il dry run è attivo avvisa `PROVA A VUOTO`. È
corretto così.

Poi fai le prove elencate qui sotto.

---

## Le prove che si possono fare

Tutte queste, tranne l'ultima, **non creano nessun contatto** nello studio.

| Cosa | Indirizzo | Cosa dimostra |
|---|---|---|
| Controlli | `?token=…` | configurazione, posta, chiave API |
| Chiave API | `?token=…&api=1` | che la chiave sia valida — è una sola **lettura** |
| Payload | `?token=…&payload=1` | il JSON che verrebbe mandato allo studio |
| Anteprima email | `?token=…&anteprima=1` | l'email che vedrà l'iscritto |
| Anteprima notifica | `?token=…&anteprima_notifica=1` | l'email che riceverà il team |
| Invio conferma | `?token=…&prova=tua@email.it` | spedisce davvero la conferma |
| Invio notifica | `?token=…&notifica=tua@email.it` | spedisce davvero la notifica interna |

Aggiungendo `&tipo=demo` si usa la richiesta demo invece della waiting list.

Controlla la posta in arrivo **e la cartella spam**.

### L'ultima prova: l'accensione

Da fare **insieme al responsabile dello studio**, quando tutto il resto è
verde.

1. in `config.php` metti `'studio_api_dry_run' => false`, ricarica il file;
2. iscriviti dal sito una volta sola, con un indirizzo concordato;
3. il responsabile controlla di vedere il contatto in anagrafica;
4. tu controlli che a `info@balansapp.it` sia arrivata la notifica con scritto
   `Registrato in StudioGestione — Contatto creato con ID …`;
5. il contatto di prova si può cancellare.

Quando tutto funziona puoi cancellare `diagnostica.php` dal server: le
iscrizioni continuano a funzionare lo stesso.

---

## Se qualcosa non va

| Messaggio | Cosa significa |
|---|---|
| `SMTP connect() failed` | il server non riesce a raggiungere `ssl0.ovh.net`. Prova a mettere `'smtp_port' => 465` e `'smtp_secure' => 'ssl'` in `config.php`. |
| `Could not authenticate` | password sbagliata, oppure `smtp_user` non è l'indirizzo completo della casella. |
| `SMTP Error: The following From address failed` | `from_email` è diverso da `smtp_user`: devono coincidere. |
| Nessun errore ma l'email non arriva | guarda nello spam; se è lì, scrivi a OVH per far attivare la firma DKIM sul dominio. |
| La pagina di diagnostica risponde "Not Found" | il token nell'indirizzo non corrisponde a quello in `config.php`. |
| Notifica con `HTTP 401` | la chiave API è sbagliata o è stata revocata. |
| Notifica con `HTTP 403` | la chiave non ha il permesso di **creare** contatti: va rigenerata spuntando anche "Creazione" su "Contatti". |
| Notifica con `HTTP 422` | lo studio ha rifiutato i dati; il motivo preciso è scritto nella notifica stessa. |
| Notifica con `HTTP 429` | superato il limite di richieste della chiave (60 al minuto). |

Gli ultimi invii restano registrati in fondo alla pagina di diagnostica
(gli indirizzi non sono mai scritti in chiaro, solo un'impronta anonima).

---

## Come cambiare il testo delle email

Tutto il testo è in `api/templates.php`:

- `balans_email_content()` — le email agli iscritti: oggetto, titolo,
  paragrafi e testo del pulsante, separati per `waitlist` e `demo`;
- `balans_internal_notification()` — la notifica al team.

Il resto del file è l'impaginazione e normalmente non va toccato. Per vedere
l'anteprima senza spedire nulla usa gli indirizzi nella tabella qui sopra.

---

## Cose da sapere

- **Costo: zero.** Si usano l'hosting e la casella email che paghi già a OVH,
  più l'API dello studio che è già inclusa nel suo gestionale.
- **La chiave API non passa mai dal browser.** Vive solo in `config.php`, sul
  server, protetto da `.htaccess`. È il motivo per cui il form del sito non
  parla più direttamente con un servizio esterno come faceva con Web3Forms.
- **Limiti:** gli hosting condivisi OVH hanno un tetto di invii giornalieri
  (nell'ordine delle centinaia) e la chiave API accetta 60 richieste al
  minuto. Per una waiting list è ampiamente sufficiente.
- **Anti-abuso:** l'endpoint accetta richieste solo dalle pagine di
  `balansapp.it`, scarta i bot tramite un campo nascosto, non riscrive due
  volte allo stesso indirizzo **per lo stesso form** entro 24 ore e limita il
  numero di invii per IP (valori modificabili in `config.php`). Waitlist e
  demo hanno contatori separati: chi si iscrive alla waitlist e poi chiede la
  demo con la stessa email riceve entrambe le conferme.
- **Il consenso privacy** è obbligatorio sul form della waiting list e viene
  registrato nelle note del contatto. Il form della demo non ha la checkbox:
  nelle note viene scritto esplicitamente che il consenso non era richiesto da
  quel form.
