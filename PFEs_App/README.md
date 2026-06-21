# Plateforme de gestion et de planification automatique des soutenances PFE

## Description
Application web permettant de gérer les données des étudiants, professeurs, PFEs et salles, puis de générer automatiquement un planning de soutenances PFE en respectant plusieurs contraintes.

## Problématique
La planification manuelle des soutenances PFE peut entraîner des affectations déséquilibrées : jurys choisis de manière peu structurée, professeurs surchargés certains jours, soutenances consécutives, conflits de salles ou conflits de disponibilité.

## Fonctionnalités
- Importation dynamique des données depuis Excel
- Gestion des étudiants, professeurs, PFEs et salles
- Affectation automatique des encadrants et des jurys
- Génération automatique du planning
- Vérification des contraintes
- Visualisation interactive avec React.js
- Vue emploi du temps par date, heure et salle
- Dashboard avec statistiques
- Exportation PDF/Word

## Technologies utilisées
- Laravel / PHP
- MySQL
- Blade
- React.js / Vite
- JavaScript
- Bootstrap
- PhpSpreadsheet
- DomPDF
- PHPWord

## Installation

```bash
git clone <lien-du-repository>
cd PFEs_App
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve