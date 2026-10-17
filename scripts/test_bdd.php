<?php
declare(strict_types=1);
/** Teste le schéma : contraintes, jointure en étoile, RGPD. Usage : php scripts/test_bdd.php */
$racine = dirname(__DIR__);
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec(file_get_contents("$racine/DATA/creation_bdd.sql"));
$ok = 0; $ko = 0;
function t(string $nom, bool $bon): void { global $ok, $ko; $bon ? $ok++ : $ko++; echo ($bon ? '  OK ' : '  KO '), $nom, "\n"; }
function refuse(PDO $pdo, string $sql): bool { try { $pdo->exec($sql); return false; } catch (PDOException $e) { return true; } }

$n = fn(string $t) => (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
t('16 communes', $n('Dim_Commune') === 16);
t('10 cultures', $n('Dim_Culture') === 10);
t("code INSEE de Briançon garde son zéro", $pdo->query("SELECT code_insee FROM Dim_Commune WHERE nom='Briançon'")->fetchColumn() === '05023');
t('département renseigné pour les 16 communes (Reims = Marne)', (int) $pdo->query('SELECT COUNT(*) FROM Dim_Commune WHERE departement IS NOT NULL')->fetchColumn() === 16 && $pdo->query("SELECT departement FROM Dim_Commune WHERE nom='Reims'")->fetchColumn() === 'Marne');
t('libellé affichable pour chaque horizon', (int) $pdo->query('SELECT COUNT(*) FROM Dim_Temps WHERE libelle IS NOT NULL')->fetchColumn() === 2);
t('olivier : gdd_maturite_cj = NULL (pas 0)', $pdo->query("SELECT gdd_maturite_cj FROM Dim_Culture WHERE nom='Olivier'")->fetchColumn() === null);
t('aucune valeur marquée « verifiee » dans le seed', (int) $pdo->query("SELECT COUNT(*) FROM Dim_Culture WHERE statut_source='verifiee'")->fetchColumn() === 0);

$ins = "INSERT INTO Fact_Indicateur (id_commune,id_culture,id_temps,id_modele,gdd,jours_gel,jours_stress,bilan_hydrique,nb_annees_valides,verdict) VALUES ";
$pdo->exec($ins . "(5,1,1,1, 1900,12.0,1.5,-120,30,'favorable'), (5,1,2,1, 2100,6.0,9.0,-190,30,'a_surveiller')");
t('insertion de 2 faits', $n('Fact_Indicateur') === 2);
t('UNIQUE : doublon commune/culture/horizon/modèle refusé', refuse($pdo, $ins . "(5,1,1,1, 1,1,1,1,1,NULL)"));
t('FK : commune inexistante refusée', refuse($pdo, $ins . "(99,1,1,1, 1,1,1,1,1,NULL)"));
t("CHECK : verdict hors liste refusé", refuse($pdo, $ins . "(5,1,1,2, 1,1,1,1,1,'super')"));
t("CHECK : code INSEE de 4 caractères refusé", refuse($pdo, "INSERT INTO Dim_Commune (nom,code_insee,latitude,longitude) VALUES ('X','5023',1,1)"));
t("CHECK : horizon au-delà de 2049 refusé", refuse($pdo, "INSERT INTO Dim_Temps (horizon,libelle,annee_debut,annee_fin,nb_annees) VALUES ('h','h',2030,2050,21)"));

$q = $pdo->prepare(str_replace([':commune', ':culture', ':modele'], ['?', '?', '?'],
    "SELECT t.horizon, f.jours_gel, f.jours_stress FROM Fact_Indicateur f
     JOIN Dim_Commune c ON c.id_commune=f.id_commune JOIN Dim_Culture u ON u.id_culture=f.id_culture
     JOIN Dim_Temps t ON t.id_temps=f.id_temps JOIN Dim_Modele m ON m.id_modele=f.id_modele
     WHERE c.code_insee=? AND u.nom=? AND m.nom_modele=? ORDER BY t.annee_debut"));
$q->execute(['75056', 'Blé tendre d\'hiver', 'MRI_AGCM3_2_S']);
$l = $q->fetchAll();
t('requête en étoile : Paris / blé / MRI renvoie 2 horizons dans l\'ordre', count($l) === 2 && $l[0]['horizon'] === 'reference' && $l[1]['horizon'] === 'horizon_2050');

// RGPD : suppression en cascade
$pdo->exec("INSERT INTO Session (id_session) VALUES ('" . bin2hex(random_bytes(16)) . "')");
$sid = $pdo->query("SELECT id_session FROM Session")->fetchColumn();
$pdo->exec("INSERT INTO Consultation (id_session,id_commune,id_culture) VALUES ('$sid',5,1),('$sid',9,9)");
t('2 consultations enregistrées', $n('Consultation') === 2);
$pdo->exec("DELETE FROM Session WHERE id_session='$sid'");
t('RGPD : supprimer la session supprime ses consultations', $n('Consultation') === 0);
t('RGPD : les faits (données climatiques) sont conservés', $n('Fact_Indicateur') === 2);
echo "\n$ok OK, $ko KO\n"; exit($ko === 0 ? 0 : 1);
