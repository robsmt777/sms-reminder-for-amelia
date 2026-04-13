# SMS Reminder for Amelia

Plugin WordPress qui envoie automatiquement un SMS de rappel à vos clients 24h avant leurs rendez-vous [Amelia Booking](https://tms-plugins.sjv.io/3kjk9n).

Développé par **[Capitaine Site](https://capitainesite.com/)** — Agence experte WordPress.

---

## ✨ Fonctionnalités

- 📱 Envoi automatique de SMS de rappel via **SMS Partner** (autres passerelles à venir)
- 🔄 **Détection des reports** : si un RDV est déplacé, un nouveau SMS est envoyé
- 🕒 Lecture directe des tables Amelia, sans dépendance à l'API interne
- 🎨 Template de message **100% personnalisable** avec variables (`%customer_full_name%`, `%appointment_start_time%`, etc.)
- ⚙️ **Interface admin complète** : clé API, expéditeur, template, fenêtre de rappel, fréquence du cron
- 📊 **Page de logs** avec statuts (envoyé, délivré, échec) et bouton "Exécuter maintenant"
- 📥 **Webhook DLR** pour remonter les accusés de réception SMS Partner
- 🔐 Support des `define()` dans `wp-config.php` pour déploiements multi-serveurs
- 🧹 Purge hebdomadaire automatique des anciens logs

---

## 📦 Installation

1. Téléchargez le zip depuis la section [Releases](../../releases)
2. WordPress → **Extensions → Ajouter → Téléverser une extension**
3. Activez le plugin
4. Allez dans **Réglages → SMS Reminder** et renseignez votre clé API SMS Partner

### Prérequis

- WordPress 6.0+
- PHP 7.4+
- [Amelia Booking](https://tms-plugins.sjv.io/3kjk9n) installé et actif
- Un compte [SMS Partner](https://www.smspartner.fr/) avec une clé API

---

## ⚙️ Configuration

### Via l'interface (recommandé)

`Réglages → SMS Reminder` permet de configurer tout ce qui suit depuis le dashboard :

| Réglage | Description |
|---|---|
| Clé API | Token SMS Partner |
| Expéditeur | Nom affiché sur le SMS (3-11 caractères alphanumériques) |
| Mode sandbox | Activer pour tester sans consommer de crédits |
| Template du message | Message SMS avec variables dynamiques |
| Fenêtre de rappel | Heures min/max avant le RDV (par défaut 23h–25h) |
| Fréquence du cron | De 1 min à 1h |
| Rétention des logs | Nombre de jours avant purge (par défaut 30) |

### Via `wp-config.php` (déploiements automatisés)

```php
define( 'SRFA_API_KEY', 'ta_cle_api_smspartner' );
define( 'SRFA_SANDBOX', false );
define( 'SRFA_SENDER',  'MonSalon' );
```

Les `define()` sont prioritaires sur les valeurs en BDD.

---

## 🎨 Variables du template

| Variable | Exemple |
|---|---|
| `%customer_first_name%` | Sophie |
| `%customer_last_name%` | Martin |
| `%customer_full_name%` | Sophie Martin |
| `%employee_first_name%` | Claire |
| `%employee_last_name%` | Dupont |
| `%employee_full_name%` | Claire Dupont |
| `%service_name%` | Soin du visage |
| `%location_name%` | Salon Paris |
| `%appointment_date%` | lundi 14 avril |
| `%appointment_start_time%` | 14h30 |

**Template par défaut** :
> %location_name% : Bonjour %customer_full_name%, RDV demain à %appointment_start_time% avec %employee_first_name% pour %service_name%. Merci de prévenir si annulation.

---

## 📥 Webhook DLR (accusés de réception)

Configurez l'URL suivante dans votre compte SMS Partner pour recevoir les statuts de livraison :

```
https://votre-site.fr/wp-json/srfa/v1/sms-delivery
```

---

## 🗺️ Roadmap

- [ ] Passerelle **OVH SMS**
- [ ] Passerelle **Twilio**
- [ ] Architecture d'extension (gateways enregistrables)
- [ ] Publication sur WordPress.org (version gratuite)
- [ ] Notifications email en cas d'échec critique
- [ ] Templates multilingues

---

## 📄 Licence

Ce plugin est distribué sous licence **GPL-2.0-or-later**. Voir [LICENSE](LICENSE).

---

## 💡 Transparence

> Les liens vers Amelia Booking dans ce README sont des liens d'affiliation. Si tu achètes Amelia via ces liens, l'agence Capitaine Site touche une petite commission, sans surcoût pour toi. Merci pour le soutien 🙏

---

## 🤝 Contact

Développement & support : [Capitaine Site](https://capitainesite.com/)
