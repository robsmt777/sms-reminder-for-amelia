# Changelog — SMS Reminder for Amelia

Toutes les modifications notables du plugin sont documentées ici.
Format : [version] — date · description

---

## [1.2.0] — 2026-04-14

### Ajouté
- **Internationalisation complète** : toutes les chaînes utilisateur sont désormais wrappées dans les helpers i18n WordPress (`__()`, `esc_html__()`, etc.) avec le text-domain `sms-reminder-for-amelia`.
- **Langue source passée à l'anglais** (convention WordPress.org).
- **Traduction française complète** (`fr_FR.po` + `fr_FR.mo`) fournie dans `/languages/` — aucun changement visible pour les utilisateurs francophones, le français reste la langue affichée.
- **Fichier `.pot` de référence** (`languages/sms-reminder-for-amelia.pot`) pour que n'importe quel traducteur puisse créer sa propre locale (Loco Translate, Poedit, WPML, etc.).
- Hook `load_plugin_textdomain()` au démarrage pour charger les traductions depuis `/languages/`.
- Le plugin est désormais compatible avec les systèmes de traduction automatique WordPress, [translate.wordpress.org](https://translate.wordpress.org/), Loco Translate, WPML, Polylang, TranslatePress.

### Technique
- ~140 chaînes uniques extraites et traduites.
- Les `error_log()` internes (côté développeur) restent non traduits, comme le veut la convention WP.

---

## [1.1.0] — 2026-04-14

### Ajouté
- **Deux slots de rappel par RDV** : SMS 1 (obligatoire) + SMS 2 optionnel, chacun avec son propre timing et son propre message.
- **Timings prédéfinis** : `10 min / 30 min / 1h / 2h / 4h / 8h / 12h / 24h / 48h` avant le RDV.
- **Template de message indépendant par slot** — par exemple SMS 1 détaillé 24h avant, SMS 2 court 1h avant.
- **Aperçu live par slot** dans la page Réglages, avec compteur de caractères et segments SMS.
- Colonne **`Slot`** dans la page Logs (badge bleu pour SMS 1, violet pour SMS 2) pour identifier facilement l'origine de chaque envoi.
- Affichage des **slots actifs** dans le bloc "Informations système" de la page Réglages.

### Modifié
- Le cron boucle désormais sur chaque slot actif. Dedup par `(appointment_id, appointment_datetime, reminder_slot)` : report de RDV → tous les slots actifs renvoient un SMS ; SMS 1 et SMS 2 sont totalement indépendants.
- Fenêtre de recherche recalculée depuis l'offset du slot + fréquence du cron, au lieu de valeurs figées 23h–25h.
- Nouveau schéma de table : colonne `reminder_slot TINYINT(1)` avec index dédié.

### Migration automatique
- Installs v1.0 : la colonne `reminder_slot` est ajoutée (anciens logs marqués `slot=1`) au chargement suivant.
- Options v1.0 (`message_template`, `reminder_hours_min`, `reminder_hours_max`) converties en structure `slots[]` : l'ancien template devient le message de SMS 1 à 24h, SMS 2 reste désactivé.

### Breaking changes
- Les clés d'option `message_template`, `reminder_hours_min`, `reminder_hours_max` sont supprimées (remplacées par `slots`). La migration est transparente pour les installs existantes.

---

## [1.0.0] — 2026-04-13

Première version publique du plugin, développée par **Capitaine Site — Agence experte WordPress** (https://capitainesite.com/).

### Fonctionnalités
- Envoi automatique de SMS de rappel 24h avant chaque rendez-vous Amelia Booking, via la passerelle **SMS Partner**
- Lecture directe des tables Amelia (`amelia_appointments`, `amelia_customer_bookings`, `amelia_users`, `amelia_services`, `amelia_locations`)
- Comparaison de la fenêtre de rappel en UTC (alignée avec le stockage interne d'Amelia)
- Détection des reports de RDV : si la date/heure change après envoi, un nouveau SMS part automatiquement
- Template de message entièrement personnalisable avec variables (`%customer_full_name%`, `%appointment_start_time%`, etc.)
- Page de réglages complète dans `Réglages → SMS Reminder` : clé API, sender, mode sandbox, template, fenêtre de rappel, fréquence du cron (1 à 60 min), rétention des logs
- Page de logs dans `Outils → SMS Reminder Logs` avec statuts, statistiques et bouton "Exécuter maintenant"
- Endpoint REST `/wp-json/srfa/v1/sms-delivery` pour les accusés de réception (DLR) SMS Partner
- Support des `define()` dans `wp-config.php` pour environnements multi-serveurs (`SRFA_API_KEY`, `SRFA_SANDBOX`, `SRFA_SENDER`)
- Purge hebdomadaire automatique des anciens logs
- Formatage intelligent des numéros français (0X, +33X, 0033X)

### À venir
- Support de passerelles SMS supplémentaires (OVH SMS, Twilio, etc.) via une architecture extensible
- Publication sur WordPress.org (version gratuite)
- Templates multilingues
- Notifications email en cas d'échec critique
