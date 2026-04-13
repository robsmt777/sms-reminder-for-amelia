# Changelog — SMS Reminder for Amelia

Toutes les modifications notables du plugin sont documentées ici.
Format : [version] — date · description

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
