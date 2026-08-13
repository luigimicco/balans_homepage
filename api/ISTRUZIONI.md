# Email di conferma agli iscritti — come attivarla

Web3Forms continua a fare quello che faceva prima: avvisare **noi** a ogni
iscrizione. In più, adesso, un piccolo script sul nostro hosting OVH scrive
anche **all'utente** che si è appena iscritto.

Serve solo una configurazione iniziale, da fare una volta sola.

---

## 0. Verifica che l'hosting lo permetta (bastano FTP e 2 minuti)

Si fa senza password e senza accesso al pannello OVH. Carica via FTP il solo
file `verifica-ambiente.php` (sta nella cartella principale del progetto,
quella con `index.html`) e aprilo nel browser:

```
https://www.balansapp.it/verifica-ambiente.php?chiave=c4d275b44099
```

Se le voci sono tutte verdi si può procedere. Se qualcuna è rossa, manda uno
screenshot della pagina a chi gestisce l'hosting: le voci rosse dicono
esattamente cosa manca. A verifica conclusa cancella il file dal server.

---

## 1. Procurati la password di `info@balansapp.it`

Il mittente configurato è `info@balansapp.it`, la casella che il sito usa già
nei contatti. Non serve crearne una nuova: serve solo la sua **password**, la
stessa con cui si legge la posta.

Chiedila a chi ha l'accesso al [Manager OVH](https://www.ovh.com/manager/)
(la trova in **Web Cloud → Email → balansapp.it**, dove può anche
reimpostarla). Se preferisce non passartela, può compilare lui `config.php` al
passo 2 e caricarlo: il risultato è identico.

Usare `info@` invece di un `no-reply@` ha due vantaggi: chi riceve l'email può
rispondere e la risposta arriva dove la leggete già, e gli avvisi di errore
per gli indirizzi digitati male finiscono in una casella che qualcuno apre.

---

## 2. Compila `api/config.php`

Apri il file `api/config.php` con un editor di testo (va bene anche Blocco
note) e sostituisci due valori:

| Cosa | Con cosa |
|---|---|
| `'smtp_pass' => 'INSERISCI-LA-PASSWORD'` | la password della casella del passo 1 |
| `'diagnostics_token' => 'CAMBIA-QUESTA-STRINGA'` | una parola lunga e casuale a tua scelta, es. `k7fp2-verifica-balans-9x` |

Se un domani si volesse spedire da un indirizzo diverso, va cambiato sia in
`smtp_user` sia in `from_email`: i due valori devono restare identici,
altrimenti OVH rifiuta l'invio.

> `config.php` contiene una password, quindi non finisce mai su GitHub.
> Se cambi computer devi ricrearlo partendo da `config.example.php`.

---

## 2-bis. (facoltativo) Prova sul tuo computer prima di pubblicare

Le email partono davvero anche dal tuo PC: il collegamento al server di posta
OVH funziona da qualsiasi connessione, basta la password del passo 1.

Nel terminale, dalla cartella del progetto:

```bash
php -S localhost:8080
```

Poi apri nel browser (`token` è la parola che hai scelto al passo 2):

```
http://localhost:8080/api/diagnostica.php?token=IL-TUO-TOKEN
http://localhost:8080/api/diagnostica.php?token=IL-TUO-TOKEN&prova=tua@email.it
```

Se l'email arriva, la configurazione è giusta e in produzione funzionerà
uguale. Per fermare il server premi `Ctrl+C`.

Puoi anche compilare il form su `http://localhost:8080`, ma tieni presente che
in quel caso l'iscrizione viene inviata **davvero** a Web3Forms e la ritrovi
tra quelle vere: per la sola verifica dell'email conviene usare gli indirizzi
qui sopra.

> Non serve modificare `allowed_origins` per la prova in locale: quando il
> sito gira su `localhost` l'endpoint accetta le sue pagine automaticamente.

---

## 3. Carica i file sul server via FTP

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

Le quattro pagine HTML vanno ricaricate perché richiamano `script.js?v=3`:
senza il numero aggiornato i browser continuerebbero a usare la versione
vecchia del JavaScript, tenuta in cache per un anno.

---

## 4. Verifica che funzioni

Apri nel browser, sostituendo il token con quello che hai scelto al passo 2:

```
https://www.balansapp.it/api/diagnostica.php?token=IL-TUO-TOKEN
```

Vedrai un elenco di controlli: devono essere **tutti verdi**.

Poi fatti spedire davvero l'email di prova:

```
https://www.balansapp.it/api/diagnostica.php?token=IL-TUO-TOKEN&prova=tua@email.it
```

e la versione per la richiesta demo:

```
https://www.balansapp.it/api/diagnostica.php?token=IL-TUO-TOKEN&prova=tua@email.it&tipo=demo
```

Controlla la posta in arrivo **e la cartella spam**. Come ultima prova,
iscriviti alla waiting list dal sito con un tuo indirizzo: devi ricevere sia
la solita notifica di Web3Forms sia la nuova email di conferma.

Quando tutto funziona puoi cancellare `diagnostica.php` dal server: l'invio
continua a funzionare lo stesso.

---

## Se qualcosa non va

| Messaggio | Cosa significa |
|---|---|
| `SMTP connect() failed` | il server non riesce a raggiungere `ssl0.ovh.net`. Prova a mettere `'smtp_port' => 465` e `'smtp_secure' => 'ssl'` in `config.php`. |
| `Could not authenticate` | password sbagliata, oppure `smtp_user` non è l'indirizzo completo della casella. |
| `SMTP Error: The following From address failed` | `from_email` è diverso da `smtp_user`: devono coincidere. |
| Nessun errore ma l'email non arriva | guarda nello spam; se è lì, scrivi a OVH per far attivare la firma DKIM sul dominio. |
| La pagina di diagnostica risponde "Not Found" | il token nell'indirizzo non corrisponde a quello in `config.php`. |

Gli ultimi invii restano registrati in fondo alla pagina di diagnostica
(gli indirizzi non sono mai scritti in chiaro, solo un'impronta anonima).

---

## Come cambiare il testo delle email

Tutto il testo è in `api/templates.php`, nella funzione
`balans_email_content()`: oggetto, titolo, paragrafi e testo del pulsante,
separati per `waitlist` e `demo`. Il resto del file è l'impaginazione e
normalmente non va toccato.

Per vedere l'anteprima di come verranno, senza spedire nulla:

```
https://www.balansapp.it/api/diagnostica.php?token=IL-TUO-TOKEN&anteprima=1
https://www.balansapp.it/api/diagnostica.php?token=IL-TUO-TOKEN&anteprima=1&tipo=demo
```

---

## Cose da sapere

- **Costo: zero.** Si usano l'hosting e la casella email che paghi già a OVH.
- **Limiti:** gli hosting condivisi OVH hanno un tetto di invii giornalieri
  (nell'ordine delle centinaia). Per una waiting list è ampiamente
  sufficiente; se un giorno gli iscritti diventassero migliaia al giorno,
  converrà passare a un servizio dedicato.
- **Se lo script si rompe, l'iscrizione resta valida:** il sito invia prima a
  Web3Forms e solo dopo chiede l'email di conferma, ignorando l'esito. Nel
  peggiore dei casi l'utente non riceve la mail, ma risulta comunque iscritto
  e la notifica arriva a noi.
- **Anti-abuso:** l'endpoint accetta richieste solo dalle pagine di
  `balansapp.it`, non riscrive due volte allo stesso indirizzo **per lo stesso
  form** entro 24 ore e limita il numero di invii per IP (valori modificabili in
  `config.php`). Waitlist e demo hanno contatori separati: chi si iscrive alla
  waitlist e poi chiede la demo con la stessa email riceve entrambe le conferme.
