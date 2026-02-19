# 🍽️ Vite & Gourmand

Application web développée dans le cadre du TP Développeur Web & Web Mobile.

---

# 📌 Présentation

Vite & Gourmand est une application permettant :

- La consultation des menus
- La commande en ligne
- La gestion des commandes (Employé)
- L’administration et les statistiques (Admin)

---

# 🛠️ Stack Technique

## Front-end
- HTML5
- CSS3 / Bootstrap
- JavaScript (Fetch API)
- Application SPA

## Back-end
- PHP 8
- PDO
- API REST
- Architecture MVC

## Base relationnelle
- MySQL

## Base NoSQL
- MongoDB (statistiques commandes)

---

# ⚙️ Installation en local

## 1️⃣ Cloner le projet

git clone VOTRE_REPO

---

## 2️⃣ Créer la base de données

Dans MySQL :

CREATE DATABASE vite_gourmand;

Puis importer :

seed.sql

---

## 3️⃣ Configuration BDD

Modifier :

backend/config/database.php

Paramètres :

DB_HOST=localhost  
DB_NAME=vite_gourmand  
DB_USER=root  
DB_PASS=

---

## 4️⃣ Lancer le serveur

php -S localhost:8000

Puis accéder :

http://localhost:8000

---

# 🔐 Comptes de démonstration

## Administrateur
Email : admin@mail.fr  
Mot de passe : Administrateur123!

## Employé
Email : julie.employe@mail.com  
Mot de passe : Employe123!

---

# 🔑 Fonctionnalités

## Visiteur
- Consultation menus
- Filtres dynamiques
- Création compte
- Contact

## Utilisateur
- Commander un menu
- Suivi commande
- Annulation si non acceptée
- Avis client

## Employé
- Gestion menus
- Mise à jour statut commande
- Validation avis

## Administrateur
- Création compte employé
- Désactivation compte
- Statistiques MongoDB
- Calcul chiffre d’affaires

---

# 🔐 Sécurité

- Hash password (password_hash)
- Protection SQL Injection (PDO)
- Contrôle des rôles
- Reset mot de passe par token
- API sécurisée

---

# 📄 Documents fournis

- seed.sql
- manuel_utilisateur.pdf
- documentation_technique.pdf
- charte_graphique.pdf
- gestion_projet.pdf
