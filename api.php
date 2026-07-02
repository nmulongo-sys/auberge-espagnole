<?php
/**
 * api.php — Stockage partagé pour l'auberge espagnole
 *
 * À placer dans le même dossier que pique-nique.html (htdocs/ sur InfinityFree).
 *
 * PRÉREQUIS (InfinityFree) :
 *   1. Créer data.json manuellement dans le File Manager (fichier vide ou contenu ci-dessous).
 *   2. Donner les droits 666 à data.json (clic droit → Permissions/Chmod → 666).
 *
 * Contenu initial de data.json si tu le crées à la main :
 *   {"event":{"titre":"Pique-nique — auberge espagnole","date":"","lieu":"",
 *    "debut":"12:00","fin":"16:00","note":"","attendus":""},"people":[],"items":[]}
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store, no-cache');

// Répondre immédiatement aux pre-flight CORS (certains hébergeurs l'exigent)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$file = __DIR__ . '/data.json';

/* ---- LECTURE ---- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists($file)) {
        echo 'null';
    } else {
        echo file_get_contents($file);
    }
    exit;
}

/* ---- ÉCRITURE ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');

    if (!$body) {
        http_response_code(400);
        echo '{"error":"empty body"}';
        exit;
    }

    // Vérification : doit être du JSON valide
    $decoded = json_decode($body);
    if ($decoded === null) {
        http_response_code(400);
        echo '{"error":"invalid JSON"}';
        exit;
    }

    // Écriture atomique avec verrou (LOCK_EX)
    $result = file_put_contents($file, $body, LOCK_EX);

    if ($result === false) {
        http_response_code(500);
        // Message explicite pour aider au débogage depuis la Console navigateur
        echo '{"error":"write_failed","hint":"Vérifie que data.json existe et a les droits 666 (chmod). Sur InfinityFree : File Manager → clic droit data.json → Permissions → 666."}';
    } else {
        echo '{"ok":true,"bytes":' . $result . '}';
    }
    exit;
}

/* ---- Méthode non supportée ---- */
http_response_code(405);
echo '{"error":"method not allowed"}';
